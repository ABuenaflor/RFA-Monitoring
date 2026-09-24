<x-mail::message>
# PCT Alert

Hello {{ $recipient->name }},

The following {{ \Illuminate\Support\Str::plural('case', count($items) + $hiddenCount) }} assigned to you
{{ count($items) + $hiddenCount === 1 ? 'has' : 'have' }} reached a PCT alert level as of
{{ now()->format('M d, Y') }}.

<x-mail::table>
| Case | PCT Stage | Status | Day |
| :--- | :--- | :--- | :---: |
@foreach ($items as $item)
| [{{ $item['reference'] }}]({{ $item['url'] }}) | {{ $item['stage_label'] }} | **{{ $item['level_label'] }}** | {{ $item['days'] ?? '—' }} |
@endforeach
</x-mail::table>

@if ($hiddenCount > 0)
…and {{ $hiddenCount }} more {{ \Illuminate\Support\Str::plural('case', $hiddenCount) }}. Open the system to see the full list.
@endif

<x-mail::button :url="$notificationsUrl">
Open Notifications
</x-mail::button>

You will receive one email per case for each alert level (nearing, due today, breached).
In-app notifications continue to show the current status.

{{ config('app.name') }}
</x-mail::message>
