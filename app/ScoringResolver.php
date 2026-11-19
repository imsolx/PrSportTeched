<?php
declare(strict_types=1);

/** Resolves stored rule versions only; never infers a type from a sport or request. */
final class ScoringResolver
{
    public static function object($json, string $label): array
    {
        try {
            $value = json_decode((string) $json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException($label . ' ต้องเป็น JSON Object ที่ถูกต้อง');
        }
        if (!$value instanceof stdClass) {
            throw new InvalidArgumentException($label . ' ต้องเป็น JSON Object ที่ถูกต้อง');
        }
        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function resolve(array $match, array $rule, ?array $result = null): array
    {
        if (empty($match['rule_version_id']) || (string) $match['rule_version_id'] !== (string) ($rule['id'] ?? '')
            || empty($match['sport_id']) || (string) $match['sport_id'] !== (string) ($rule['sport_id'] ?? '')) {
            throw new InvalidArgumentException('ชุดกติกาไม่ตรงกับแมตช์หรือกีฬา กรุณาตรวจสอบการตั้งค่า');
        }
        $type = trim((string) ($rule['scoring_type'] ?? ''));
        if (!in_array($type, ScoringEngine::TYPES, true)) {
            throw new InvalidArgumentException('ไม่รองรับประเภทคะแนนที่ผูกกับแมตช์ กรุณาตรวจสอบการตั้งค่า');
        }
        $config = self::object($rule['scoring_config'] ?? null, 'Scoring Config');
        $parts = [];
        foreach (['scoring_system', 'match_format', 'period_format', 'overtime_rules', 'tie_break_rules', 'ranking_rules', 'custom_rules'] as $field) {
            $parts[$field] = self::object($rule[$field] ?? null, $field);
        }
        try {
            $events = json_decode((string) ($rule['event_types'] ?? ''), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Event Types ของกติกาไม่ถูกต้อง');
        }
        if (!is_array($events) || array_filter($events, static fn($event): bool => !$event instanceof stdClass)) {
            throw new InvalidArgumentException('Event Types ของกติกาไม่ถูกต้อง');
        }
        // Retain the existing effective defaults, but only after strict stored-data validation.
        if ($type === 'SCORE') {
            $config = array_replace([
                'allow_draw' => !empty($parts['match_format']['allow_draw']),
                'allow_extra_time' => !empty($parts['overtime_rules']['enabled']),
                'allow_penalty' => ($parts['tie_break_rules']['method'] ?? 'draw') === 'penalty',
                'include_in_standings' => true,
            ], $config);
        } elseif ($type === 'SET') {
            $config = array_replace(['best_of' => max(1, (int) ($parts['period_format']['count'] ?? 3)), 'include_in_standings' => true], $config);
        }
        self::validateConfig($type, $config);
        if ($result !== null) {
            if ((string) ($result['scoring_type'] ?? '') !== $type) {
                throw new InvalidArgumentException('ประเภทคะแนนของผลเดิมไม่ตรงกับกติกา ต้องตรวจสอบประวัติก่อนบันทึก');
            }
            $config = self::object($result['config_snapshot'] ?? null, 'Config Snapshot');
            self::validateConfig($type, $config);
        }
        return ['type' => $type, 'config' => $config, 'rule_version_id' => $rule['id'],
            'recorder_page' => ['SCORE' => 'score.php', 'SET' => 'set.php'][$type] ?? null];
    }

    private static function validateConfig(string $type, array $config): void
    {
        foreach (['best_of', 'periods', 'period_count', 'points_to_win_set', 'points_to_win', 'normal_set_points', 'final_set_points', 'max_points', 'target_score'] as $key) {
            if (!array_key_exists($key, $config)) continue;
            $value = $config[$key];
            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value != (int) $value
                || (int) $value < (in_array($key, ['best_of', 'periods', 'period_count'], true) ? 1 : 0)) {
                throw new InvalidArgumentException('Scoring Config ไม่ถูกต้อง: ' . $key);
            }
        }
        if ($type === 'SET' && isset($config['win_by']) && (!is_numeric($config['win_by']) || (float) $config['win_by'] != (int) $config['win_by'] || (int) $config['win_by'] < 1)) {
            throw new InvalidArgumentException('Scoring Config ไม่ถูกต้อง: win_by');
        }
        if (in_array($type, ['COUNT', 'COMBINED'], true) && isset($config['win_by']) && !in_array($config['win_by'], ['HIGHEST', 'LOWEST'], true)) {
            throw new InvalidArgumentException('Scoring Config ไม่ถูกต้อง: win_by');
        }
        foreach (['allow_draw', 'allow_extra_time', 'allow_penalty', 'allow_overtime', 'include_in_standings'] as $key) {
            if (array_key_exists($key, $config) && !in_array($config[$key], [true, false, 0, 1], true)) {
                throw new InvalidArgumentException('Scoring Config ไม่ถูกต้อง: ' . $key);
            }
        }
        foreach (['rank_points', 'quick_points'] as $key) {
            if (!array_key_exists($key, $config)) continue;
            if (!is_array($config[$key]) || array_values($config[$key]) !== $config[$key] || count($config[$key]) > 100
                || array_filter($config[$key], static fn($point): bool => !is_numeric($point) || (float) $point != (int) $point || (int) $point < 0)) {
                throw new InvalidArgumentException('Scoring Config ไม่ถูกต้อง: ' . $key);
            }
        }
        if (isset($config['aggregation']) && !in_array($config['aggregation'], ['SUM', 'AVERAGE', 'DROP_HIGHEST_LOWEST'], true)) {
            throw new InvalidArgumentException('Scoring Config ไม่ถูกต้อง: aggregation');
        }
    }
}
