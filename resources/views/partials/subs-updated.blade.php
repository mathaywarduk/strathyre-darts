@use('App\Support\Dates')
@if ($updated)
    <p class="mute" style="margin: 12px 0 0">Subs last updated {{ Dates::dayMonth($updated) }}</p>
@endif
