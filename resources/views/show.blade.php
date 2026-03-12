<x-watch::layout title="{{ $group->shortClass() }}">

<div class="fault-detail" x-data="{ notesChanged: false, testVisible: {{ $group->generated_test ? 'true' : 'false' }}, testFileExists: {{ $testFileExists ? 'true' : 'false' }} }">

    {{-- Back + status badge inline under the auto-rendered page-header --}}
    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1.5rem; margin-top:-0.5rem;">
        <a href="{{ route('watch.faults') }}" class="back-link">← All Faults</a>
        <span class="badge {{ $group->statusBadgeClass() }}" id="status-badge">{{ $group->status }}</span>
        <span style="color:var(--text-muted); font-size:0.82rem;">{{ number_format($group->occurrence_count) }} occurrence{{ $group->occurrence_count !== 1 ? 's' : '' }}</span>
    </div>

    {{-- Meta --}}
    <div class="detail-section">
        <dl class="fault-meta-grid">
            <dt>Class</dt>
            <dd><code>{{ $group->class_name }}</code></dd>

            <dt>File</dt>
            <dd><code>{{ $group->relativeFile() }}{{ $group->line ? ':' . $group->line : '' }}</code></dd>

            <dt>Message</dt>
            <dd>{{ $group->message ?? '(none)' }}</dd>

            <dt>First seen</dt>
            <dd>{{ $group->first_seen_at?->format('Y-m-d H:i:s T') }}</dd>

            <dt>Last seen</dt>
            <dd>{{ $group->last_seen_at?->format('Y-m-d H:i:s T') }}</dd>

            @if ($group->app_version)
            <dt>App version</dt>
            <dd>{{ $group->app_version }}</dd>
            @endif
        </dl>
    </div>

    {{-- Status actions --}}
    <div class="detail-section">
        <h2>Status</h2>
        <div class="action-row">
            @if (! $group->isResolved())
                <button class="btn btn-success btn-sm"
                        hx-patch="{{ route('watch.faults.status', $group) }}"
                        hx-vals='{"status":"resolved"}'
                        hx-target="#status-badge"
                        hx-swap="outerHTML">
                    Mark Resolved
                </button>
            @endif
            @if (! $group->isIgnored())
                <button class="btn btn-warning btn-sm"
                        hx-patch="{{ route('watch.faults.status', $group) }}"
                        hx-vals='{"status":"ignored"}'
                        hx-target="#status-badge"
                        hx-swap="outerHTML">
                    Ignore
                </button>
            @endif
            @if (! $group->isOpen())
                <button class="btn btn-secondary btn-sm"
                        hx-patch="{{ route('watch.faults.status', $group) }}"
                        hx-vals='{"status":"open"}'
                        hx-target="#status-badge"
                        hx-swap="outerHTML">
                    Reopen
                </button>
            @endif
            <button class="btn btn-danger btn-sm"
                    hx-delete="{{ route('watch.faults.delete', $group) }}"
                    hx-confirm="Delete this fault group permanently?"
                    hx-push-url="{{ route('watch.faults') }}">
                Delete
            </button>
        </div>
    </div>

    {{-- Notes --}}
    <div class="detail-section">
        <h2>Notes / AI Evaluation</h2>
        <textarea class="notes-textarea"
                  id="resolution-notes"
                  placeholder="Paste an AI evaluation, root-cause analysis, or developer comments…"
                  x-on:input="notesChanged = true"
                  hx-patch="{{ route('watch.faults.notes', $group) }}"
                  hx-trigger="change delay:1s"
                  hx-vals="js:{resolution_notes: document.getElementById('resolution-notes').value}"
                  hx-swap="none">{{ $group->resolution_notes }}</textarea>
        <p class="notes-hint" x-show="notesChanged" x-transition>Auto-saving…</p>
    </div>

    {{-- Stack trace --}}
    @if ($group->sample_context)
    <div class="detail-section">
        <h2>Stack Trace (sample)</h2>
        <div class="stack-trace">
            @foreach ($group->sample_context as $frame)
                <div class="stack-frame">
                    <span class="stack-fn">{{ $frame['function'] ?? '?' }}</span>
                    @if (!empty($frame['file']))
                        <span class="stack-file">{{ $frame['file'] }}{{ isset($frame['line']) ? ':' . $frame['line'] : '' }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Test generation --}}
    <div class="detail-section">
        <h2>Regression Test</h2>
        <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:0.75rem;">Generate a PHPUnit skeleton. When the test passes, mark this fault as resolved.</p>

        <button class="btn btn-secondary btn-sm"
                hx-post="{{ route('watch.faults.test', $group) }}"
                hx-target="#generated-test-section"
                hx-swap="innerHTML"
                x-on:htmx:after-request.window="testVisible = true; testFileExists = true">
            {{ $group->generated_test ? 'Regenerate Test' : 'Generate Test' }}
        </button>

        <div id="generated-test-section" x-show="testVisible" x-transition>
            @if ($group->generated_test)
                @include('fault::partials.generated-test', [
                    'group'          => $group,
                    'testFilePath'   => $testFilePath,
                    'testFileExists' => $testFileExists,
                    'testClassName'  => $testClassName,
                ])
            @endif
        </div>

        <div id="fault-test-result"></div>
    </div>

</div>

</x-watch::layout>
