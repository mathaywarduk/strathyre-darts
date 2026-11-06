<?php

namespace App\Services;

use App\Models\LeagueNight;
use App\Models\Player;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Subs and money for a season.
 *
 * Players hand over money in any amounts. What they owe is simply what's due so
 * far (a night's subs for every league night whose date has arrived, played or
 * not) minus everything they've paid. To show it week by week, money is matched
 * to league nights oldest first: £5 at £2 a night covers two weeks and half of a
 * third.
 */
final class Finance
{
    public readonly int $rate;

    public readonly CarbonInterface $today;

    /** @var array<int, int> player id => total paid, in pence */
    private array $paid = [];

    private int $received;

    private int $expensePence;

    public function __construct(public readonly League $league, ?CarbonInterface $today = null)
    {
        $this->today = ($today ?? now())->copy()->startOfDay();
        $this->rate = $league->settings->subs_pence ?? 0;

        $payments = $league->season->payments()->get();
        foreach ($payments as $payment) {
            $this->paid[$payment->player_id] = ($this->paid[$payment->player_id] ?? 0) + $payment->amount_pence;
        }
        $this->received = (int) $payments->sum('amount_pence');
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

    public function paidBy(int $playerId): int
    {
        return $this->paid[$playerId] ?? 0;
    }

    /** What every player owes in total so far */
    public function due(): int
    {
        return $this->dueNights()->count() * $this->rate;
    }

    public function owedBy(int $playerId): int
    {
        return max(0, $this->due() - $this->paidBy($playerId));
    }

    /** Paid ahead of what's due */
    public function creditOf(int $playerId): int
    {
        return max(0, $this->paidBy($playerId) - $this->due());
    }

    /** How much of that night's subs the player's money covers, oldest week first */
    public function covered(LeagueNight $night, int $playerId): int
    {
        $index = $this->league->nights->search(fn (LeagueNight $n) => $n->id === $night->id);

        return max(0, min($this->rate, $this->paidBy($playerId) - $index * $this->rate));
    }

    public function isPaid(LeagueNight $night, int $playerId): bool
    {
        return $this->covered($night, $playerId) >= $this->rate;
    }

    /** @return list<int> week numbers not fully covered, among nights due so far */
    public function weeksOwed(int $playerId): array
    {
        return $this->dueNights()->reject(fn ($n) => $this->isPaid($n, $playerId))->pluck('week')->all();
    }

    /**
     * Players who owe, the most first.
     *
     * @return Collection<int, array{player: Player, weeks: list<int>, amount: int}>
     */
    public function owing(): Collection
    {
        return $this->league->players
            ->map(fn (Player $p) => ['player' => $p, 'weeks' => $this->weeksOwed($p->id), 'amount' => $this->owedBy($p->id)])
            ->filter(fn ($r) => $r['amount'] > 0)
            ->sort(fn ($a, $b) => [$b['amount'], mb_strtolower($a['player']->name)] <=> [$a['amount'], mb_strtolower($b['player']->name)])
            ->values();
    }

    /** Money actually received (all payments) */
    public function received(): int
    {
        return $this->received;
    }

    public function expenses(): int
    {
        return $this->expensePence;
    }

    public function owed(): int
    {
        return $this->league->players->sum(fn (Player $p) => $this->owedBy($p->id));
    }

    public function opening(): int
    {
        return (int) $this->league->season->opening_pence;
    }

    public function balance(): int
    {
        return $this->opening() + $this->received() - $this->expenses();
    }

    /**
     * One week's subs: who is covered in full and who isn't.
     *
     * @return array{paid: Collection<int, Player>, unpaid: Collection<int, Player>, covered: array<int, int>, received: int, expected: int, owed: int}
     */
    public function week(LeagueNight $night): array
    {
        $covered = [];
        foreach ($this->league->players as $p) {
            $covered[$p->id] = $this->covered($night, $p->id);
        }
        $paid = $this->league->players->filter(fn (Player $p) => $covered[$p->id] >= $this->rate)->values();
        $unpaid = $this->league->players->reject(fn (Player $p) => $covered[$p->id] >= $this->rate)->values();

        return [
            'paid' => $paid,
            'unpaid' => $unpaid,
            'covered' => $covered,
            'received' => array_sum($covered),
            'expected' => $this->league->players->count() * $this->rate,
            'owed' => $this->isDue($night) ? $this->league->players->count() * $this->rate - array_sum($covered) : 0,
        ];
    }

    /** @return array{paid: int, due: int, owed: int, credit: int, weeks: list<int>, cells: list<array{night: LeagueNight, paid: bool}>} */
    public function player(int $playerId): array
    {
        return [
            'paid' => $this->paidBy($playerId),
            'due' => $this->due(),
            'owed' => $this->owedBy($playerId),
            'credit' => $this->creditOf($playerId),
            'weeks' => $this->weeksOwed($playerId),
            'cells' => $this->dueNights()->map(fn ($n) => ['night' => $n, 'paid' => $this->isPaid($n, $playerId)])->all(),
        ];
    }

    public static function weeksText(array $weeks, bool $capital = false): string
    {
        $word = count($weeks) === 1 ? 'week' : 'weeks';

        return ($capital ? ucfirst($word) : $word).' '.implode(', ', $weeks);
    }
}
