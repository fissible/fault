<x-watch::layout title="Faults" subtitle="Exceptions captured by your application, grouped by fingerprint.">

{{-- Filter bar --}}
<form class="filter-bar" method="GET" action="{{ route('watch.faults') }}">
    <div class="filter-bar-inner">
        <div class="btn-group" role="group">
            @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'ignored' => 'Ignored'] as $s => $label)
                <a href="{{ route('watch.faults', array_merge(request()->query(), ['status' => $s])) }}"
                   class="btn btn-sm {{ $status === $s ? 'btn-primary' : 'btn-secondary' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <input type="hidden" name="status" value="{{ $status }}">
        <input type="text"
               name="search"
               class="filter-input"
               placeholder="Search class, message, file…"
               value="{{ $search }}"
               x-data
               @keyup.escape="$el.value=''; $el.form.submit()">
        <button type="submit" class="btn btn-sm btn-secondary">Search</button>
        @if ($search)
            <a href="{{ route('watch.faults', ['status' => $status]) }}" class="btn btn-sm btn-ghost">Clear</a>
        @endif
    </div>
</form>

{{-- Groups list --}}
@if ($groups->isEmpty())
    <div class="empty-state">
        <p>No {{ $status }} faults{{ $search ? " matching \"{$search}\"" : '' }}.</p>
    </div>
@else
    <div class="fault-list">
        @foreach ($groups as $group)
            <a href="{{ route('watch.faults.show', $group) }}" class="fault-row">
                <div class="fault-row-header">
                    <span class="fault-class">{{ $group->shortClass() }}</span>
                    <span class="badge {{ $group->statusBadgeClass() }}">{{ $group->status }}</span>
                    <span class="fault-count">{{ number_format($group->occurrence_count) }}×</span>
                </div>
                <div class="fault-message">{{ \Illuminate\Support\Str::limit($group->message, 120) }}</div>
                <div class="fault-meta">
                    <span class="fault-file">{{ $group->relativeFile() }}{{ $group->line ? ':' . $group->line : '' }}</span>
                    <span class="fault-time">last {{ $group->last_seen_at?->diffForHumans() }}</span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="pagination-wrapper">
        {{ $groups->links() }}
    </div>
@endif

</x-watch::layout>
