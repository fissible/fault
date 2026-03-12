<div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem; flex-wrap:wrap;">
    <code style="font-size:0.78rem; color:var(--text-muted);">tests/Unit/Faults/{{ $testClassName }}.php</code>
    @if ($testFileExists)
        <span class="badge badge-pass" style="font-size:0.7rem;">saved</span>
    @else
        <span class="badge badge-muted" style="font-size:0.7rem;">not on disk</span>
    @endif
</div>

<pre class="generated-test-code"><code>{{ $group->generated_test }}</code></pre>

<div style="display:flex; gap:0.5rem; align-items:center; margin-top:0.5rem; flex-wrap:wrap;">
    <button class="btn btn-sm btn-ghost copy-btn"
            x-data
            @click="navigator.clipboard.writeText($el.closest('div').previousElementSibling.textContent)">
        Copy
    </button>
    @if ($testFileExists)
        <button class="btn btn-secondary btn-sm"
                hx-post="{{ route('watch.faults.run-test', $group) }}"
                hx-target="#fault-test-result"
                hx-swap="innerHTML"
                hx-indicator="#run-test-spinner">
            Run Test
        </button>
        <span id="run-test-spinner"
              class="htmx-indicator muted"
              style="font-size:0.8rem;">Running…</span>
    @endif
</div>
