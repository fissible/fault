# fissible/fault — Project

Current version: **0.1.0**
Org standards: [fissible/.github](https://github.com/fissible/.github)

---

## Status

All features documented in README are implemented. No open issues.

---

## Session handoff notes

### 2026-03-25

**Completed:**
- Full feature audit — implementation matches README spec; two undocumented extras (Run Test, Delete)
- Wired CI: `.github/workflows/ci.yml` (PHP 8.2/8.3 matrix, checks out `fissible/watch` sibling for path dep)
- Wired release workflow: `.github/workflows/release.yml` (delegates to `fissible/.github` reusable)
- Copied `release.sh` and `.cliff.toml` from `fissible/.github`
- Cut and pushed **v0.1.0** (first release)
- Fixed stale test assertion: `@group fault-` → `#[Group('fault-` (PHPUnit 11 attribute syntax)

**Next task:** No scheduled tasks. Candidates:
- Fix config comment discrepancy: `fault.php` claims wildcard `str_starts_with` matching but `shouldIgnore()` uses `instanceof`
- Add HTTP/controller tests (currently only unit coverage for `FaultReporter` and `TestStubGenerator`)
- Publish to Packagist (not in current roadmap — fault consumed as VCS dep per Phase 12 design)

**Decisions:**
- CI checks out `fissible/watch` as a sibling (not a Packagist dep) because fault requires `@dev` path version locally
- First release is `v0.1.0` (minor bump from placeholder `0.0.0`) — conventional for an initial feature-complete release
- `fissible/watch` must be a **public** repo for CI to check it out without a PAT; made public 2026-03-25

### Follow-up (same session)

- CI failed: `fissible/watch` was private — `actions/checkout` returned 404
- Fixed by making `watch` public on GitHub (no code change needed)
- fault CI confirmed green; Phase 12 exit condition fully met
