<?php
declare(strict_types=1);
if (getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') throw new RuntimeException('Use isolated SQLite memory database');
require dirname(__DIR__) . '/app/bootstrap.php';
ob_start();
Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234']);
$pdo = Database::pdo();

function resolverCheck(bool $value, string $label): void
{
    if (!$value) throw new RuntimeException($label);
}
function resolverFixture(string $type): array
{
    static $sequence = 0;
    $sport = AppService::handle('sports.save', ['name' => 'Resolver fixture ' . ++$sequence,
        'type' => 'team', 'gender' => 'open', 'min_players' => 1, 'max_players' => 2, 'icon' => 'badminton',
        'scoring_type' => in_array($type, ScoringTypeDetector::SELECTABLE_TYPES, true) ? $type : 'SCORE']);
    $rule = AppService::handle('sportRules.list', ['sport_id' => $sport['id']])[0];
    if ($rule['scoring_type'] !== $type) {
        $rule = AppService::handle('sportRules.save', ['sport_id' => $sport['id'], 'name' => 'Legacy registry fixture', 'scoring_type' => $type]);
    }
    $tournament = AppService::handle('tournaments.save', ['name' => 'Resolver tournament ' . $sequence, 'sport_ids' => [$sport['id']], 'format' => 'round_robin']);
    AppService::handle('tournaments.sportSetting', ['tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'rule_version_id' => $rule['id'], 'status' => 'ready']);
    $teams = [];
    foreach (['130', '140'] as $department) $teams[] = AppService::handle('teams.save', ['name' => 'Resolver ' . $department,
        'tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'department_code' => $department]);
    return AppService::handle('matches.save', ['tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'team1_id' => $teams[0]['id'], 'team2_id' => $teams[1]['id']]);
}
function resolverState(): array
{
    $tables = ['matches', 'match_results', 'match_result_revisions', 'match_events', 'match_event_requests', 'match_periods', 'match_entries', 'audit_logs', 'standings', 'tournament_sport_settings'];
    $out = [];
    foreach ($tables as $table) $out[$table] = Database::pdo()->query('SELECT * FROM ' . $table . ' ORDER BY ' . ($table === 'match_event_requests' ? 'request_id' : 'id'))->fetchAll();
    return $out;
}
function resolverReject(callable $write): void
{
    $before = resolverState();
    try { $write(); throw new LogicException('Invalid operation was accepted'); }
    catch (PDOException $error) { throw $error; }
    catch (RuntimeException | InvalidArgumentException $error) { /* Expected business rejection. */ }
    resolverCheck(!Database::pdo()->inTransaction(), 'Leaked transaction');
    resolverCheck($before === resolverState(), 'Rejected operation changed persisted data');
}

foreach (ScoringEngine::TYPES as $type) {
    $match = resolverFixture($type);
    $center = AppService::matchCenter((string) $match['id']);
    resolverCheck($center['resolved_scoring_type'] === $type, 'Registry type not preserved: ' . $type);
    resolverCheck($center['recorder_page'] === (['SCORE' => 'score.php', 'SET' => 'set.php'][$type] ?? null), 'Wrong recorder page');
}
echo "PASS all 19 stored types, 2 existing recorder pages, no sport-name inference\n";

$score = resolverFixture('SCORE'); $id = (string) $score['id'];
$pdo->exec("UPDATE sport_rule_versions SET created_by = 'migration' WHERE id = " . (int) $score['rule_version_id']);
resolverCheck(AppService::matchCenter($id)['resolved_scoring_type'] === 'SCORE', 'Legacy creator caused name inference');
$result = ['match_id' => $id, 'result_data' => ['regular_score' => ['home' => 1, 'away' => 0]], 'expected_revision' => 0];
foreach (['SET', 'RACE_POSITION', 'UNKNOWN', '', ['SCORE']] as $type) {
    resolverReject(static fn() => AppService::handle('matches.result', array_replace($result, ['scoring_type' => $type])));
}
AppService::handle('matches.result', $result + ['scoring_config' => ['allow_draw' => false]]);
resolverCheck(AppService::matchResultDetail($id)['result']['scoring_type'] === 'SCORE', 'Omitted type was not server resolved');
resolverCheck(AppService::matchResultDetail($id)['result']['config_snapshot_data']['allow_draw'] === true, 'Request overrode config');
$snapshot = json_encode(['allow_draw' => false, 'include_in_standings' => true]);
$stmt = $pdo->prepare('UPDATE match_results SET config_snapshot = :snapshot WHERE match_id = :id');
$stmt->execute([':snapshot' => $snapshot, ':id' => $id]);
resolverReject(static fn() => AppService::handle('matches.result', ['match_id' => $id, 'scoring_type' => 'SCORE', 'reason' => 'snapshot test', 'expected_revision' => 1,
    'result_data' => ['regular_score' => ['home' => 0, 'away' => 0]]]));
echo "PASS forged type/config rejected, omitted type resolved, historical snapshot enforced\n";

$bad = resolverFixture('SCORE'); $badId = (string) $bad['id']; $ruleId = (int) $bad['rule_version_id'];
$original = $pdo->query('SELECT * FROM sport_rule_versions WHERE id = ' . $ruleId)->fetch();
foreach (['{', '[]', 'null', '{"quick_points":"bad"}', '{"best_of":0}'] as $config) {
    $query = $pdo->prepare('UPDATE sport_rule_versions SET scoring_config = :config WHERE id = :id'); $query->execute([':config' => $config, ':id' => $ruleId]);
    foreach (['matches.center', 'matches.clock', 'matches.recordingDraft', 'matches.event', 'matches.result'] as $action) {
        resolverReject(static fn() => AppService::handle($action, ['match_id' => $badId, 'scoring_type' => 'SCORE', 'clock_action' => 'start', 'result_data' => ['regular_score' => ['home' => 1, 'away' => 0]]]));
    }
    $listed = array_values(array_filter(AppService::listMatches(), static fn($row) => (string) $row['id'] === $badId))[0];
    resolverCheck($listed['recorder_page'] === null && $listed['resolved_scoring_type'] === null && $listed['scoring_error'], 'Bad match received a fallback page');
}
$query->execute([':config' => $original['scoring_config'], ':id' => $ruleId]);
foreach (['', 'SET_SCORE', 'UNKNOWN'] as $type) {
    $query = $pdo->prepare('UPDATE sport_rule_versions SET scoring_type = :type WHERE id = :id'); $query->execute([':type' => $type, ':id' => $ruleId]);
    resolverReject(static fn() => AppService::matchCenter($badId));
}
$query->execute([':type' => 'SCORE', ':id' => $ruleId]);
foreach ([null, 999999] as $missing) {
    $query = $pdo->prepare('UPDATE matches SET rule_version_id = :rule WHERE id = :id'); $query->execute([':rule' => $missing, ':id' => $badId]);
    resolverReject(static fn() => AppService::matchCenter($badId));
}
$query->execute([':rule' => $ruleId, ':id' => $badId]);
$pdo->exec("UPDATE match_results SET scoring_type = 'SET' WHERE match_id = " . (int) $id);
resolverReject(static fn() => AppService::matchCenter($id));
$pdo->exec("UPDATE match_results SET scoring_type = 'SCORE' WHERE match_id = " . (int) $id);
echo "PASS missing/unknown/malformed rules and conflicting history fail closed without writes\n";

$live = resolverFixture('SCORE'); $liveId = (string) $live['id'];
$next = AppService::handle('sportRules.save', ['sport_id' => $live['sport_id'], 'name' => 'Next version', 'scoring_type' => 'SET']);
$foreign = resolverFixture('SET');
resolverReject(static fn() => AppService::updateMatch($liveId, ['rule_version_id' => $foreign['rule_version_id']]));
AppService::updateMatchClock($liveId, ['clock_action' => 'start']);
resolverReject(static fn() => AppService::updateMatch($liveId, ['rule_version_id' => $next['id']]));
resolverReject(static fn() => AppService::updateMatch($liveId, ['status' => 'scheduled']));
resolverReject(static fn() => AppService::updateMatch($liveId, ['team1_score' => 100]));
resolverReject(static fn() => AppService::updateMatchClock($liveId, ['clock_action' => 'pause', 'scoring_type' => 'SET']));
resolverReject(static fn() => AppService::saveMatchEvent(['match_id' => $liveId, 'scoring_type' => 'SET']));
AppService::updateMatch($liveId, ['venue' => 'Metadata remains editable']);
resolverCheck(AppService::matchCenter($liveId)['match']['venue'] === 'Metadata remains editable', 'Metadata editing regressed');
$setting = ['tournament_id' => $live['tournament_id'], 'sport_id' => $live['sport_id'], 'rule_version_id' => $live['rule_version_id'], 'status' => 'locked'];
AppService::handle('tournaments.sportSetting', $setting);
resolverReject(static fn() => AppService::handle('tournaments.sportSetting', array_replace($setting, ['status' => 'ready'])));
resolverReject(static fn() => AppService::handle('tournaments.sportSetting', array_replace($setting, ['rule_version_id' => $next['id']])));
echo "PASS cross-sport/live reassignment, status rewind, raw score bypass and locked setting changes rejected\n";

$draft = resolverFixture('SET');
$nextDraft = AppService::handle('sportRules.save', ['sport_id' => $draft['sport_id'], 'name' => 'Next draft version', 'scoring_type' => 'SCORE']);
AppService::saveMatchRecordingDraft(['match_id' => $draft['id'], 'scoring_type' => 'SET', 'expected_revision' => 0, 'result_data' => ['sets' => [['home' => 1, 'away' => 0]]]]);
resolverReject(static fn() => AppService::updateMatch((string) $draft['id'], ['rule_version_id' => $nextDraft['id']]));
$started = resolverFixture('SCORE');
$extra = AppService::createMatch(['tournament_id' => $started['tournament_id'], 'sport_id' => $started['sport_id']]);
$nextStarted = AppService::handle('sportRules.save', ['sport_id' => $started['sport_id'], 'name' => 'Next scheduled version', 'scoring_type' => 'SET']);
AppService::updateMatchClock((string) $started['id'], ['clock_action' => 'start']);
// Simulate a legacy row whose status/clock were reset but whose period history remains.
$pdo->exec("UPDATE matches SET status = 'scheduled', clock_status = 'stopped' WHERE id = " . (int) $started['id']);
$updated = AppService::handle('tournaments.sportSetting', ['tournament_id' => $started['tournament_id'], 'sport_id' => $started['sport_id'], 'rule_version_id' => $nextStarted['id'], 'status' => 'ready']);
resolverCheck((int) $updated['migrated_matches'] === 1, 'Untouched migration did not exclude started period');
resolverCheck((int) AppService::matchCenter((string) $started['id'])['match']['rule_version_id'] === (int) $started['rule_version_id'], 'Started history rule changed');
resolverCheck((int) AppService::matchCenter((string) $extra['id'])['match']['rule_version_id'] === (int) $nextStarted['id'], 'Untouched match did not follow setting');
echo "PASS draft and historical-start evidence prevent reassignment; untouched scheduled migration retained\n";

$time = resolverFixture('TIME');
resolverReject(static fn() => AppService::importMatchEntries(['match_id' => $time['id'], 'scoring_type' => 'SCORE',
    'rows' => [['entry_type' => 'affiliation', 'department_code' => '130']]]));
$entry = AppService::saveMatchEntry(['match_id' => $time['id'], 'entry_type' => 'affiliation', 'department_code' => '130']);
AppService::updateMatchClock((string) $time['id'], ['clock_action' => 'start']);
resolverReject(static fn() => AppService::recordMatchEntryResult(['id' => $entry['id'], 'scoring_type' => 'SCORE', 'verified_time_ms' => 1000]));
echo "PASS entry import and timing writes reject forged types without partial rows\n";

// The next request must not repair missing rule/config by guessing from active rules.
$pdo->exec('UPDATE matches SET rule_version_id = NULL WHERE id = ' . (int) $badId);
$pdo->exec("UPDATE sport_rule_versions SET scoring_config = '' WHERE id = " . $ruleId);
$copy = tempnam(sys_get_temp_dir(), 'resolver-bootstrap-');
$pdo->exec("VACUUM INTO '" . str_replace("'", "''", $copy) . "'");
try {
    $env = getenv(); $env['PRSPORT_DB_DSN'] = 'sqlite:' . $copy;
    $code = 'require ' . var_export(dirname(__DIR__) . '/app/bootstrap.php', true) . '; echo json_encode([Database::pdo()->query("SELECT rule_version_id FROM matches WHERE id = ' . (int) $badId . '")->fetchColumn(), Database::pdo()->query("SELECT scoring_config FROM sport_rule_versions WHERE id = ' . $ruleId . '")->fetchColumn()]);';
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    resolverCheck(proc_close($process) === 0 && $errors === '' && json_decode($output, true) === [null, ''], 'Bootstrap silently repaired unresolved history');
} finally { unlink($copy); }
echo "PASS a separate bootstrap request preserves missing legacy rule/config for reviewed backfill\n";
Auth::logout();
resolverReject(static fn() => AppService::matchCenter($liveId));
echo "PASS guest authorization retained\n";
ob_end_flush();
