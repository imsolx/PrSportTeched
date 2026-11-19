<?php
declare(strict_types=1);

/** Result persistence/revisions, reopening and standings; composed only into ScoringService. */
trait MatchResultOperations
{
    public static function saveMatchResult(string $id, array $payload): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $id);
        self::assertMatchResultAccess($match);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        $context = self::matchScoringContext($match, $payload);
        $rule = $context['rule'];
        $scoringType = $context['type'];
        $scoringConfig = $context['config'];
        $resultPayload = self::payloadObject($payload['result_data'] ?? []);
        if (!$resultPayload) {
            $score1 = self::nullableInt($payload['team1_score'] ?? null);
            $score2 = self::nullableInt($payload['team2_score'] ?? null);
            if ($score1 === null || $score2 === null) {
                throw new InvalidArgumentException('กรุณาระบุคะแนนทั้งสองฝั่ง');
            }
            $resultPayload = ['regular_score' => ['home' => $score1, 'away' => $score2]];
        }
        $resolved = ScoringEngine::resolve(
            $scoringType,
            $scoringConfig,
            $resultPayload,
            ['home_id' => $match['team1_id'], 'away_id' => $match['team2_id']]
        );
        $summary = $resolved['summary'];
        $existing = $context['result'];
        $expectedRevision = (int) ($payload['expected_revision'] ?? $existing['revision_number'] ?? 0);
        if ($expectedRevision !== (int) ($existing['revision_number'] ?? 0)) {
            throw new RuntimeException('ผลถูกแก้ไขโดยผู้ใช้อื่นแล้ว กรุณารีเฟรชก่อนบันทึกอีกครั้ง');
        }
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($existing && $reason === '') {
            throw new InvalidArgumentException('กรุณาระบุเหตุผลในการแก้ไขผลการแข่งขัน');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload);
            $now = Database::now();
            $email = Auth::currentUser()['email'] ?? '';
            $stored = [
                'match_id' => $id,
                'scoring_type' => $resolved['scoring_type'],
                'config_snapshot' => json_encode($scoringConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'result_data' => json_encode($resolved['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $email,
                'updated_at' => $now,
            ];
            if ($existing) {
                $revision = (int) $existing['revision_number'] + 1;
                self::insertRow('match_result_revisions', [
                    'id' => self::nextId('match_result_revisions'),
                    'match_result_id' => $existing['id'],
                    'revision_number' => $revision,
                    'old_result' => (string) $existing['result_data'],
                    'new_result' => $stored['result_data'],
                    'reason' => $reason,
                    'updated_by' => $email,
                    'created_at' => $now,
                ]);
                $stored['revision_number'] = $revision;
                $update = $pdo->prepare('UPDATE match_results SET scoring_type = :type, config_snapshot = :config,
                    result_data = :result, summary_json = :summary, updated_by = :updated_by,
                    updated_at = :updated_at, revision_number = :revision
                    WHERE id = :id AND revision_number = :expected');
                $update->execute([':type' => $stored['scoring_type'], ':config' => $stored['config_snapshot'],
                    ':result' => $stored['result_data'], ':summary' => $stored['summary_json'],
                    ':updated_by' => $email, ':updated_at' => $now, ':revision' => $revision,
                    ':id' => $existing['id'], ':expected' => $expectedRevision]);
                if ($update->rowCount() !== 1) throw new RuntimeException('ผลถูกแก้ไขโดยผู้ใช้อื่นแล้ว กรุณารีเฟรชก่อนบันทึกอีกครั้ง');
                $resultRow = self::findById('match_results', (string) $existing['id']);
            } else {
                $stored['id'] = self::nextId('match_results');
                $stored['revision_number'] = 1;
                $stored['created_by'] = $email;
                $stored['created_at'] = $now;
                $resultRow = self::insertRow('match_results', $stored);
            }
            $row = self::updateRow('matches', $id, [
                'status' => 'completed',
                'clock_status' => 'stopped',
                'team1_score' => $summary['home_score'],
                'team2_score' => $summary['away_score'],
                'winner_team_id' => $summary['winner_id'],
                'confirmed_by' => $email,
                'confirmed_at' => $now,
            ]);
            self::recalculateStandings((string) $match['tournament_id']);
            self::audit($existing ? 'result_update' : 'result', 'match', $id, [
                'old_result' => $existing ? self::jsonObject((string) $existing['result_data']) : null,
                'new_result' => $resolved['result'],
                'summary' => $summary,
                'reason' => $reason,
            ]);
            $pdo->commit();
            return array_replace($row, ['result' => self::decodeMatchResult($resultRow)]);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function matchResultDetail(string $matchId): array
    {
        Auth::canViewSportsWork();
        $match = self::findById('matches', $matchId);
        self::assertMatchReadAccess($match);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        $context = self::matchScoringContext($match);
        $rule = $context['rule'];
        $result = self::fetchOne('SELECT * FROM match_results WHERE match_id = :match_id LIMIT 1', [':match_id' => $matchId]);
        return [
            'match' => $match,
            'rule' => $rule,
            'suggested_scoring_type' => $context['type'],
            'result' => $result ? self::decodeMatchResult($result) : null,
        ];
    }

    public static function deleteMatchResult(string $matchId, array $payload): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        $result = self::fetchOne('SELECT * FROM match_results WHERE match_id = :match_id LIMIT 1', [':match_id' => $matchId]);
        if (!$result) throw new RuntimeException('แมตช์นี้ยังไม่มีผลการแข่งขันทางการให้ยกเลิก');
        $expectedRevision = (int) ($payload['expected_revision'] ?? $result['revision_number'] ?? 0);
        if ($expectedRevision !== (int) ($result['revision_number'] ?? 0)) {
            throw new RuntimeException('ผลถูกแก้ไขโดยผู้ใช้อื่นแล้ว กรุณารีเฟรชก่อนยกเลิกผล');
        }
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') throw new InvalidArgumentException('กรุณาระบุเหตุผลในการยกเลิกผลการแข่งขัน');
        $revisionHistory = self::fetchAll(
            'SELECT revision_number, old_result, new_result, reason, updated_by, created_at
             FROM match_result_revisions WHERE match_result_id = :id ORDER BY revision_number',
            [':id' => (string) $result['id']]
        );

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload);
            self::deleteRowsWhere('match_results', 'id = :id', [':id' => (string) $result['id']]);
            self::updateRow('matches', $matchId, [
                'status' => 'live',
                'clock_status' => 'paused',
                'winner_team_id' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'action_revision' => (int) ($match['action_revision'] ?? 0) + 1,
            ]);
            self::syncMatchScoreFromEvents($matchId);
            $row = self::findById('matches', $matchId);
            self::recalculateStandings((string) $match['tournament_id']);
            self::audit('result_delete', 'match', $matchId, [
                'old_result' => self::decodeMatchResult($result), 'revision_history' => $revisionHistory,
                'reason' => $reason, 'reopened_status' => 'live',
            ]);
            $pdo->commit();
            return $row;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function recalculateStandings(string $tournamentId): void
    {
        self::deleteRowsWhere('standings', 'tournament_id = :tournament_id', [':tournament_id' => $tournamentId]);
        $teams = self::fetchAll(
            "SELECT * FROM teams WHERE tournament_id = :tournament_id AND status NOT IN ('rejected', 'withdrawn')",
            [':tournament_id' => $tournamentId]
        );
        $rows = [];
        foreach ($teams as $team) {
            $rows[(string) $team['id']] = [
                'team_id' => $team['id'], 'sport_id' => (string) ($team['sport_id'] ?? ''),
                'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                'points' => 0, 'scored' => 0, 'conceded' => 0,
            ];
        }
        $rules = [];
        foreach (self::fetchAll(
            "SELECT matches.*, match_results.config_snapshot AS result_config_snapshot
             FROM matches
             LEFT JOIN match_results ON match_results.match_id = matches.id
             WHERE matches.tournament_id = :tournament_id AND matches.status = 'completed'",
            [':tournament_id' => $tournamentId]
        ) as $match) {
            $team1 = (string) ($match['team1_id'] ?? '');
            $team2 = (string) ($match['team2_id'] ?? '');
            if (!isset($rows[$team1], $rows[$team2])) {
                continue;
            }
            $ruleId = (string) ($match['rule_version_id'] ?? '');
            if (!isset($rules[$ruleId])) {
                $context = self::matchScoringContext($match);
                $decoded = $context['rule'];
                $detected = $context['type'];
                $rules[$ruleId] = [
                    'win' => (int) ($decoded['win_points'] ?? 3),
                    'draw' => (int) ($decoded['draw_points'] ?? 1),
                    'loss' => (int) ($decoded['loss_points'] ?? 0),
                    'ranking_mode' => in_array($detected, ['TIME', 'RACE_POSITION', 'DISTANCE', 'HEIGHT', 'POINT_RANKING'], true),
                    'include' => !in_array($detected, ['TIME', 'RACE_POSITION', 'DISTANCE', 'HEIGHT', 'POINT_RANKING'], true)
                        && (!array_key_exists('include_in_standings', $decoded['scoring_config_data'] ?? [])
                        || !empty($decoded['scoring_config_data']['include_in_standings'])),
                ];
            }
            $rule = $rules[$ruleId];
            if (trim((string) ($match['result_config_snapshot'] ?? '')) !== '') {
                $resultConfig = self::jsonObject((string) $match['result_config_snapshot']);
                $rule['include'] = !array_key_exists('include_in_standings', $resultConfig) || !empty($resultConfig['include_in_standings']);
            }
            if (!$rule['include'] || $rule['ranking_mode']) {
                continue;
            }
            $score1 = (int) ($match['team1_score'] ?? 0);
            $score2 = (int) ($match['team2_score'] ?? 0);
            $rows[$team1]['played']++; $rows[$team2]['played']++;
            $rows[$team1]['scored'] += $score1; $rows[$team1]['conceded'] += $score2;
            $rows[$team2]['scored'] += $score2; $rows[$team2]['conceded'] += $score1;
            $winnerId = (string) ($match['winner_team_id'] ?? '');
            if ($winnerId === $team1) {
                $rows[$team1]['won']++; $rows[$team2]['lost']++;
                $rows[$team1]['points'] += $rule['win']; $rows[$team2]['points'] += $rule['loss'];
            } elseif ($winnerId === $team2) {
                $rows[$team2]['won']++; $rows[$team1]['lost']++;
                $rows[$team2]['points'] += $rule['win']; $rows[$team1]['points'] += $rule['loss'];
            } else {
                $rows[$team1]['drawn']++; $rows[$team2]['drawn']++;
                $rows[$team1]['points'] += $rule['draw']; $rows[$team2]['points'] += $rule['draw'];
            }
        }
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['sport_id']][] = $row;
        }
        foreach ($groups as $group) {
            usort($group, function (array $a, array $b): int {
                return ($b['points'] <=> $a['points'])
                    ?: (($b['scored'] - $b['conceded']) <=> ($a['scored'] - $a['conceded']))
                    ?: ($b['scored'] <=> $a['scored'])
                    ?: ((int) $a['team_id'] <=> (int) $b['team_id']);
            });
            foreach ($group as $index => $row) {
                unset($row['sport_id']);
                $row['id'] = self::nextId('standings');
                $row['tournament_id'] = $tournamentId;
                $row['rank_number'] = $index + 1;
                $row['updated_at'] = Database::now();
                self::insertRow('standings', $row);
            }
        }
    }

}
