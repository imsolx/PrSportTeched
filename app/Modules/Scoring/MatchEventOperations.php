<?php
declare(strict_types=1);

/** Events, corrections, idempotency and score synchronization; composed only into ScoringService. */
trait MatchEventOperations
{
    public static function saveMatchEvent(array $payload): array
    {
        Auth::canManageResults();
        $matchId = trim((string) ($payload['match_id'] ?? ''));
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        $requestId = trim((string) ($payload['request_id'] ?? ''));
        if ($requestId !== '' && !preg_match('/^[A-Za-z0-9_-]{8,100}$/', $requestId)) {
            throw new InvalidArgumentException('รหัสคำขอบันทึกเหตุการณ์ไม่ถูกต้อง');
        }
        if ($requestId !== '') {
            $previous = self::fetchOne('SELECT match_id, event_id FROM match_event_requests WHERE request_id = :id', [':id' => $requestId]);
            if ($previous) {
                if ((string) $previous['match_id'] !== $matchId) throw new RuntimeException('รหัสคำขอถูกใช้กับแมตช์อื่นแล้ว');
                return self::findById('match_events', (string) $previous['event_id']);
            }
        }
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        if ((string) ($match['status'] ?? '') !== 'live') {
            throw new RuntimeException('ต้องเริ่มการแข่งขันก่อนจึงจะบันทึกคะแนนหรือเหตุการณ์ได้');
        }
        $rule = self::matchScoringContext($match)['rule'];
        $eventType = trim((string) ($payload['event_type'] ?? ''));
        $definition = null;
        foreach ($rule['event_types_data'] as $candidate) {
            if ((string) ($candidate['code'] ?? '') === $eventType) {
                $definition = $candidate;
                break;
            }
        }
        if (!$definition) {
            throw new InvalidArgumentException('เหตุการณ์นี้ไม่ได้เปิดใช้ในกติกาของกีฬา');
        }
        $quickTeamScore = filter_var($payload['quick_team_score'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $teamPointScoringTypes = ['SCORE', 'PERIOD_SCORE'];
        if ($quickTeamScore && (!in_array((string) ($rule['scoring_type'] ?? ''), $teamPointScoringTypes, true) || (int) ($definition['points'] ?? 0) <= 0)) {
            throw new InvalidArgumentException('กีฬาประเภทนี้ไม่รองรับปุ่มเพิ่มคะแนนทีม');
        }
        $quickAmounts = (array) ($rule['scoring_config_data']['quick_points'] ?? ((string) ($rule['scoring_type'] ?? '') === 'PERIOD_SCORE' ? [1, 2, 3] : [1]));
        $quickAmounts = array_values(array_unique(array_map('intval', $quickAmounts)));
        if ($quickTeamScore && (!in_array((int) ($payload['points'] ?? 1), $quickAmounts, true)
            || count($quickAmounts) > 10 || array_filter($quickAmounts, static fn(int $amount): bool => $amount < 1 || $amount > 10))) {
            throw new InvalidArgumentException('จำนวนแต้มจากปุ่มไม่ตรงกับกติกาของแมตช์');
        }
        $teamId = self::nullableId($payload['team_id'] ?? null);
        $participant = (string) ($definition['participant'] ?? 'optional');
        $points = $quickTeamScore ? (int) ($payload['points'] ?? 1) : (array_key_exists('points', $payload) && $payload['points'] !== '' ? (int) $payload['points'] : (int) ($definition['points'] ?? 0));
        if (($participant === 'athlete' || $participant === 'team' || $points !== 0) && $teamId === null) {
            throw new InvalidArgumentException('กรุณาเลือกทีมของเหตุการณ์');
        }
        if ($teamId !== null && !in_array((string) $teamId, [(string) $match['team1_id'], (string) $match['team2_id']], true)) {
            throw new InvalidArgumentException('ทีมไม่อยู่ในแมตช์นี้');
        }
        $studentId = self::nullableId($payload['student_id'] ?? null);
        if ($participant === 'athlete' && $studentId === null && !$quickTeamScore) {
            throw new InvalidArgumentException('กรุณาเลือกนักกีฬาผู้ทำเหตุการณ์');
        }
        if ($studentId !== null && $teamId === null) {
            throw new InvalidArgumentException('กรุณาเลือกทีมก่อนเลือกนักกีฬา');
        }
        if ($studentId !== null && $teamId !== null && !self::fetchOne('SELECT id FROM team_members WHERE team_id = :team_id AND student_id = :student_id', [':team_id' => $teamId, ':student_id' => $studentId])) {
            throw new InvalidArgumentException('นักกีฬาไม่อยู่ในทีมที่เลือก');
        }
        $metadata = self::payloadObject($payload['metadata'] ?? []);
        if ($quickTeamScore) {
            $metadata['quick_team_score'] = true;
        }
        $record = [
            'id' => self::nextId('match_events'),
            'match_id' => $matchId,
            'event_type' => $eventType,
            'team_id' => $teamId,
            'student_id' => $studentId,
            'period_number' => max(1, (int) ($payload['period_number'] ?? $match['current_period'] ?? 1)),
            'elapsed_seconds' => max(0, (int) ($payload['elapsed_seconds'] ?? $match['clock_seconds'] ?? 0)),
            'added_time' => max(0, (int) ($payload['added_time'] ?? 0)),
            'points' => $points,
            'card_type' => str_contains($eventType, 'card') ? str_replace('_card', '', $eventType) : null,
            'note' => trim((string) ($payload['note'] ?? '')),
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'is_voided' => 0,
            'created_by' => Auth::currentUser()['email'] ?? '',
            'created_at' => Database::now(),
        ];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload ?? []);
            self::claimMatchActionRevision($matchId, (int) ($payload['expected_revision'] ?? $match['action_revision'] ?? 0));
            $row = self::insertRow('match_events', $record);
            if ($requestId !== '') {
                $stmt = $pdo->prepare('INSERT INTO match_event_requests (request_id, match_id, event_id, created_at)
                    VALUES (:request_id, :match_id, :event_id, :created_at)');
                $stmt->execute([':request_id' => $requestId, ':match_id' => $matchId, ':event_id' => $row['id'], ':created_at' => Database::now()]);
            }
            self::syncMatchScoreFromEvents($matchId);
            self::audit('create_event', 'match', $matchId, $row);
            $pdo->commit();
            return $row;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($requestId !== '') {
                $previous = self::fetchOne('SELECT match_id, event_id FROM match_event_requests WHERE request_id = :id', [':id' => $requestId]);
                if ($previous && (string) $previous['match_id'] === $matchId) return self::findById('match_events', (string) $previous['event_id']);
            }
            throw $error;
        }
    }

    public static function updateMatchEvent(string $id, array $payload): array
    {
        Auth::canManageResults();
        $event = self::findById('match_events', $id);
        $match = self::findById('matches', (string) $event['match_id']);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        if ((string) ($match['status'] ?? '') !== 'live') {
            throw new RuntimeException('แก้ไขเหตุการณ์ได้เฉพาะแมตช์ที่กำลังแข่งขัน');
        }
        if ((int) ($event['is_voided'] ?? 0) === 1) {
            throw new RuntimeException('เหตุการณ์นี้ถูกยกเลิกแล้ว ไม่สามารถแก้ไขได้');
        }
        $metadata = self::jsonObject((string) ($event['metadata_json'] ?? '{}'));
        if (!empty($metadata['score_correction'])) {
            throw new RuntimeException('รายการแก้คะแนนต้องจัดการผ่านเมนูแก้ไขคะแนนรวม');
        }
        $reason = trim((string) ($payload['edit_reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('กรุณาระบุเหตุผลในการแก้ไขเหตุการณ์');
        }
        $rule = self::matchScoringContext($match)['rule'];
        $eventType = trim((string) ($payload['event_type'] ?? $event['event_type']));
        $definition = null;
        foreach ($rule['event_types_data'] as $candidate) {
            if ((string) ($candidate['code'] ?? '') === $eventType) {
                $definition = $candidate;
                break;
            }
        }
        if (!$definition) throw new InvalidArgumentException('เหตุการณ์นี้ไม่ได้เปิดใช้ในกติกาของกีฬา');

        $teamId = self::nullableId($payload['team_id'] ?? $event['team_id']);
        $studentId = self::nullableId($payload['student_id'] ?? $event['student_id']);
        $participant = (string) ($definition['participant'] ?? 'optional');
        $points = array_key_exists('points', $payload) && $payload['points'] !== ''
            ? (int) $payload['points'] : (int) ($definition['points'] ?? 0);
        if (($participant === 'athlete' || $participant === 'team' || $points !== 0) && $teamId === null) {
            throw new InvalidArgumentException('กรุณาเลือกทีมของเหตุการณ์');
        }
        if ($teamId !== null && !in_array((string) $teamId, [(string) $match['team1_id'], (string) $match['team2_id']], true)) {
            throw new InvalidArgumentException('ทีมไม่อยู่ในแมตช์นี้');
        }
        if ($participant === 'athlete' && $studentId === null && empty($metadata['quick_team_score'])) {
            throw new InvalidArgumentException('กรุณาเลือกนักกีฬาของเหตุการณ์');
        }
        if ($studentId !== null && $teamId === null) throw new InvalidArgumentException('กรุณาเลือกทีมก่อนเลือกนักกีฬา');
        if ($studentId !== null && $teamId !== null && !self::fetchOne(
            'SELECT id FROM team_members WHERE team_id = :team_id AND student_id = :student_id',
            [':team_id' => $teamId, ':student_id' => $studentId]
        )) throw new InvalidArgumentException('นักกีฬาไม่อยู่ในทีมที่เลือก');

        $metadata['edited'] = true;
        $metadata['last_edit_reason'] = $reason;
        $metadata['last_edited_by'] = Auth::currentUser()['email'] ?? '';
        $metadata['last_edited_at'] = Database::now();
        $patch = [
            'event_type' => $eventType,
            'team_id' => $teamId,
            'student_id' => $studentId,
            'period_number' => max(1, (int) ($payload['period_number'] ?? $event['period_number'] ?? 1)),
            'elapsed_seconds' => max(0, (int) ($payload['elapsed_seconds'] ?? $event['elapsed_seconds'] ?? 0)),
            'added_time' => max(0, (int) ($payload['added_time'] ?? $event['added_time'] ?? 0)),
            'points' => $points,
            'card_type' => str_contains($eventType, 'card') ? str_replace('_card', '', $eventType) : null,
            'note' => trim((string) ($payload['note'] ?? '')),
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload ?? []);
            self::claimMatchActionRevision((string) $event['match_id'], (int) ($payload['expected_revision'] ?? $match['action_revision'] ?? 0));
            $row = self::updateRow('match_events', $id, $patch);
            self::syncMatchScoreFromEvents((string) $event['match_id']);
            self::audit('update_event', 'match', (string) $event['match_id'], [
                'event_id' => $id, 'before' => $event, 'after' => $row, 'reason' => $reason,
            ]);
            $pdo->commit();
            return $row;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function correctMatchTeamScore(array $payload): array
    {
        Auth::canManageResults();
        $matchId = trim((string) ($payload['match_id'] ?? ''));
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        if ((string) ($match['status'] ?? '') !== 'live') throw new RuntimeException('แก้คะแนนได้เฉพาะแมตช์ที่กำลังแข่งขัน');
        $rule = self::matchScoringContext($match)['rule'];
        if (!in_array((string) ($rule['scoring_type'] ?? ''), ['SCORE', 'PERIOD_SCORE'], true)) {
            throw new InvalidArgumentException('รูปแบบกีฬานี้ไม่รองรับการแก้คะแนนรวมด้วยวิธีนี้');
        }
        $teamId = trim((string) ($payload['team_id'] ?? ''));
        $side = $teamId === (string) $match['team1_id'] ? 1 : ($teamId === (string) $match['team2_id'] ? 2 : 0);
        if ($side === 0) throw new InvalidArgumentException('ทีมไม่อยู่ในแมตช์นี้');
        $target = self::nullableInt($payload['target_score'] ?? null);
        if ($target === null || $target < 0 || $target > 9999) throw new InvalidArgumentException('คะแนนใหม่ต้องอยู่ระหว่าง 0 ถึง 9999');
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') throw new InvalidArgumentException('กรุณาระบุเหตุผลในการแก้คะแนน');
        $current = (int) ($match['team' . $side . '_score'] ?? 0);
        $eventTotal = (int) (self::fetchOne('SELECT COALESCE(SUM(points), 0) AS total FROM match_events
            WHERE match_id = :match AND team_id = :team AND is_voided = 0', [':match' => $matchId, ':team' => $teamId])['total'] ?? 0);
        if ($eventTotal !== $current) {
            throw new RuntimeException('คะแนนปัจจุบันไม่ได้มาจากเหตุการณ์ในศูนย์แข่งขัน กรุณาตรวจสอบผลกับผู้ดูแลก่อนแก้');
        }
        $delta = $target - $current;
        if ($delta === 0) return $match;
        $definition = null;
        foreach ($rule['event_types_data'] as $candidate) {
            if ((int) ($candidate['points'] ?? 0) > 0) { $definition = $candidate; break; }
        }
        if (!$definition) throw new InvalidArgumentException('กติกาไม่มีเหตุการณ์ทำคะแนน');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload ?? []);
            self::claimMatchActionRevision($matchId, (int) ($payload['expected_revision'] ?? $match['action_revision'] ?? 0));
            $row = self::insertRow('match_events', [
                'id' => self::nextId('match_events'), 'match_id' => $matchId,
                'event_type' => $definition['code'], 'team_id' => $teamId, 'student_id' => null,
                'period_number' => (int) ($match['current_period'] ?? 1), 'elapsed_seconds' => (int) ($match['clock_seconds'] ?? 0),
                'added_time' => 0, 'points' => $delta, 'card_type' => null, 'note' => $reason,
                'metadata_json' => json_encode(['score_correction' => true, 'from' => $current, 'to' => $target], JSON_UNESCAPED_UNICODE),
                'is_voided' => 0, 'created_by' => Auth::currentUser()['email'] ?? '', 'created_at' => Database::now(),
            ]);
            self::syncMatchScoreFromEvents($matchId);
            self::audit('correct_score', 'match', $matchId, ['event_id' => $row['id'], 'team_id' => $teamId,
                'from' => $current, 'to' => $target, 'reason' => $reason]);
            $pdo->commit();
            return self::findById('matches', $matchId);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function voidMatchEvent(string $id, array $payload = []): array
    {
        Auth::canManageResults();
        $event = self::findById('match_events', $id);
        $match = self::findById('matches', (string) $event['match_id']);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        if ((string) ($match['status'] ?? '') !== 'live') {
            throw new RuntimeException('ไม่สามารถแก้เหตุการณ์ของแมตช์ที่ล็อกผลแล้ว');
        }
        if ((int) ($event['is_voided'] ?? 0) === 1) {
            return $event;
        }
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload ?? []);
            self::claimMatchActionRevision((string) $event['match_id'], (int) ($payload['expected_revision'] ?? $match['action_revision'] ?? 0));
            $row = self::updateRow('match_events', $id, [
                'is_voided' => 1,
                'voided_by' => Auth::currentUser()['email'] ?? '',
                'voided_at' => Database::now(),
            ]);
            self::syncMatchScoreFromEvents((string) $event['match_id']);
            self::audit('void_event', 'match', (string) $event['match_id'], ['before' => $event, 'after' => $row]);
            $pdo->commit();
            return $row;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    private static function syncMatchScoreFromEvents(string $matchId): void
    {
        $match = self::findById('matches', $matchId);
        $confirmedResult = self::fetchOne('SELECT scoring_type FROM match_results WHERE match_id = :match_id LIMIT 1', [':match_id' => $matchId]);
        if ($confirmedResult && !in_array((string) $confirmedResult['scoring_type'], ['SCORE', 'PERIOD_SCORE'], true)) {
            return;
        }
        $hasScoringHistory = self::fetchOne(
            'SELECT id FROM match_events WHERE match_id = :match_id AND points != 0 LIMIT 1',
            [':match_id' => $matchId]
        );
        if (!$hasScoringHistory) {
            return;
        }
        $totals = [(string) $match['team1_id'] => 0, (string) $match['team2_id'] => 0];
        foreach (self::fetchAll('SELECT team_id, points FROM match_events WHERE match_id = :match_id AND is_voided = 0', [':match_id' => $matchId]) as $event) {
            $key = (string) ($event['team_id'] ?? '');
            if ($key !== '' && array_key_exists($key, $totals)) {
                $totals[$key] += (int) $event['points'];
            }
        }
        $score1 = max(0, $totals[(string) $match['team1_id']] ?? 0);
        $score2 = max(0, $totals[(string) $match['team2_id']] ?? 0);
        $patch = ['team1_score' => $score1, 'team2_score' => $score2];
        if ((string) $match['status'] === 'completed') {
            $rule = self::matchScoringContext($match)['rule'];
            $patch['winner_team_id'] = self::determineMatchWinner($match, $score1, $score2, $rule, self::nullableId($match['winner_team_id'] ?? null));
        }
        self::updateRow('matches', $matchId, $patch);
        if ((string) $match['status'] === 'completed') {
            self::recalculateStandings((string) $match['tournament_id']);
        }
    }

}
