<?php
declare(strict_types=1);

/** Match center, clock/period control and recording drafts; composed only into ScoringService. */
trait MatchRecordingOperations
{
    public static function matchCenter(string $matchId): array
    {
        Auth::canViewSportsWork();
        $match = null;
        foreach (self::joinedMatches() as $row) {
            if ((string) $row['id'] === $matchId) {
                $match = $row;
                break;
            }
        }
        if (!$match) {
            throw new RuntimeException('ไม่พบแมตช์ที่เลือก');
        }
        self::assertMatchReadAccess($match);
        $context = self::matchScoringContext($match);
        $rule = $context['rule'];
        self::ensureMatchPeriods($matchId, $rule);
        $resolvedScoringType = $context['type'];
        $events = self::fetchAll(
            'SELECT match_events.*, teams.name AS team_name,
                    students.student_code, students.prefix, students.first_name, students.last_name
             FROM match_events
             LEFT JOIN teams ON teams.id = match_events.team_id
             LEFT JOIN students ON students.id = match_events.student_id
             WHERE match_events.match_id = :match_id AND match_events.event_type <> :draft_event
             ORDER BY match_events.period_number DESC, match_events.elapsed_seconds DESC, match_events.id DESC',
            [':match_id' => $matchId, ':draft_event' => 'recording_draft']
        );
        foreach ($events as &$event) {
            $event['student_name'] = trim(implode(' ', array_filter([$event['prefix'] ?? '', $event['first_name'] ?? '', $event['last_name'] ?? ''])));
            $event['metadata'] = self::jsonObject((string) ($event['metadata_json'] ?? '{}'));
        }
        unset($event);
        $savedResult = self::fetchOne('SELECT * FROM match_results WHERE match_id = :match_id LIMIT 1', [':match_id' => $matchId]);
        return [
            'match' => $match,
            'rule' => $rule,
            'resolved_scoring_type' => $resolvedScoringType,
            'recorder_page' => $context['recorder_page'],
            'recording_draft' => self::latestMatchRecordingDraft($matchId),
            'recording_history' => self::matchRecordingHistory($matchId),
            'recording_result' => $savedResult ? self::decodeMatchResult($savedResult) : null,
            'periods' => self::fetchAll('SELECT * FROM match_periods WHERE match_id = :match_id ORDER BY period_number', [':match_id' => $matchId]),
            'events' => $events,
            'team1_members' => $match['team1_id'] ? self::joinedTeamMembers((string) $match['team1_id']) : [],
            'team2_members' => $match['team2_id'] ? self::joinedTeamMembers((string) $match['team2_id']) : [],
        ];
    }

    public static function updateMatchClock(string $matchId, array $payload): array
    {
        return MatchScoringGuard::transaction(static function () use ($matchId, $payload): array {
            Auth::canManageResults();
            $match = MatchScoringGuard::lock($matchId);
            self::assertMatchResultAccess($match);
            self::matchScoringContext($match, $payload ?? []);
            if (in_array((string) ($match['status'] ?? ''), ['completed', 'cancelled', 'postponed'], true)) {
                throw new RuntimeException('แมตช์นี้ไม่เปิดให้ควบคุมเวลาแล้ว');
            }
            Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
            $action = (string) ($payload['clock_action'] ?? 'pause');
            $seconds = max(0, (int) ($payload['clock_seconds'] ?? $match['clock_seconds'] ?? 0));
            $period = max(1, (int) ($payload['period_number'] ?? $match['current_period'] ?? 1));
            $patch = ['clock_seconds' => $seconds, 'current_period' => $period];
            if ($action === 'start') {
                $patch['clock_status'] = 'running';
                $patch['status'] = 'live';
                self::markPeriod($matchId, $period, 'live');
            } elseif ($action === 'pause') {
                $patch['clock_status'] = 'paused';
            } elseif ($action === 'reset') {
                $patch['clock_status'] = 'stopped';
                $patch['clock_seconds'] = 0;
            } elseif ($action === 'next_period') {
                self::markPeriod($matchId, $period, 'completed');
                $patch['current_period'] = $period + 1;
                $patch['clock_seconds'] = 0;
                $patch['clock_status'] = 'stopped';
                self::ensurePeriodRow($matchId, $period + 1, 'ช่วงที่ ' . ($period + 1), 'regular', 0);
            } elseif ($action === 'overtime') {
                $patch['overtime_count'] = (int) ($match['overtime_count'] ?? 0) + 1;
                $patch['current_period'] = $period + 1;
                $patch['clock_seconds'] = 0;
                $patch['clock_status'] = 'stopped';
                self::ensurePeriodRow($matchId, $period + 1, 'ต่อเวลาครั้งที่ ' . $patch['overtime_count'], 'overtime', 0);
            } else {
                throw new InvalidArgumentException('คำสั่งนาฬิกาไม่ถูกต้อง');
            }
            $row = self::updateRow('matches', $matchId, $patch);
            self::audit('clock_' . $action, 'match', $matchId, $patch);
            return $row;
        });
    }

    private static function latestMatchRecordingDraft(string $matchId): ?array
    {
        $row = self::fetchOne('SELECT metadata_json, created_at, created_by FROM match_events
            WHERE match_id = :match_id AND event_type = :event_type AND is_voided = 0
            ORDER BY id DESC LIMIT 1', [':match_id' => $matchId, ':event_type' => 'recording_draft']);
        if (!$row) return null;
        $metadata = self::jsonObject((string) ($row['metadata_json'] ?? '{}'));
        return [
            'scoring_type' => (string) ($metadata['scoring_type'] ?? ''),
            'result_data' => is_array($metadata['result_data'] ?? null) ? $metadata['result_data'] : [],
            'created_at' => (string) $row['created_at'],
            'created_by' => (string) $row['created_by'],
        ];
    }

    private static function matchRecordingHistory(string $matchId): array
    {
        return array_map(static function (array $row): array {
            $metadata = self::jsonObject((string) ($row['metadata_json'] ?? '{}'));
            return [
                'id' => $row['id'], 'note' => (string) $row['note'],
                'scoring_type' => (string) ($metadata['scoring_type'] ?? ''),
                'created_at' => (string) $row['created_at'],
                'created_by' => (string) $row['created_by'],
            ];
        }, self::fetchAll('SELECT id, note, metadata_json, created_at, created_by FROM match_events
            WHERE match_id = :match_id AND event_type = :event_type AND is_voided = 0
            ORDER BY id DESC LIMIT 30', [':match_id' => $matchId, ':event_type' => 'recording_draft']));
    }

    public static function saveMatchRecordingDraft(array $payload): array
    {
        Auth::canManageResults();
        $matchId = trim((string) ($payload['match_id'] ?? ''));
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        if (in_array((string) ($match['status'] ?? ''), ['completed', 'cancelled', 'postponed'], true)) {
            throw new RuntimeException('แมตช์นี้ไม่เปิดให้บันทึกแบบร่างแล้ว');
        }
        $rule = self::matchScoringContext($match)['rule'];
        $type = self::resolvedMatchScoringType($match, $rule);
        if ((string) ($payload['scoring_type'] ?? '') !== $type) {
            throw new InvalidArgumentException('รูปแบบบันทึกไม่ตรงกับกติกาที่ผูกกับแมตช์');
        }
        $resultData = self::payloadObject($payload['result_data'] ?? []);
        $metadata = json_encode(['scoring_type' => $type, 'result_data' => $resultData], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metadata === false || strlen($metadata) > 65535) throw new InvalidArgumentException('ข้อมูลแบบร่างมีขนาดใหญ่เกินไป');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload ?? []);
            self::claimMatchActionRevision($matchId, (int) ($payload['expected_revision'] ?? $match['action_revision'] ?? 0));
            $row = self::insertRow('match_events', [
                'id' => self::nextId('match_events'), 'match_id' => $matchId,
                'event_type' => 'recording_draft', 'team_id' => null, 'student_id' => null,
                'period_number' => max(1, (int) ($match['current_period'] ?? 1)),
                'elapsed_seconds' => max(0, (int) ($match['clock_seconds'] ?? 0)),
                'added_time' => 0, 'points' => 0, 'card_type' => null,
                'note' => mb_substr(trim((string) ($payload['action_note'] ?? 'บันทึกแบบร่าง ' . $type)), 0, 200),
                'metadata_json' => $metadata,
                'is_voided' => 0, 'created_by' => Auth::currentUser()['email'] ?? '',
                'created_at' => Database::now(),
            ]);
            self::audit('recording_draft', 'match', $matchId, ['event_id' => $row['id'], 'scoring_type' => $type]);
            $pdo->commit();
            return ['match_id' => $matchId, 'scoring_type' => $type, 'created_at' => $row['created_at']];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function ensureMatchPeriods(string $matchId, array $rule): void
    {
        $decoded = isset($rule['period_count']) ? $rule : self::decodeSportRule($rule);
        $count = max(1, (int) $decoded['period_count']);
        $duration = max(0, (int) $decoded['period_minutes']) * 60;
        for ($number = 1; $number <= $count; $number++) {
            self::ensurePeriodRow($matchId, $number, 'ช่วงที่ ' . $number, 'regular', $duration);
        }
    }

    private static function ensurePeriodRow(string $matchId, int $number, string $name, string $type, int $duration): void
    {
        if (self::fetchOne('SELECT id FROM match_periods WHERE match_id = :match_id AND period_number = :period_number', [':match_id' => $matchId, ':period_number' => $number])) {
            return;
        }
        self::insertRow('match_periods', [
            'id' => self::nextId('match_periods'),
            'match_id' => $matchId,
            'period_number' => $number,
            'name' => $name,
            'period_type' => $type,
            'duration_seconds' => $duration,
            'status' => 'pending',
        ]);
    }

    private static function markPeriod(string $matchId, int $number, string $status): void
    {
        $period = self::fetchOne('SELECT * FROM match_periods WHERE match_id = :match_id AND period_number = :period_number', [':match_id' => $matchId, ':period_number' => $number]);
        if (!$period) {
            self::ensurePeriodRow($matchId, $number, 'ช่วงที่ ' . $number, 'regular', 0);
            $period = self::fetchOne('SELECT * FROM match_periods WHERE match_id = :match_id AND period_number = :period_number', [':match_id' => $matchId, ':period_number' => $number]);
        }
        $patch = ['status' => $status];
        if ($status === 'live' && empty($period['started_at'])) {
            $patch['started_at'] = Database::now();
        }
        if ($status === 'completed') {
            $patch['ended_at'] = Database::now();
        }
        self::updateRow('match_periods', (string) $period['id'], $patch);
    }

}
