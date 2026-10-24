<?php
declare(strict_types=1);

if (!str_starts_with((string) getenv('PRSPORT_DB_DSN'), 'sqlite:')) {
    throw new RuntimeException('Schema test requires an isolated SQLite database');
}

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::pdo();
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['match_results', 'match_result_revisions', 'match_official_assignments', 'match_entries',
    'match_entry_revisions', 'match_event_requests'] as $table) {
    if (!in_array($table, $tables, true)) {
        throw new RuntimeException("Missing table {$table}");
    }
}

$matchColumns = array_column($pdo->query('PRAGMA table_info(matches)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('action_revision', $matchColumns, true)) throw new RuntimeException('Missing matches.action_revision');
$entryColumns = array_column($pdo->query('PRAGMA table_info(match_entries)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('revision_number', $entryColumns, true)) throw new RuntimeException('Missing match_entries.revision_number');

$columns = $pdo->query('PRAGMA table_info(sport_rule_versions)')->fetchAll(PDO::FETCH_ASSOC);
$columnNames = array_column($columns, 'name');
foreach (['scoring_type', 'scoring_config'] as $column) {
    if (!in_array($column, $columnNames, true)) {
        throw new RuntimeException("Missing column sport_rule_versions.{$column}");
    }
}

echo "PASS portable database schema\n";
