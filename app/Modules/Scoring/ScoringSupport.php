<?php
declare(strict_types=1);

/** Shared data/access helpers and server-authoritative scoring context. */
trait ScoringSupport
{
    private static function mayManageMatchResult(array $match): bool
    {
        $user = Auth::currentUser();
        if (in_array((string) ($user['role'] ?? ''), [Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN], true)) return true;
        if (!in_array((string) ($user['role'] ?? ''), [Auth::ROLE_RESULT_OFFICER, Auth::ROLE_SPORT_HEAD], true)) return false;
        $assignment = self::fetchOne('SELECT * FROM match_official_assignments WHERE match_id = :match_id AND user_id = :user_id',
            [':match_id' => $match['id'], ':user_id' => $user['id']]);
        if (!$assignment) return false;
        $sportId = (string) ($match['sport_id'] ?: (self::findById('tournaments', (string) $match['tournament_id'])['sport_id'] ?? ''));
        $allowedSports = array_filter(array_map('trim', explode(',', (string) ($user['sport_ids'] ?? ''))));
        if (($user['role'] === Auth::ROLE_SPORT_HEAD || $allowedSports) && !in_array($sportId, $allowedSports, true)) return false;
        $teamIds = array_values(array_filter([(string) ($match['team1_id'] ?? ''), (string) ($match['team2_id'] ?? '')]));
        $scopedTeamId = (string) ($assignment['team_id'] ?? '');
        if ($scopedTeamId !== '' && !in_array($scopedTeamId, $teamIds, true)) return false;
        $department = trim((string) ($user['department_code'] ?? ''));
        if ($department !== '' && $teamIds) {
            foreach ($scopedTeamId !== '' ? [$scopedTeamId] : $teamIds as $teamId) {
                $team = self::fetchOne('SELECT department_code FROM teams WHERE id = :id', [':id' => $teamId]);
                if ((string) ($team['department_code'] ?? '') === $department) return true;
            }
            return false;
        }
        return !$teamIds || $user['role'] !== Auth::ROLE_SPORT_HEAD || $scopedTeamId !== '';
    }

    private static function assertMatchResultAccess(array $match): void
    {
        if (!self::mayManageMatchResult($match)) {
            throw new RuntimeException('ไม่ได้รับมอบหมายให้บันทึกผลแมตช์นี้ หรืออยู่นอกขอบเขตทีม/สังกัดที่ดูแล');
        }
    }

    private static function assertMatchReadAccess(array $match): void
    {
        if (in_array((string) (Auth::currentUser()['role'] ?? ''), [Auth::ROLE_SPORT_HEAD, Auth::ROLE_RESULT_OFFICER], true)) {
            self::assertMatchResultAccess($match);
        }
    }

    private static function claimMatchActionRevision(string $matchId, int $expected): void
    {
        $stmt = Database::pdo()->prepare('UPDATE matches SET action_revision = action_revision + 1
            WHERE id = :id AND action_revision = :expected');
        $stmt->execute([':id' => $matchId, ':expected' => $expected]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('แมตช์มีข้อมูลใหม่แล้ว กรุณารีเฟรชก่อนบันทึกอีกครั้ง');
    }

    private static function matchScoringContext(array $match, array $payload = []): array
    {
        $raw = self::fetchOne('SELECT * FROM sport_rule_versions WHERE id = :id', [':id' => (string) ($match['rule_version_id'] ?? '')]);
        if (!$raw) throw new InvalidArgumentException('ไม่พบชุดกติกาที่ผูกกับแมตช์ กรุณาตรวจสอบการตั้งค่า');
        $saved = self::fetchOne('SELECT * FROM match_results WHERE match_id = :id', [':id' => $match['id']]);
        $identity = $match;
        if (array_key_exists('pinned_sport_id', $match)) $identity['sport_id'] = $match['pinned_sport_id'];
        $resolved = ScoringResolver::resolve($identity, $raw, $saved);
        if (array_key_exists('scoring_type', $payload) && (!is_string($payload['scoring_type']) || $payload['scoring_type'] !== $resolved['type'])) {
            throw new InvalidArgumentException('รูปแบบบันทึกไม่ตรงกับกติกาที่ผูกกับแมตช์');
        }
        $rule = self::decodeSportRule($raw);
        $rule['scoring_config_data'] = $resolved['config'];
        return array_replace($resolved, ['rule' => $rule, 'result' => $saved]);
    }

    private static function resolvedMatchScoringType(array $match, array $rule): string
    {
        return self::matchScoringContext($match)['type'];
    }

    private static function assertScoringWriteCurrent(array $match, array $payload = []): void
    {
        $current = MatchScoringGuard::lock((string) $match['id']);
        foreach (['rule_version_id', 'sport_id', 'tournament_id', 'team1_id', 'team2_id', 'status', 'action_revision'] as $key) {
            if ((string) ($current[$key] ?? '') !== (string) ($match[$key] ?? '')) {
                throw new RuntimeException('แมตช์มีข้อมูลใหม่แล้ว กรุณารีเฟรชก่อนบันทึกอีกครั้ง');
            }
        }
        self::matchScoringContext($current, $payload);
    }

    private static function payloadObject($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function jsonObject(string $json): array
    {
        $value = json_decode($json, true);
        return is_array($value) ? $value : [];
    }

    private static function jsonArray(string $json): array
    {
        $value = self::jsonObject($json);
        return self::isList($value) ? $value : [];
    }

    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    public static function audit(string $action, string $entity, string $entityId, array $detail): void
    {
        try {
            self::insertRow('audit_logs', [
                'id' => self::nextId('audit_logs'),
                'user_email' => $action === 'login_failed'
                    ? (string) ($detail['email'] ?? '')
                    : (Auth::currentUser()['email'] ?? ''),
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => Database::now(),
            ]);
        } catch (Throwable $error) {
            error_log($error->getMessage());
        }
    }

    private static function defaultEventTypes(): array
    {
        return [
            ['code' => 'score', 'label' => 'ทำคะแนน', 'points' => 1, 'participant' => 'athlete'],
            ['code' => 'foul', 'label' => 'ฟาวล์', 'points' => 0, 'participant' => 'athlete'],
            ['code' => 'yellow_card', 'label' => 'ใบเหลือง', 'points' => 0, 'participant' => 'athlete'],
            ['code' => 'red_card', 'label' => 'ใบแดง', 'points' => 0, 'participant' => 'athlete'],
            ['code' => 'substitution', 'label' => 'เปลี่ยนตัว', 'points' => 0, 'participant' => 'athlete'],
            ['code' => 'timeout', 'label' => 'ขอเวลานอก', 'points' => 0, 'participant' => 'team'],
            ['code' => 'note', 'label' => 'เหตุการณ์อื่น', 'points' => 0, 'participant' => 'optional'],
        ];
    }

    private static function decodeSportRule(array $row): array
    {
        $scoring = self::jsonObject((string) ($row['scoring_system'] ?? '{}'));
        $match = self::jsonObject((string) ($row['match_format'] ?? '{}'));
        $period = self::jsonObject((string) ($row['period_format'] ?? '{}'));
        $overtime = self::jsonObject((string) ($row['overtime_rules'] ?? '{}'));
        $tieBreak = self::jsonObject((string) ($row['tie_break_rules'] ?? '{}'));
        $ranking = self::jsonObject((string) ($row['ranking_rules'] ?? '{}'));
        $custom = self::jsonObject((string) ($row['custom_rules'] ?? '{}'));
        $legacyTypes = ['points' => 'SCORE', 'sets' => 'SET', 'time' => 'TIME', 'rank' => 'RACE_POSITION'];
        $row['scoring_type'] = strtoupper((string) ($row['scoring_type'] ?? $legacyTypes[$scoring['mode'] ?? 'points'] ?? 'SCORE'));
        $row['scoring_config_data'] = self::jsonObject((string) ($row['scoring_config'] ?? '{}'));
        if ($row['scoring_type'] === 'SCORE') {
            $row['scoring_config_data'] = array_replace([
                'allow_draw' => !empty($match['allow_draw']),
                'allow_extra_time' => !empty($overtime['enabled']),
                'allow_penalty' => (string) ($tieBreak['method'] ?? 'draw') === 'penalty',
                'include_in_standings' => true,
            ], $row['scoring_config_data']);
        } elseif ($row['scoring_type'] === 'SET') {
            $row['scoring_config_data'] = array_replace(['best_of' => max(1, (int) ($period['count'] ?? 3)), 'include_in_standings' => true], $row['scoring_config_data']);
        }
        $row['scoring_config_text'] = json_encode($row['scoring_config_data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $row['include_in_standings'] = !array_key_exists('include_in_standings', $row['scoring_config_data']) || !empty($row['scoring_config_data']['include_in_standings']);
        $row['best_of'] = (int) ($row['scoring_config_data']['best_of'] ?? 3);
        $row['points_to_win'] = (int) ($row['scoring_config_data']['points_to_win_set'] ?? $row['scoring_config_data']['points_to_win'] ?? 0);
        $row['set_win_by'] = (int) ($row['scoring_config_data']['win_by'] ?? 2);
        $row['max_points'] = (int) ($row['scoring_config_data']['max_points'] ?? 0);
        $row['normal_set_points'] = (int) ($row['scoring_config_data']['normal_set_points'] ?? 0);
        $row['final_set_points'] = (int) ($row['scoring_config_data']['final_set_points'] ?? 0);
        $row['target_score'] = (int) ($row['scoring_config_data']['target_score'] ?? 13);
        $row['aggregation'] = (string) ($row['scoring_config_data']['aggregation'] ?? 'SUM');
        $row['quick_points'] = implode(',', (array) ($row['scoring_config_data']['quick_points'] ?? ($row['scoring_type'] === 'PERIOD_SCORE' ? [1, 2, 3] : [1])));
        $row['numeric_win_by'] = (string) ($row['scoring_config_data']['win_by'] ?? 'HIGHEST');
        $row['scoring_mode'] = $scoring['mode'] ?? 'points';
        $row['win_by'] = $scoring['win_by'] ?? 'highest';
        $row['event_points'] = (int) ($scoring['event_points'] ?? 1);
        $row['allow_draw'] = !empty($match['allow_draw']);
        $row['period_count'] = (int) ($period['count'] ?? 1);
        $row['period_minutes'] = (int) ($period['duration_minutes'] ?? 0);
        $row['overtime_enabled'] = !empty($overtime['enabled']);
        $row['overtime_periods'] = (int) ($overtime['periods'] ?? 2);
        $row['overtime_minutes'] = (int) ($overtime['duration_minutes'] ?? 15);
        $row['tie_break_method'] = $tieBreak['method'] ?? 'draw';
        $row['win_points'] = (int) ($ranking['win_points'] ?? 3);
        $row['draw_points'] = (int) ($ranking['draw_points'] ?? 1);
        $row['loss_points'] = (int) ($ranking['loss_points'] ?? 0);
        $row['event_types_data'] = self::jsonArray((string) ($row['event_types'] ?? '[]')) ?: self::defaultEventTypes();
        foreach ($row['event_types_data'] as &$eventType) {
            if (!is_array($eventType)) {
                continue;
            }
            if (!isset($eventType['participant']) || !in_array((string) $eventType['participant'], ['athlete', 'team', 'optional'], true)) {
                $code = (string) ($eventType['code'] ?? '');
                $eventType['participant'] = $code === 'timeout' ? 'team' : ($code === 'note' ? 'optional' : 'athlete');
            }
        }
        unset($eventType);
        $row['custom_rules_text'] = (string) ($custom['note'] ?? $custom['legacy_note'] ?? '');
        $row['tournament_usage_count'] = (int) ($row['tournament_usage_count'] ?? 0);
        $row['match_usage_count'] = (int) ($row['match_usage_count'] ?? 0);
        $row['usage_count'] = $row['tournament_usage_count'] + $row['match_usage_count'];
        $row['is_referenced'] = $row['usage_count'] > 0;
        return $row;
    }

    private static function decodeMatchResult(array $row): array
    {
        $row['config_snapshot_data'] = self::jsonObject((string) ($row['config_snapshot'] ?? '{}'));
        $row['result_data_value'] = self::jsonObject((string) ($row['result_data'] ?? '{}'));
        $row['summary'] = self::jsonObject((string) ($row['summary_json'] ?? '{}'));
        return $row;
    }

    private static function determineMatchWinner(array $match, int $score1, int $score2, array $rule, ?int $tieBreakWinner): ?int
    {
        $team1 = self::nullableId($match['team1_id'] ?? null);
        $team2 = self::nullableId($match['team2_id'] ?? null);
        if ($score1 === $score2) {
            if ($tieBreakWinner !== null && in_array((string) $tieBreakWinner, [(string) $team1, (string) $team2], true)) {
                return $tieBreakWinner;
            }
            if (empty($rule['allow_draw']) && (string) ($rule['tie_break_method'] ?? 'draw') !== 'draw') {
                throw new InvalidArgumentException('กรุณาระบุผู้ชนะจากการตัดสิน Tie-break');
            }
            return null;
        }
        $lowerWins = (string) ($rule['win_by'] ?? 'highest') === 'lowest';
        if ($lowerWins) {
            return $score1 < $score2 ? $team1 : $team2;
        }
        return $score1 > $score2 ? $team1 : $team2;
    }

    private static function joinedTeamMembers(string $teamId): array
    {
        $where = $teamId !== '' ? 'WHERE team_members.team_id = :team_id' : '';
        $params = $teamId !== '' ? [':team_id' => $teamId] : [];
        $rows = self::fetchAll(
            "SELECT team_members.*, students.student_code, students.prefix, students.first_name, students.last_name, students.photo_url,
                    students.department_code AS student_department_code, teams.name AS team_name, teams.department_code AS team_department_code,
                    departments.name AS department_name
             FROM team_members
             LEFT JOIN students ON students.id = team_members.student_id
             LEFT JOIN teams ON teams.id = team_members.team_id
             LEFT JOIN departments ON departments.code = COALESCE(NULLIF(students.department_code, ''), teams.department_code)
             $where
             ORDER BY team_members.team_id ASC, team_members.id ASC",
            $params
        );
        foreach ($rows as &$row) {
            $row['full_name'] = trim(implode(' ', array_filter([
                $row['prefix'] ?? '',
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
            ]))) ?: (string) ($row['student_id'] ?? '');
            $row['department_code'] = $row['student_department_code'] ?: $row['team_department_code'];
        }
        unset($row);
        usort($rows, fn(array $a, array $b): int => ((int) $a['team_id'] <=> (int) $b['team_id']) ?: (self::positionSort((string) ($a['field_position'] ?? ''), 0) <=> self::positionSort((string) ($b['field_position'] ?? ''), 0)));
        return $rows;
    }

    private static function joinedMatches(): array
    {
        $sportNames = self::groupConcat('s2.name', ' · ');
        $rows = self::fetchAll(
            "SELECT matches.*, matches.sport_id AS pinned_sport_id,
                    tournaments.name AS tournament_name, tournaments.logo_url AS tournament_logo_url,
                    tournaments.status AS tournament_status,
                    COALESCE(matches.sport_id, tournaments.sport_id) AS sport_id,
                    sports.name AS sport_name,
                    sports.icon AS sport_icon,
                    sports.logo_url AS sport_logo_url,
                    team1.name AS team1_name_raw,
                    team2.name AS team2_name_raw,
                    team1.logo_url AS team1_logo_url,
                    team2.logo_url AS team2_logo_url,
                    team1.department_code AS team1_department_code,
                    team2.department_code AS team2_department_code,
                    d1.logo_url AS team1_department_logo_url,
                    d2.logo_url AS team2_department_logo_url,
                    d1.name AS team1_department_name,
                    d2.name AS team2_department_name,
                    match_results.scoring_type AS result_scoring_type,
                    match_results.summary_json AS result_summary_json,
                    match_results.revision_number AS result_revision_number
             FROM matches
             LEFT JOIN tournaments ON tournaments.id = matches.tournament_id
             LEFT JOIN sports ON sports.id = COALESCE(matches.sport_id, tournaments.sport_id)
             LEFT JOIN teams team1 ON team1.id = matches.team1_id
             LEFT JOIN teams team2 ON team2.id = matches.team2_id
             LEFT JOIN departments d1 ON d1.code = team1.department_code
             LEFT JOIN departments d2 ON d2.code = team2.department_code
             LEFT JOIN match_results ON match_results.match_id = matches.id
             ORDER BY matches.id DESC"
        );
        foreach ($rows as &$row) {
            $row['match_code'] = 'T' . ($row['tournament_id'] ?: '-') . '-M' . ($row['match_number'] ?: $row['id'] ?: '-');
            $row['team1_name'] = $row['team1_name_raw'] ?: ($row['team1_department_name'] ?: ($row['team1_id'] ?: 'ทีม 1'));
            $row['team2_name'] = $row['team2_name_raw'] ?: ($row['team2_department_name'] ?: ($row['team2_id'] ?: 'ทีม 2'));
            $resultSummary = self::jsonObject((string) ($row['result_summary_json'] ?? '{}'));
            $row['result_summary'] = $resultSummary;
            $row['score_label'] = (string) $row['status'] === 'completed'
                ? (string) ($resultSummary['display_score'] ?? ((string) ($row['team1_score'] ?? 0) . ' - ' . (string) ($row['team2_score'] ?? 0)))
                : 'ยังไม่บันทึกผล';
            $row['status_label'] = self::matchStatusLabel((string) ($row['status'] ?? 'scheduled'));
            $row['display_label'] = $row['match_code'] . ' | ' . ($row['sport_name'] ?: 'ไม่ระบุกีฬา') . ' | ' . ($row['tournament_name'] ?: $row['tournament_id']) . ' | ' . $row['team1_name'] . ' พบ ' . $row['team2_name'] . ' | ' . $row['score_label'];
            unset($row['team1_name_raw'], $row['team2_name_raw']);
        }
        unset($row);
        return $rows;
    }

    private static function matchStatusLabel(string $status): string
    {
        return [
            'scheduled' => 'รอแข่งขัน',
            'live' => 'กำลังแข่งขัน',
            'completed' => 'จบแล้ว',
            'cancelled' => 'ยกเลิก',
            'postponed' => 'เลื่อนแข่ง',
        ][$status] ?? $status;
    }

    private static function positionSort(string $position, int $fallback): int
    {
        $order = [
            'GK' => 10, 'GOALKEEPER' => 10,
            'CB' => 20, 'LB' => 21, 'RB' => 22, 'DEFENDER' => 25,
            'DM' => 30, 'CM' => 31, 'AM' => 32, 'MIDFIELDER' => 35,
            'LW' => 40, 'RW' => 41, 'WF' => 42,
            'ST' => 50, 'CF' => 51, 'FW' => 52, 'FORWARD' => 55,
            'S' => 80, 'SUB' => 80, 'SUBSTITUTE' => 80,
            'COACH' => 90,
        ];
        $key = self::normalizePosition($position);
        return $order[$key] ?? 60 + $fallback;
    }

    private static function normalizePosition(string $value): string
    {
        return strtoupper(trim($value));
    }

    private static function nullableId($value): ?int
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : (int) $value;
    }

    private static function nullableInt($value): ?int
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : (int) $value;
    }

    private static function findById(string $table, string $id): array
    {
        $row = self::fetchOne("SELECT * FROM {$table} WHERE id = :id LIMIT 1", [':id' => $id]);
        if (!$row) {
            throw new RuntimeException('ไม่พบรายการ id ' . $id);
        }
        return $row;
    }

    private static function insertRow(string $table, array $data): array
    {
        $data = self::filterColumns($table, $data);
        $columns = array_keys($data);
        $fields = implode(', ', $columns);
        $placeholders = ':' . implode(', :', $columns);
        $stmt = Database::pdo()->prepare("INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})");
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        $id = $data['id'] ?? Database::pdo()->lastInsertId();
        return self::findById($table, (string) $id);
    }

    private static function updateRow(string $table, string $id, array $patch): array
    {
        self::findById($table, $id);
        if (in_array('updated_at', self::tableColumns($table), true)) {
            $patch['updated_at'] = Database::now();
        }
        $patch = self::filterColumns($table, $patch);
        unset($patch['id'], $patch['created_at']);
        if (!$patch) {
            return self::findById($table, $id);
        }
        $sets = implode(', ', array_map(fn(string $column): string => "{$column} = :{$column}", array_keys($patch)));
        $stmt = Database::pdo()->prepare("UPDATE {$table} SET {$sets} WHERE id = :id");
        foreach ($patch as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return self::findById($table, $id);
    }

    private static function deleteRowsWhere(string $table, string $where, array $params): array
    {
        $rows = self::fetchAll("SELECT * FROM {$table} WHERE {$where}", $params);
        $stmt = Database::pdo()->prepare("DELETE FROM {$table} WHERE {$where}");
        $stmt->execute($params);
        return $rows;
    }

    private static function nextId(string $table): int
    {
        $row = self::fetchOne("SELECT MAX(id) AS max_id FROM {$table}");
        return ((int) ($row['max_id'] ?? 0)) + 1;
    }

    private static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function groupConcat(string $expression, string $separator = ','): string
    {
        $separator = str_replace("'", "''", $separator);
        if (Database::isMysql()) {
            return "GROUP_CONCAT({$expression} SEPARATOR '{$separator}')";
        }
        if (Database::isPgsql()) {
            return "STRING_AGG({$expression}::text, '{$separator}')";
        }
        return "group_concat({$expression}, '{$separator}')";
    }

    private static function filterColumns(string $table, array $data): array
    {
        $columns = self::tableColumns($table);
        return array_intersect_key($data, array_flip($columns));
    }

    private static function tableColumns(string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        if (Database::isMysql()) {
            $rows = Database::pdo()->query("SHOW COLUMNS FROM {$table}")->fetchAll();
            return $cache[$table] = array_map(fn(array $row): string => (string) $row['Field'], $rows);
        }
        if (Database::isPgsql()) {
            $stmt = Database::pdo()->prepare(
                'SELECT column_name FROM information_schema.columns
                 WHERE table_schema = current_schema() AND table_name = :table
                 ORDER BY ordinal_position'
            );
            $stmt->execute([':table' => $table]);
            return $cache[$table] = array_map(fn(array $row): string => (string) $row['column_name'], $stmt->fetchAll());
        }
        $rows = Database::pdo()->query("PRAGMA table_info({$table})")->fetchAll();
        return $cache[$table] = array_map(fn(array $row): string => (string) $row['name'], $rows);
    }
}
