<?php
declare(strict_types=1);

final class ScoringEngine
{
    public const TYPES = [
        'SCORE', 'SET', 'ROUND', 'TIME', 'DISTANCE', 'HEIGHT', 'JUDGED',
        'TARGET', 'COUNT', 'LOWEST', 'DECISION', 'COMBINED',
        // Historical rule versions remain readable and editable.
        'PERIOD_SCORE', 'END_SCORE', 'POINT_RANKING', 'COMBAT', 'RACE_POSITION', 'STROKE', 'MAP',
    ];

    public static function resolve(string $type, array $config, array $result, array $participants): array
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('รูปแบบการคิดคะแนนไม่ถูกต้อง');
        }
        $homeId = self::participantId($participants['home_id'] ?? null);
        $awayId = self::participantId($participants['away_id'] ?? null);
        if ($homeId === null || $awayId === null || $homeId === $awayId) {
            throw new InvalidArgumentException('การแข่งขันต้องมีผู้แข่งขันสองฝั่งที่แตกต่างกัน');
        }

        $resolved = match ($type) {
            'SCORE' => self::score($config, $result, $homeId, $awayId),
            'PERIOD_SCORE' => self::periodScore($config, $result, $homeId, $awayId),
            'SET' => self::sets($config, $result, $homeId, $awayId),
            'ROUND' => self::roundScore($config, $result, $homeId, $awayId),
            'END_SCORE' => self::endScore($config, $result, $homeId, $awayId),
            'TIME' => self::metric($result, $homeId, $awayId, true, 'ms'),
            'DISTANCE' => self::attemptMetric($result, $homeId, $awayId, false, 'm'),
            'HEIGHT' => self::height($result, $homeId, $awayId),
            'JUDGED' => self::pointRanking($config, $result, $homeId, $awayId),
            'POINT_RANKING' => self::pointRanking($config, $result, $homeId, $awayId),
            'TARGET' => self::targetScore($config, $result, $homeId, $awayId),
            'COUNT' => self::countScore($config, $result, $homeId, $awayId),
            'LOWEST' => self::lowestScore($result, $homeId, $awayId),
            'DECISION' => self::decision($result, $homeId, $awayId),
            'COMBINED' => self::combined($config, $result, $homeId, $awayId),
            'COMBAT' => self::combat($config, $result, $homeId, $awayId),
            'RACE_POSITION' => self::metric($result, $homeId, $awayId, true, 'position'),
            'STROKE' => self::stroke($result, $homeId, $awayId),
            'MAP' => self::maps($config, $result, $homeId, $awayId),
        };

        return [
            'scoring_type' => $type,
            'result' => $resolved['result'],
            'summary' => [
                'display_score' => (string) $resolved['display_score'],
                'secondary_text' => (string) ($resolved['secondary_text'] ?? ''),
                'detail' => $resolved['detail'] ?? [],
                'home_score' => $resolved['home_score'] ?? null,
                'away_score' => $resolved['away_score'] ?? null,
                'winner_id' => $resolved['winner_id'] ?? null,
                'is_draw' => !empty($resolved['is_draw']),
                'status' => 'COMPLETED',
            ],
        ];
    }

    private static function score(array $config, array $result, int $homeId, int $awayId): array
    {
        $regular = self::pair($result['regular_score'] ?? $result, 'คะแนนเวลาปกติ');
        $extra = isset($result['extra_time']) ? self::pair($result['extra_time'], 'คะแนนต่อเวลา') : ['home' => 0, 'away' => 0];
        if (($extra['home'] > 0 || $extra['away'] > 0) && empty($config['allow_extra_time'])) {
            throw new InvalidArgumentException('กติกานี้ไม่อนุญาตให้บันทึกผลต่อเวลา');
        }
        $home = $regular['home'] + $extra['home'];
        $away = $regular['away'] + $extra['away'];
        $penalty = isset($result['penalty']) ? self::pair($result['penalty'], 'คะแนนยิงจุดโทษ') : null;
        if ($penalty !== null && empty($config['allow_penalty'])) {
            throw new InvalidArgumentException('กติกานี้ไม่อนุญาตการยิงจุดโทษ');
        }
        $allowDraw = !empty($config['allow_draw']);
        $winner = $home === $away ? null : ($home > $away ? $homeId : $awayId);
        $secondary = ($extra['home'] + $extra['away']) > 0 ? 'หลังต่อเวลา' : '';
        if ($home === $away && $penalty !== null) {
            if ($penalty['home'] === $penalty['away']) {
                throw new InvalidArgumentException('คะแนนยิงจุดโทษต้องมีผู้ชนะ');
            }
            $winner = $penalty['home'] > $penalty['away'] ? $homeId : $awayId;
            $secondary = sprintf('ชนะจุดโทษ %d-%d', $penalty['home'], $penalty['away']);
        } elseif ($home === $away && !$allowDraw) {
            throw new InvalidArgumentException('กติกานี้ไม่อนุญาตผลเสมอ กรุณาระบุผลต่อเวลาหรือจุดโทษ');
        }
        return self::resolved($result, $home, $away, $winner, $homeId, $awayId, $secondary);
    }

    private static function periodScore(array $config, array $result, int $homeId, int $awayId): array
    {
        $periods = self::rows($result['periods'] ?? [], 'ช่วงการแข่งขัน');
        $required = max(1, (int) ($config['periods'] ?? $config['period_count'] ?? 1));
        $regular = array_values(array_filter($periods, static fn(array $row): bool => strtoupper((string) ($row['type'] ?? 'REGULAR')) !== 'OVERTIME'));
        if (count($regular) < $required) {
            throw new InvalidArgumentException("กรุณากรอกคะแนนให้ครบ {$required} ช่วง");
        }
        [$home, $away] = self::sumRows($periods);
        if ($home === $away) {
            if (empty($config['allow_overtime'])) {
                throw new InvalidArgumentException('คะแนนรวมเสมอ แต่กติกาไม่อนุญาตให้จบการแข่งขันด้วยผลเสมอ');
            }
            throw new InvalidArgumentException('คะแนนรวมยังเสมอ กรุณาเพิ่มช่วงต่อเวลา');
        }
        return self::resolved($result, $home, $away, $home > $away ? $homeId : $awayId, $homeId, $awayId, '', self::rowDetails($periods));
    }

    private static function sets(array $config, array $result, int $homeId, int $awayId): array
    {
        $sets = self::rows($result['sets'] ?? [], 'เซต');
        $bestOf = max(1, (int) ($config['best_of'] ?? 3));
        $needed = intdiv($bestOf, 2) + 1;
        $homeSets = 0;
        $awaySets = 0;
        foreach ($sets as $index => $set) {
            if ($set['home'] === $set['away']) {
                throw new InvalidArgumentException('คะแนนในแต่ละเซตต้องมีผู้ชนะ');
            }
            self::validateSetScore($config, $set, $index + 1, $bestOf);
            $set['home'] > $set['away'] ? $homeSets++ : $awaySets++;
            if ($homeSets === $needed || $awaySets === $needed) {
                if ($index !== array_key_last($sets)) {
                    throw new InvalidArgumentException('มีการกรอกเซตหลังการแข่งขันได้ผู้ชนะแล้ว');
                }
            }
        }
        if ($homeSets < $needed && $awaySets < $needed) {
            throw new InvalidArgumentException("ยังไม่มีฝ่ายใดชนะครบ {$needed} เซต");
        }
        return self::resolved($result, $homeSets, $awaySets, $homeSets > $awaySets ? $homeId : $awayId, $homeId, $awayId, '', self::rowDetails($sets));
    }

    private static function validateSetScore(array $config, array $set, int $number, int $bestOf): void
    {
        $target = (int) ($config['points_to_win_set'] ?? $config['points_to_win'] ?? 0);
        if ($number === $bestOf && isset($config['final_set_points'])) {
            $target = (int) $config['final_set_points'];
        } elseif (isset($config['normal_set_points'])) {
            $target = (int) $config['normal_set_points'];
        }
        if ($target <= 0) {
            return;
        }
        $high = max($set['home'], $set['away']);
        $low = min($set['home'], $set['away']);
        $winBy = max(1, (int) ($config['win_by'] ?? 2));
        $max = (int) ($config['max_points'] ?? 0);
        $validAtCap = $max > 0 && $high === $max && $low === $max - 1;
        if ($high < $target || (!$validAtCap && $high - $low < $winBy)) {
            throw new InvalidArgumentException("คะแนนเซต {$number} ยังไม่เป็นไปตามเงื่อนไขการชนะ");
        }
    }

    private static function endScore(array $config, array $result, int $homeId, int $awayId): array
    {
        $ends = self::rows($result['ends'] ?? [], 'End');
        [$home, $away] = self::sumRows($ends);
        $target = max(1, (int) ($config['target_score'] ?? 13));
        if (max($home, $away) < $target || $home === $away) {
            throw new InvalidArgumentException("การแข่งขันต้องมีฝ่ายใดฝ่ายหนึ่งถึง {$target} คะแนน");
        }
        return self::resolved($result, $home, $away, $home > $away ? $homeId : $awayId, $homeId, $awayId, '', self::rowDetails($ends));
    }

    private static function roundScore(array $config, array $result, int $homeId, int $awayId): array
    {
        $rounds = self::rows($result['rounds'] ?? [], 'รอบ');
        [$home, $away] = self::sumRows($rounds);
        $target = max(0, (int) ($config['target_score'] ?? 0));
        if ($target > 0 && max($home, $away) < $target) {
            throw new InvalidArgumentException("ยังไม่มีฝ่ายใดถึง {$target} คะแนน");
        }
        if ($home === $away) throw new InvalidArgumentException('คะแนนรวมแต่ละรอบต้องมีผู้ชนะ');
        return self::resolved($result, $home, $away, $home > $away ? $homeId : $awayId, $homeId, $awayId, '', self::rowDetails($rounds));
    }

    private static function targetScore(array $config, array $result, int $homeId, int $awayId): array
    {
        return self::pointRanking(['aggregation' => $config['aggregation'] ?? 'SUM'], $result, $homeId, $awayId);
    }

    private static function countScore(array $config, array $result, int $homeId, int $awayId): array
    {
        $home = self::nonNegativeInt($result['home']['value'] ?? $result['home'] ?? null, 'จำนวนฝั่งที่ 1');
        $away = self::nonNegativeInt($result['away']['value'] ?? $result['away'] ?? null, 'จำนวนฝั่งที่ 2');
        $lowestWins = strtoupper((string) ($config['win_by'] ?? 'HIGHEST')) === 'LOWEST';
        $winner = $home === $away ? null : (($lowestWins ? $home < $away : $home > $away) ? $homeId : $awayId);
        return self::resolved($result, $home, $away, $winner, $homeId, $awayId);
    }

    private static function lowestScore(array $result, int $homeId, int $awayId): array
    {
        $home = self::sumNumericValues($result['home']['values'] ?? [], 'ฝั่งที่ 1');
        $away = self::sumNumericValues($result['away']['values'] ?? [], 'ฝั่งที่ 2');
        return self::numericResolved($result, $home, $away, true, $homeId, $awayId, 'ผลรวมน้อยที่สุดชนะ');
    }

    private static function decision(array $result, int $homeId, int $awayId): array
    {
        $method = strtoupper(trim((string) ($result['method'] ?? 'DECISION')));
        if (!in_array($method, ['DECISION', 'POINTS', 'KO', 'TKO', 'SUBMISSION', 'IPPON', 'DQ', 'WALKOVER'], true)) {
            throw new InvalidArgumentException('วิธีตัดสินไม่ถูกต้อง');
        }
        $rounds = empty($result['rounds']) ? [] : self::rows($result['rounds'], 'ยก');
        [$home, $away] = self::sumRows($rounds);
        $winner = self::participantId($result['winner_id'] ?? null);
        if ($method === 'POINTS' && $rounds && $home !== $away) $winner = $home > $away ? $homeId : $awayId;
        if (!in_array($winner, [$homeId, $awayId], true)) throw new InvalidArgumentException('กรุณาระบุผู้ชนะตามเงื่อนไขการตัดสิน');
        return self::resolved($result, $home, $away, $winner, $homeId, $awayId, $method, self::rowDetails($rounds));
    }

    private static function combined(array $config, array $result, int $homeId, int $awayId): array
    {
        $events = $result['events'] ?? null;
        if (!is_array($events) || !$events) throw new InvalidArgumentException('กรุณาระบุรายการย่อย');
        $home = $away = 0.0;
        foreach ($events as $event) {
            if (!is_array($event) || !is_numeric($event['home'] ?? null) || !is_numeric($event['away'] ?? null)
                || (float) $event['home'] < 0 || (float) $event['away'] < 0) {
                throw new InvalidArgumentException('คะแนนรายการย่อยต้องเป็นตัวเลขไม่ติดลบ');
            }
            $weight = isset($event['weight']) && is_numeric($event['weight']) ? (float) $event['weight'] : 1.0;
            if ($weight <= 0) throw new InvalidArgumentException('น้ำหนักรายการย่อยต้องมากกว่า 0');
            $home += $event['home'] * $weight;
            $away += $event['away'] * $weight;
        }
        $lowestWins = strtoupper((string) ($config['win_by'] ?? 'HIGHEST')) === 'LOWEST';
        return self::numericResolved($result, $home, $away, $lowestWins, $homeId, $awayId, 'ผลรวมหลายรายการ');
    }

    private static function sumNumericValues(mixed $values, string $label): float
    {
        if (!is_array($values) || !$values) throw new InvalidArgumentException("กรุณาระบุผลของ{$label}");
        $total = 0.0;
        foreach ($values as $value) {
            if (!is_numeric($value) || (float) $value < 0) throw new InvalidArgumentException("ผลของ{$label}ต้องไม่ติดลบ");
            $total += (float) $value;
        }
        return $total;
    }

    private static function numericResolved(array $result, float $home, float $away, bool $lowestWins, int $homeId, int $awayId, string $secondary): array
    {
        $winner = $home === $away ? null : (($lowestWins ? $home < $away : $home > $away) ? $homeId : $awayId);
        return [
            'result' => $result,
            'display_score' => self::formatNumber($home) . ' - ' . self::formatNumber($away),
            'secondary_text' => $secondary, 'detail' => [],
            'home_score' => (int) round($home * 1000), 'away_score' => (int) round($away * 1000),
            'winner_id' => $winner, 'is_draw' => $winner === null,
        ];
    }

    private static function metric(array $result, int $homeId, int $awayId, bool $lowerWins, string $unit): array
    {
        $home = self::metricValue($result['home'] ?? $result['home_value'] ?? null, 'ฝั่งที่ 1');
        $away = self::metricValue($result['away'] ?? $result['away_value'] ?? null, 'ฝั่งที่ 2');
        $winner = $home === $away ? null : (($lowerWins ? $home < $away : $home > $away) ? $homeId : $awayId);
        $display = $unit === 'ms'
            ? self::formatMilliseconds((int) $home) . ' - ' . self::formatMilliseconds((int) $away)
            : self::formatNumber($home) . ' - ' . self::formatNumber($away);
        return [
            'result' => $result, 'display_score' => $display, 'secondary_text' => '', 'detail' => [],
            'home_score' => (int) round($home), 'away_score' => (int) round($away), 'winner_id' => $winner,
            'is_draw' => $winner === null,
        ];
    }

    private static function attemptMetric(array $result, int $homeId, int $awayId, bool $lowerWins, string $unit): array
    {
        $home = self::bestAttempt($result['home']['attempts'] ?? [] , $lowerWins, 'ฝั่งที่ 1');
        $away = self::bestAttempt($result['away']['attempts'] ?? [] , $lowerWins, 'ฝั่งที่ 2');
        $winner = $home === $away ? null : (($lowerWins ? $home < $away : $home > $away) ? $homeId : $awayId);
        return [
            'result' => $result,
            'display_score' => self::formatNumber($home) . " {$unit} - " . self::formatNumber($away) . " {$unit}",
            'secondary_text' => 'ผลงานที่ดีที่สุด', 'detail' => [],
            'home_score' => (int) round($home * 1000), 'away_score' => (int) round($away * 1000),
            'winner_id' => $winner, 'is_draw' => $winner === null,
        ];
    }

    private static function height(array $result, int $homeId, int $awayId): array
    {
        $home = self::heightResult($result['home']['attempts'] ?? [], 'ฝั่งที่ 1');
        $away = self::heightResult($result['away']['attempts'] ?? [], 'ฝั่งที่ 2');
        $winner = null;
        if ($home['height'] !== $away['height']) {
            $winner = $home['height'] > $away['height'] ? $homeId : $awayId;
        } elseif ($home['failures'] !== $away['failures']) {
            $winner = $home['failures'] < $away['failures'] ? $homeId : $awayId;
        }
        return [
            'result' => $result,
            'display_score' => self::formatNumber($home['height']) . ' m - ' . self::formatNumber($away['height']) . ' m',
            'secondary_text' => $winner === null ? 'อันดับร่วม' : 'ตัดสินด้วยจำนวนครั้งที่ไม่ผ่าน', 'detail' => [],
            'home_score' => (int) round($home['height'] * 1000), 'away_score' => (int) round($away['height'] * 1000),
            'winner_id' => $winner, 'is_draw' => $winner === null,
        ];
    }

    private static function pointRanking(array $config, array $result, int $homeId, int $awayId): array
    {
        $method = strtoupper((string) ($config['aggregation'] ?? 'SUM'));
        $home = self::judgeTotal($result['home']['scores'] ?? [], $method, 'ฝั่งที่ 1');
        $away = self::judgeTotal($result['away']['scores'] ?? [], $method, 'ฝั่งที่ 2');
        $winner = $home === $away ? null : ($home > $away ? $homeId : $awayId);
        return [
            'result' => $result, 'display_score' => self::formatNumber($home) . ' - ' . self::formatNumber($away),
            'secondary_text' => $method, 'detail' => [],
            'home_score' => (int) round($home * 1000), 'away_score' => (int) round($away * 1000),
            'winner_id' => $winner, 'is_draw' => $winner === null,
        ];
    }

    private static function combat(array $config, array $result, int $homeId, int $awayId): array
    {
        $method = strtoupper(trim((string) ($result['method'] ?? 'POINTS')));
        $allowed = ['POINTS', 'KO', 'TKO', 'SUBMISSION', 'DQ', 'WALKOVER'];
        if (!in_array($method, $allowed, true)) {
            throw new InvalidArgumentException('วิธีตัดสินกีฬาต่อสู้ไม่ถูกต้อง');
        }
        $rounds = self::rows($result['rounds'] ?? [], 'ยก');
        [$home, $away] = self::sumRows($rounds);
        $winner = self::participantId($result['winner_id'] ?? null);
        if ($method === 'POINTS') {
            if (!$rounds || $home === $away) {
                throw new InvalidArgumentException('ผลคะแนนต้องมียกและมีผู้ชนะ');
            }
            $winner = $home > $away ? $homeId : $awayId;
        } elseif (!in_array($winner, [$homeId, $awayId], true)) {
            throw new InvalidArgumentException('กรุณาระบุผู้ชนะสำหรับวิธีตัดสินนี้');
        }
        return self::resolved($result, $home, $away, $winner, $homeId, $awayId, $method, self::rowDetails($rounds));
    }

    private static function stroke(array $result, int $homeId, int $awayId): array
    {
        $home = self::sumValues($result['home']['holes'] ?? [], 'score', 'ฝั่งที่ 1');
        $away = self::sumValues($result['away']['holes'] ?? [], 'score', 'ฝั่งที่ 2');
        $winner = $home === $away ? null : ($home < $away ? $homeId : $awayId);
        return self::resolved($result, $home, $away, $winner, $homeId, $awayId, 'จำนวนสโตรกน้อยกว่าเป็นผู้ชนะ');
    }

    private static function maps(array $config, array $result, int $homeId, int $awayId): array
    {
        $maps = is_array($result['maps'] ?? null) ? array_values($result['maps']) : [];
        if (!$maps) {
            throw new InvalidArgumentException('กรุณาระบุผลอย่างน้อยหนึ่ง Map');
        }
        $home = 0;
        $away = 0;
        foreach ($maps as $map) {
            $winner = self::participantId(is_array($map) ? ($map['winner_id'] ?? null) : null);
            if ($winner === $homeId) $home++;
            elseif ($winner === $awayId) $away++;
            else throw new InvalidArgumentException('ผู้ชนะ Map ไม่ตรงกับผู้แข่งขัน');
        }
        $bestOf = max(1, (int) ($config['best_of'] ?? count($maps)));
        $needed = intdiv($bestOf, 2) + 1;
        if (max($home, $away) < $needed) {
            throw new InvalidArgumentException("ยังไม่มีฝ่ายใดชนะครบ {$needed} Map");
        }
        return self::resolved($result, $home, $away, $home > $away ? $homeId : $awayId, $homeId, $awayId, '', array_map(static fn(array $row, int $i): string => 'Map ' . ($i + 1) . ': ' . $row['winner_id'], $maps, array_keys($maps)));
    }

    private static function resolved(array $result, int $home, int $away, ?int $winner, int $homeId, int $awayId, string $secondary = '', array $detail = []): array
    {
        return [
            'result' => $result, 'display_score' => "{$home} - {$away}", 'secondary_text' => $secondary,
            'detail' => $detail, 'home_score' => $home, 'away_score' => $away,
            'winner_id' => $winner, 'is_draw' => $winner === null,
        ];
    }

    private static function pair(mixed $value, string $label): array
    {
        if (!is_array($value)) throw new InvalidArgumentException("{$label}ไม่ถูกต้อง");
        $home = self::nonNegativeInt($value['home'] ?? $value['home_score'] ?? null, $label . 'ฝั่งที่ 1');
        $away = self::nonNegativeInt($value['away'] ?? $value['away_score'] ?? null, $label . 'ฝั่งที่ 2');
        return ['home' => $home, 'away' => $away];
    }

    private static function rows(mixed $rows, string $label): array
    {
        if (!is_array($rows) || !$rows) throw new InvalidArgumentException("กรุณาระบุ{$label}");
        return array_map(static function ($row) use ($label): array {
            if (!is_array($row)) throw new InvalidArgumentException("ข้อมูล{$label}ไม่ถูกต้อง");
            return array_replace($row, self::pair($row, $label));
        }, array_values($rows));
    }

    private static function sumRows(array $rows): array
    {
        $home = $away = 0;
        foreach ($rows as $row) { $home += $row['home']; $away += $row['away']; }
        return [$home, $away];
    }

    private static function rowDetails(array $rows): array
    {
        return array_map(static fn(array $row): string => $row['home'] . '-' . $row['away'], $rows);
    }

    private static function metricValue(mixed $value, string $label): float
    {
        if (is_array($value)) {
            $status = strtoupper((string) ($value['status'] ?? 'VALID'));
            if ($status !== 'VALID') throw new InvalidArgumentException("{$label}ไม่มีผลที่ใช้จัดอันดับได้ ({$status})");
            $value = $value['value'] ?? $value['value_ms'] ?? null;
        }
        if (!is_numeric($value) || (float) $value <= 0) throw new InvalidArgumentException("{$label}ต้องมากกว่า 0");
        return (float) $value;
    }

    private static function bestAttempt(mixed $attempts, bool $lowerWins, string $label): float
    {
        if (!is_array($attempts)) throw new InvalidArgumentException("Attempt ของ{$label}ไม่ถูกต้อง");
        $valid = [];
        foreach ($attempts as $attempt) {
            if (is_array($attempt) && strtoupper((string) ($attempt['status'] ?? 'VALID')) === 'VALID' && is_numeric($attempt['value'] ?? null) && (float) $attempt['value'] > 0) {
                $valid[] = (float) $attempt['value'];
            }
        }
        if (!$valid) throw new InvalidArgumentException("{$label}ไม่มี Attempt ที่ใช้ได้");
        return $lowerWins ? min($valid) : max($valid);
    }

    private static function heightResult(mixed $attempts, string $label): array
    {
        if (!is_array($attempts)) throw new InvalidArgumentException("ผลความสูงของ{$label}ไม่ถูกต้อง");
        $best = 0.0; $failures = 0;
        foreach ($attempts as $attempt) {
            if (!is_array($attempt) || !is_numeric($attempt['height'] ?? null) || !is_array($attempt['results'] ?? null)) continue;
            $marks = array_map('strtoupper', array_map('strval', $attempt['results']));
            if (in_array('O', $marks, true)) {
                $best = max($best, (float) $attempt['height']);
                $failures += count(array_filter($marks, static fn(string $mark): bool => $mark === 'X'));
            }
        }
        if ($best <= 0) throw new InvalidArgumentException("{$label}ไม่มีระดับความสูงที่ผ่าน");
        return ['height' => $best, 'failures' => $failures];
    }

    private static function judgeTotal(mixed $scores, string $method, string $label): float
    {
        if (!is_array($scores)) throw new InvalidArgumentException("คะแนนกรรมการของ{$label}ไม่ถูกต้อง");
        $values = array_values(array_filter(array_map(static fn($score): mixed => is_array($score) ? ($score['score'] ?? null) : $score, $scores), 'is_numeric'));
        $values = array_map('floatval', $values);
        if (!$values) throw new InvalidArgumentException("{$label}ไม่มีคะแนนกรรมการ");
        if ($method === 'AVERAGE') return array_sum($values) / count($values);
        if ($method === 'DROP_HIGHEST_LOWEST') {
            if (count($values) < 3) throw new InvalidArgumentException('การตัดคะแนนสูงสุดและต่ำสุดต้องมีกรรมการอย่างน้อย 3 คน');
            sort($values); array_shift($values); array_pop($values);
        }
        return array_sum($values);
    }

    private static function sumValues(mixed $rows, string $field, string $label): int
    {
        if (!is_array($rows) || !$rows) throw new InvalidArgumentException("กรุณาระบุผลของ{$label}");
        $total = 0;
        foreach ($rows as $row) $total += self::nonNegativeInt(is_array($row) ? ($row[$field] ?? null) : null, $label);
        return $total;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) throw new InvalidArgumentException("{$label}ต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป");
        return (int) $value;
    }

    private static function participantId(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0 ? (int) $value : null;
    }

    private static function formatMilliseconds(int $value): string
    {
        return number_format($value / 1000, 3, '.', '') . ' s';
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
