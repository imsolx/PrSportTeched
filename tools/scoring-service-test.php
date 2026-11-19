<?php
declare(strict_types=1);

if (getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') {
    throw new RuntimeException('Scoring service tests require isolated SQLite memory database');
}
require dirname(__DIR__) . '/app/bootstrap.php';
// Keep session headers writable until the authorization checks log out.
ob_start();
Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234']);
$pdo = Database::pdo();

function checkScoring(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function scoringFixture(string $type): array
{
    static $sequence = 0;
    $name = 'Service regression ' . $type . ' ' . ++$sequence;
    $sport = AppService::handle('sports.save', [
        'name' => $name, 'type' => 'team', 'gender' => 'open',
        'min_players' => 1, 'max_players' => 2, 'icon' => 'general', 'scoring_type' => $type,
    ]);
    $tournament = AppService::handle('tournaments.save', [
        'name' => $name, 'sport_ids' => [$sport['id']], 'format' => 'round_robin',
    ]);
    $teams = [];
    foreach (['130', '140'] as $department) {
        $teams[] = AppService::handle('teams.save', [
            'name' => $type . ' ' . $department, 'tournament_id' => $tournament['id'],
            'sport_id' => $sport['id'], 'department_code' => $department,
        ]);
    }
    return AppService::handle('matches.save', [
        'tournament_id' => $tournament['id'], 'sport_id' => $sport['id'],
        'team1_id' => $teams[0]['id'], 'team2_id' => $teams[1]['id'], 'round_name' => 'Service regression',
    ]);
}

function scoringRows(string $table): array
{
    $key = $table === 'match_event_requests' ? 'request_id' : 'id';
    return Database::pdo()->query('SELECT * FROM ' . $table . ' ORDER BY ' . $key)->fetchAll();
}

function rejectScoringWrite(string $trigger, callable $write, array $tables): void
{
    $pdo = Database::pdo();
    $before = [];
    foreach ($tables as $table) $before[$table] = scoringRows($table);
    $pdo->exec($trigger);
    try {
        $write();
        throw new RuntimeException('Injected database failure was not observed');
    } catch (PDOException $error) {
        if (!str_contains($error->getMessage(), 'phase3_failure')) throw $error;
    } finally {
        $pdo->exec('DROP TRIGGER phase3_failure');
    }
    checkScoring(!$pdo->inTransaction(), 'Transaction leaked after failed scoring write');
    foreach ($before as $table => $rows) {
        checkScoring(scoringRows($table) === $rows, $table . ' changed despite rollback');
    }
}

$match = scoringFixture('SCORE');
$id = (string) $match['id'];
checkScoring(
    AppService::handle('matches.center', ['match_id' => $id]) === ScoringService::matchCenter($id),
    'Facade and service return different match center data'
);
$result = ['scoring_type' => 'SCORE', 'expected_revision' => 0,
    'result_data' => ['regular_score' => ['home' => 2, 'away' => 1]]];
$rejectResult = "CREATE TRIGGER phase3_failure BEFORE UPDATE OF status ON matches
    WHEN NEW.id = " . (int) $id . " AND NEW.status = 'completed'
    BEGIN SELECT RAISE(ABORT, 'phase3_failure'); END";
$resultTables = ['matches', 'match_results', 'match_result_revisions', 'standings', 'audit_logs'];
rejectScoringWrite($rejectResult, static function () use ($id, $result): void {
    AppService::saveMatchResult($id, $result);
}, $resultTables);
AppService::saveMatchResult($id, $result);
$detail = ScoringService::matchResultDetail($id);
checkScoring((int) $detail['result']['revision_number'] === 1, 'Result revision not persisted');
$edited = ['scoring_type' => 'SCORE', 'expected_revision' => 1, 'reason' => 'Service regression edit',
    'result_data' => ['regular_score' => ['home' => 3, 'away' => 1]]];
rejectScoringWrite($rejectResult, static function () use ($id, $edited): void {
    ScoringService::saveMatchResult($id, $edited);
}, $resultTables);
checkScoring(
    AppService::matchResultDetail($id) === ScoringService::matchResultDetail($id),
    'Result facade response differs after rollback'
);
echo "PASS result create/update rollback preserves results, revisions, standings, audit and transaction boundaries\n";

$eventMatch = scoringFixture('SCORE');
$eventId = (string) $eventMatch['id'];
AppService::updateMatchClock($eventId, ['clock_action' => 'start']);
$center = ScoringService::matchCenter($eventId);
$definition = array_values(array_filter($center['rule']['event_types_data'], static fn(array $row): bool => (int) $row['points'] > 0))[0];
$event = ['match_id' => $eventId, 'team_id' => $eventMatch['team1_id'], 'event_type' => $definition['code'],
    'quick_team_score' => true, 'points' => 1, 'request_id' => 'phase3_event_retry', 'expected_revision' => 0];
rejectScoringWrite("CREATE TRIGGER phase3_failure BEFORE UPDATE OF team1_score ON matches
    WHEN NEW.id = " . (int) $eventId . " BEGIN SELECT RAISE(ABORT, 'phase3_failure'); END",
    static function () use ($event): void { AppService::saveMatchEvent($event); },
    ['matches', 'match_events', 'match_event_requests', 'audit_logs']);
$saved = ScoringService::saveMatchEvent($event);
checkScoring((int) $saved['points'] === 1, 'Event retry failed');
checkScoring(AppService::saveMatchEvent($event) === $saved, 'Idempotent retry changed the event');
checkScoring((int) ScoringService::matchCenter($eventId)['match']['team1_score'] === 1, 'Event score not persisted once');
echo "PASS event rollback preserves action revision, event ledger and score; retry remains idempotent\n";

$setMatch = scoringFixture('SET');
$setId = (string) $setMatch['id'];
$draft = ['match_id' => $setId, 'scoring_type' => 'SET', 'expected_revision' => 0,
    'result_data' => ['sets' => [['home' => 10, 'away' => 8]]]];
rejectScoringWrite("CREATE TRIGGER phase3_failure BEFORE INSERT ON match_events
    WHEN NEW.match_id = " . (int) $setId . " AND NEW.event_type = 'recording_draft'
    BEGIN SELECT RAISE(ABORT, 'phase3_failure'); END",
    static function () use ($draft): void { ScoringService::saveMatchRecordingDraft($draft); },
    ['matches', 'match_events', 'audit_logs']);
AppService::saveMatchRecordingDraft($draft);
checkScoring((int) ScoringService::matchCenter($setId)['recording_draft']['result_data']['sets'][0]['home'] === 10, 'Draft retry failed');
echo "PASS draft rollback preserves action revision and history; retry persists draft\n";

Auth::logout();
$messages = [];
foreach ([static fn(): array => AppService::matchCenter($id), static fn(): array => ScoringService::matchCenter($id)] as $read) {
    try { $read(); throw new LogicException('Guest accessed scoring data'); }
    catch (RuntimeException $error) { $messages[] = $error->getMessage(); }
}
checkScoring(count($messages) === 2 && $messages[0] === $messages[1], 'Facade/service authorization differs');
echo "PASS facade/service response parity and guest authorization\n";
ob_end_flush();
