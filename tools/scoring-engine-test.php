<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/ScoringEngine.php';
require dirname(__DIR__) . '/app/ScoringTypeDetector.php';

function expect(string $label, string $type, array $config, array $result, int $winner, string $score): void
{
    $resolved = ScoringEngine::resolve($type, $config, $result, ['home_id' => 10, 'away_id' => 20]);
    if ($resolved['summary']['winner_id'] !== $winner || $resolved['summary']['display_score'] !== $score) {
        throw new RuntimeException($label . ': ' . json_encode($resolved, JSON_UNESCAPED_UNICODE));
    }
    echo "PASS {$label}\n";
}

expect('score penalty', 'SCORE', ['allow_draw' => false, 'allow_penalty' => true], [
    'regular_score' => ['home' => 1, 'away' => 1],
    'penalty' => ['home' => 5, 'away' => 4],
], 10, '1 - 1');

expect('period score', 'PERIOD_SCORE', ['periods' => 4, 'allow_overtime' => true], [
    'periods' => [['home' => 20, 'away' => 18], ['home' => 22, 'away' => 25], ['home' => 24, 'away' => 20], ['home' => 18, 'away' => 20]],
], 10, '84 - 83');

expect('sets', 'SET', ['best_of' => 3, 'points_to_win_set' => 21, 'win_by' => 2, 'max_points' => 30], [
    'sets' => [['home' => 21, 'away' => 17], ['home' => 18, 'away' => 21], ['home' => 21, 'away' => 15]],
], 10, '2 - 1');

expect('ends', 'END_SCORE', ['target_score' => 13], [
    'ends' => [['home' => 4, 'away' => 0], ['home' => 0, 'away' => 3], ['home' => 9, 'away' => 0]],
], 10, '13 - 3');

expect('rounds', 'ROUND', ['target_score' => 13], [
    'rounds' => [['home' => 4, 'away' => 0], ['home' => 0, 'away' => 3], ['home' => 9, 'away' => 0]],
], 10, '13 - 3');
expect('judged', 'JUDGED', ['aggregation' => 'AVERAGE'], [
    'home' => ['scores' => [9, 8]], 'away' => ['scores' => [7, 8]],
], 10, '8.5 - 7.5');
expect('target', 'TARGET', ['aggregation' => 'SUM'], [
    'home' => ['scores' => [10, 9]], 'away' => ['scores' => [8, 9]],
], 10, '19 - 17');
expect('count', 'COUNT', ['win_by' => 'HIGHEST'], [
    'home' => ['value' => 12], 'away' => ['value' => 10],
], 10, '12 - 10');
expect('lowest', 'LOWEST', [], [
    'home' => ['values' => [4, 3]], 'away' => ['values' => [5, 4]],
], 10, '7 - 9');
expect('decision', 'DECISION', [], [
    'method' => 'IPPON', 'winner_id' => 10,
], 10, '0 - 0');
expect('combined', 'COMBINED', ['win_by' => 'HIGHEST'], [
    'events' => [['name' => 'รายการ 1', 'home' => 10, 'away' => 8], ['name' => 'รายการ 2', 'home' => 12, 'away' => 9]],
], 10, '22 - 17');

foreach ([
    ['ROUND', ['target_score' => 13], ['rounds' => [['home' => 3, 'away' => 1]]]],
    ['COUNT', [], ['home' => ['value' => -1], 'away' => ['value' => 2]]],
    ['DECISION', [], ['method' => 'IPPON']],
    ['COMBINED', [], ['events' => [['home' => -2, 'away' => 1]]]],
] as [$type, $config, $invalidResult]) {
    try {
        ScoringEngine::resolve($type, $config, $invalidResult, ['home_id' => 10, 'away_id' => 20]);
        throw new RuntimeException("Invalid {$type} result was accepted");
    } catch (InvalidArgumentException) {
        // Expected validation failure.
    }
}
echo "PASS invalid recording results rejected\n";

expect('time', 'TIME', [], ['home' => ['value' => 10523], 'away' => ['value' => 10712]], 10, '10.523 s - 10.712 s');
expect('distance', 'DISTANCE', [], ['home' => ['attempts' => [['value' => 6.21], ['value' => 6.45]]], 'away' => ['attempts' => [['value' => 6.31]]]], 10, '6.45 m - 6.31 m');
expect('height', 'HEIGHT', [], ['home' => ['attempts' => [['height' => 1.7, 'results' => ['O']], ['height' => 1.75, 'results' => ['X', 'O']]]], 'away' => ['attempts' => [['height' => 1.7, 'results' => ['X', 'O']], ['height' => 1.75, 'results' => ['X', 'X', 'O']]]]], 10, '1.75 m - 1.75 m');
expect('judge points', 'POINT_RANKING', ['aggregation' => 'AVERAGE'], ['home' => ['scores' => [8.5, 8.7, 8.2]], 'away' => ['scores' => [8.1, 8.4, 8.3]]], 10, '8.467 - 8.267');
expect('combat', 'COMBAT', [], ['method' => 'POINTS', 'rounds' => [['home' => 10, 'away' => 9], ['home' => 10, 'away' => 9]]], 10, '20 - 18');
expect('race', 'RACE_POSITION', [], ['home' => ['value' => 1], 'away' => ['value' => 2]], 10, '1 - 2');
expect('stroke', 'STROKE', [], ['home' => ['holes' => [['score' => 4], ['score' => 3]]], 'away' => ['holes' => [['score' => 5], ['score' => 4]]]], 10, '7 - 9');
expect('maps', 'MAP', ['best_of' => 3], ['maps' => [['winner_id' => 10], ['winner_id' => 20], ['winner_id' => 10]]], 10, '2 - 1');

if (ScoringTypeDetector::detect(['name' => 'วิ่ง 100 เมตร', 'icon' => 'running'], ['scoring_type' => 'SCORE', 'created_by' => 'migration']) !== 'RACE_POSITION') {
    throw new RuntimeException('Running sport was not auto-detected as RACE_POSITION');
}
foreach (['football' => 'ฟุตบอล', 'futsal' => 'ฟุตซอล'] as $icon => $name) {
    if (ScoringTypeDetector::detect(['name' => $name, 'icon' => $icon]) !== 'SCORE') {
        throw new RuntimeException("{$name} did not use the shared team-score mode");
    }
}
echo "PASS automatic scoring type detection\n";
