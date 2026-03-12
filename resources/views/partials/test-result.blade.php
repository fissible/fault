@if ($missing)
    <div class="notice notice-warn" style="margin-top:0.75rem;">
        Test file not found on disk. Click <strong>Generate Test</strong> to write it.
    </div>
@else
    <div style="display:flex; align-items:center; gap:0.75rem; margin-top:0.75rem; margin-bottom:0.5rem;">
        @if ($passed)
            <span class="badge badge-pass">✓ Passed</span>
        @else
            <span class="badge badge-fail">✗ Failed</span>
        @endif
    </div>

    <pre class="generated-test-code"
         style="max-height:320px; overflow-y:auto;">{{ $output }}</pre>

    @if ($passed && ! $group->isResolved())
        <div style="margin-top:0.75rem; padding:0.6rem 0.9rem; border-radius:5px; background:#0d2010; border:1px solid #1a5a2a; display:flex; align-items:center; gap:0.75rem;">
            <span style="color:#3fb950; font-size:0.85rem;">All tests passing — ready to close this fault?</span>
            <button class="btn btn-success btn-sm"
                    hx-patch="{{ route('watch.faults.status', $group) }}"
                    hx-vals='{"status":"resolved"}'
                    hx-target="#status-badge"
                    hx-swap="outerHTML"
                    style="margin-left:auto;">
                Mark Resolved
            </button>
        </div>
    @endif
@endif
