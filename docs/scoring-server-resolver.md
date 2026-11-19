# Phase 5 — Server Resolver

## Scope

Phase 5 uses the existing rule-version model and tables. No schema migration,
data cleanup, renamed scoring code, new recorder type or scoring formula is
introduced. The Phase 3 service split and Phase 4 audit artifacts remain intact.

## Authority and validation

- `ScoringResolver.php` reads a match's pinned rule identity, verifies sport
  ownership and checks its type against the existing 19-code engine registry.
- Stored config/legacy rule JSON must decode to objects; event definitions must
  be a JSON list of objects. Recognized numeric/boolean/list/aggregation config
  fields are checked before the existing engine consumes them. This is validation
  of current fields, not a complete new rule system for every catalog type.
- Existing effective SCORE/SET defaults remain, derived only from validated
  stored rule fields. Type selection never falls back or uses sport-name inference.
- Existing result snapshots are used when editing results if their type agrees
  with the pinned rule. Missing/corrupt snapshots and type disagreements stop
  recording for review; historical results are never rewritten by the resolver.
- `scoring_type` remains optional for result writes, but when supplied must match
  the stored type exactly. Unknown/blank/array/conflicting types are rejected.
  Draft type requirements remain compatible. Client config fields cannot override
  stored config or a historical snapshot.

## Integration and lifecycle

`ScoringSupport::matchScoringContext()` supplies the context for result, draft,
clock, event/correction/void and entry workflows. Transactional writes lock and
re-check current match identity/status/revision before writing. Clock control and
entry registration now also join an owned transaction, including entry imports.

`MatchScoringGuard.php` uses the existing PDO connection and `FOR UPDATE` row
locks on MySQL/PostgreSQL. SQLite uses its transactional locking behavior.
Rule edits and references are coordinated by rule locks in the main creation,
update and setting paths. Referenced rules still require a new version.

Started/persisted-work evidence includes status, clock, periods, scores, action
revision, confirmation, event/draft/result rows, entry timing/revisions and relevant
audit history. Changing a rule/sport/tournament/team after such work is rejected.
Restoring the status to scheduled cannot erase that evidence. Unchanged rule
identity still allows ordinary metadata edits, such as venue/round information.
Raw score changes and manual completion through `matches.save` must instead use
the scoring APIs. A locked tournament setting cannot be reassigned or unlocked
through the generic setting command; no new unlock workflow is added here.

Automatic scheduled-match reassignment still works for untouched rows, but skips
clock/period/entry-result evidence as well as draft/event/result history. Pending
entrant registration alone is not treated as proof that scoring started.

`Database.php` no longer fills blank legacy scoring configs or null match rule
references on each bootstrap. These require the reviewed backfill from Phase 4.
The pre-existing bootstrap scaffolding/seed and creation of missing default sport
rule versions remain; this is not a replacement migration system or an audit of
their historical provenance. Run production inventory on a copy before release.

## API and UI compatibility

Existing actions, successful result shapes, permissions and CSRF checks remain.
`matches.list` adds `resolved_scoring_type`, `recorder_page`, `scoring_error`;
`matches.center` adds `recorder_page`. A bad row is unavailable without poisoning
the whole selector response. Invalid scoring/config requests use the existing
validation error handling (HTTP 422); lifecycle conflicts use existing runtime
error handling. No unsupported type receives a SCORE page as a fallback.

The selector consults the backend before navigation, so stale/forged client type
metadata cannot select a different recorder. SCORE/SET pickers also use resolved
types. Ship the versioned frontend assets with this backend change.

## Validation and limits

`tools/scoring-resolver-test.php` uses real API handlers and an isolated SQLite
database. It checks all 19 stored types, request tampering, snapshot use, malformed
and missing configuration, no-write rejection, cross-sport/live reassignment,
setting locks, historical evidence and a separate bootstrap process that must not
repair null/blank legacy data. The existing multi-sport test now expects a forged
per-result type switch to be rejected, replacing its old assertion that accepted
that behavior. Engine formulas and their tests are unchanged.

The scoring-module UI test also forges client type/page metadata and verifies
navigation still follows the server. Local icons, recorder assets and API errors
remain test failures; denied optional Google Fonts/Unsplash requests are recorded
as external network observations, without mocking or replacing API responses.

No production copy, MySQL or PostgreSQL server was available for integration tests.
Their lock/deadlock behavior under load still needs deployment verification.
No immutable full-rule snapshot at first start is added in this phase: normal API
guards pin referenced versions, while the additive snapshot/division migrations
remain separate Phase 4 proposals. Do not treat reconstructed legacy config as
verified historical evidence or claim production data readiness from test fixtures.

Executed checks: 14 PHP suites passed (resolver, recording-mode routing, service
rollback, team-score actions, event idempotency, entries, multi-sport, schema,
assignment, News, admin users, engine, initialization cache and bootstrap failure).
Five Chrome/Playwright UI suites passed: SCORE page, SET page, recording-mode,
scoring-modules and `npm run test:ui`. One earlier full-smoke attempt timed out
while awaiting the tournament dashboard title; a diagnostic run and a subsequent
unmodified full-smoke run passed. This intermittent test observation is not a
confirmed backend defect. Optional external resources were observed denied by
the runner; local assets and API interactions were exercised normally.
PHP/JS syntax and whitespace checks passed. Tailwind rebuild output was identical
to the existing bundle; the score engine, Auth implementation and schemas were
not modified.

## Rollback

Revert only Phase 5 changes together, including the frontend metadata consumers
and Database bootstrap policy; preserve earlier uncommitted work. No down-migration
is needed because no schema changed. Re-enabling the previous backend also
re-enables its known request-type/lifecycle weaknesses and automatic legacy repair;
keep recording disabled until the deployment's rollback/data review is complete.
