# Scoring service boundaries

`AppService::handle()` and the existing public `AppService` methods retain their
signatures and API responses. Scoring methods now delegate to the autoloaded
`app/ScoringService.php`. No API action is added or renamed.

The concrete `ScoringService` composes implementation traits, following the
project's existing trait-based module pattern. Read the relevant operation file
when changing one responsibility:

| File | Responsibility |
| --- | --- |
| `MatchResultOperations.php` | Save/read/revise/reopen results and recalculate standings |
| `MatchRecordingOperations.php` | Match center, periods/clock, draft/history |
| `MatchEventOperations.php` | Events, corrections, voiding, idempotency and score synchronization |
| `MatchEntryOperations.php` | Multi-entrant heats, entrants, timing, ranking and confirmation |
| `ScoringSupport.php` | Shared data queries, decoding, access checks, audit and value conversion |

All operation traits belong to `ScoringService`; they are not separate services
or independently registered handlers. Cross-operation `self::` calls stay inside
that class. The method bodies, validation order, SQL, exception messages, audit
details, transaction boundaries and revision/idempotency checks are preserved.
`ScoringEngine` and `ScoringTypeDetector` remain unchanged.

`ScoringSupport` is also composed into `AppService`, because match management,
Tournament, News and other existing features use the same private helpers.
There is one source implementation for each helper, rather than duplicate SQL
or newly public generic database methods. Both hosts use the existing singleton
`Database::pdo()`. The column metadata cache remains local to its host class;
neither host caches user permissions or scoring state.

`ensureMatchPeriods()` and `recalculateStandings()` are internal service entry
points used by match creation/update/deletion and standings refresh. Their
`AppService` wrappers remain private. They are public only on the service so the
facade can delegate; do not expose them as new API actions. Authorization for
external scoring operations remains inside the service, and the existing API
entry point retains its CSRF check.

## Phase 5 resolver and lifecycle guards

`ScoringResolver` validates the pinned raw rule and stored JSON before deriving
the existing effective defaults. It never guesses a type from a sport name or
uses SCORE for missing/unknown types. Existing result snapshots must match the
pinned type and are used when revising results; conflicting history is rejected
for review. `matchScoringContext()` is shared by recording reads and writes.

`MatchScoringGuard` serializes match/rule mutations on the existing PDO connection
and checks persisted clock/period/draft/event/entry/result evidence before allowing
reconfiguration. API-level setting locks cannot be silently removed. No schema
migration or new snapshot table is introduced. See
`docs/scoring-server-resolver.md` for behavior changes and deployment limitations.

## Regression checks

Run PHP tests with `PRSPORT_DB_DSN=sqlite::memory:`. In addition to existing engine,
routing, assignment, entries, idempotency and multi-sport tests,
`tools/scoring-service-test.php` exercises facade/service response parity, guest
authorization and real SQLite-trigger failures during result, event and draft
writes. It checks rollback of scores, revisions, standings, request ledgers and
audit rows, and successful retries. No mocked service or database is used.

Keep the existing SCORE, SET, scoring-module and full UI smoke tests. MySQL and
PostgreSQL integration should also run in their deployment environments before
release; the extraction does not change their SQL or schema.
