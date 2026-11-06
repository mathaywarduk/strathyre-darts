<?php

namespace App\Services;

use App\Models\LeagueNight;
use App\Models\Player;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Subs and money for a season. Subs are due from each league night's date,
 * for every player, whether or not their match was played.
 */
final class Finance
{
    public readonly int $rate;

    public readonly CarbonInterface $today;

    /** @var array<string, true> "nightId:playerId" */
    private array $paid = [];

    private int $paymentCount;

    private int $expensePence;

    public function __construct(public readonly League $league, ?CarbonInterface $today = null)
    {
        $this->today = ($today ?? now())->copy()->startOfDay();
        $this->rate = $league->settings->subs_pence ?? 0;

        $payments = $league->season->payments()->get();
        foreach ($payments as $p) {
            $this->paid[$p->league_night_id.':'.$p->player_id] = true;
        }
        $this->paymentCount = $payments->count();
        $this->expensePence = (int) $league->season->expenses()->sum('cost_pence');
    }

    /** @return Collection<int, LeagueNight> nights whose date has arrived */
    public function dueNights(): Collection
    {
        return $this->league->nights->filter(fn (LeagueNight $n) => $n->date->lte($this->today))->values();
    }

    public function isDue(LeagueNight $night): bool
    {
        return $night->date->lte($this->today);
    }

    public function isPaid(LeagueNight $night, Player|int $player): bool
    {
        return isset($this->paid[$night->id.':'.(is_int($player) ? $player : $player->id)]);
    }

    /** @return list<int> week numbers a player hasn't paid for, among nights due so far */
    public function weeksOwed(int $playerId): array
    {
        return $this->dueNights()->reject(fn ($n) => $this->isPaid($n, $playerId))->pluck('week')->all();
    }

    /**
     * Players who owe, most weeks first.
     *
     * @return Collection<int, array{player: Player, weeks: list<int>, amount: int}>
     */
    public function owing(): Collection
    {
        return $this->league->players
            ->map(fn (Player $p) => ['player' => $p, 'weeks' => $this->weeksOwed($p->id)])
            ->filter(fn ($r) => $r['weeks'] !== [])
            ->map(fn ($r) => $r + ['amount' => count($r['weeks']) * $this->rate])
            ->sort(fn ($a, $b) => [count($b['weeks']), mb_strtolower($a['player']->name)] <=> [count($a['weeks']), mb_strtolower($b['player']->name)])
            ->values();
    }

    public function received(): int
    {
        return $this->paymentCount * $this->rate;
    }

    public function expenses(): int
    {
        return $this->expensePence;
    }

    public function owed(): int
    {
        return $this->owing()->sum('amount');
    }

    public function opening(): int
    {
        return (int) $this->league->season->opening_pence;
    }

    public function balance(): int
    {
        return $this->opening() + $this->received() - $this->expenses();
    }

    /** One week's subs: who has and hasn't paid @return array{paid: Collection, unpaid: Collection, received: int, expected: int, owed: int} */
    public function week(LeagueNight $night): array
    {
        $paid = $this->league->players->filter(fn (Player $p) => $this->isPaid($night, $p))->values();
        $unpaid = $this->league->players->reject(fn (Player $p) => $this->isPaid($night, $p))->values();

        return [
            'paid' => $paid,
            'unpaid' => $unpaid,
            'received' => $paid->count() * $this->rate,
            'expected' => $this->league->players->count() * $this->rate,
            'owed' => $this->isDue($night) ? $unpaid->count() * $this->rate : 0,
        ];
    }

    /** @return array{paid: int, due: int, owed: int, weeks: list<int>, cells: list<array{night: LeagueNight, paid: bool}>} */
    public function player(int $playerId): array
    {
        $nights = $this->dueNights();
        $cells = $nights->map(fn ($n) => ['night' => $n, 'paid' => $this->isPaid($n, $playerId)])->all();
        $weeks = $this->weeksOwed($playerId);

        return [
            'paid' => ($nights->count() - count($weeks)) * $this->rate,
            'due' => $nights->count() * $this->rate,
            'owed' => count($weeks) * $this->rate,
            'weeks' => $weeks,
            'cells' => $cells,
        ];
    }

    public static function weeksText(array $weeks, bool $capital = false): string
    {
        $word = count($weeks) === 1 ? 'week' : 'weeks';

        return ($capital ? ucfirst($word) : $word).' '.implode(', ', $weeks);
    }
}
