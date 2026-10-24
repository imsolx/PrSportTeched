<?php
declare(strict_types=1);

/**
 * Tournament feature methods extracted without changing their implementations.
 * AppService keeps the existing public API and shared data/access helpers.
 */
trait TournamentModule
{
    public static function listTournaments(): array
    {
        Auth::canViewSportsWork();
        $sportIds = self::groupConcat('ts.sport_id');
        $sportNames = self::groupConcat('s2.name', ' · ');
        return self::enrichedTournamentRows(self::fetchAll(
            "SELECT tournaments.*, sports.name AS primary_sport_name,
                    (SELECT {$sportIds} FROM tournament_sports ts WHERE ts.tournament_id = tournaments.id) AS sport_ids_csv,
                    (SELECT {$sportNames} FROM tournament_sports ts2
                     JOIN sports s2 ON s2.id = ts2.sport_id WHERE ts2.tournament_id = tournaments.id) AS sport_names
             FROM tournaments
             LEFT JOIN sports ON sports.id = tournaments.sport_id
             ORDER BY tournaments.id DESC"
        ));
    }

    public static function createTournament(array $payload): array
    {
        Auth::canManageTournaments();
        $sportIds = self::tournamentSportIds($payload);
        $record = self::validateTournamentPayload($payload);
        $record['id'] = self::nextId('tournaments');
        $record['published_at'] = '';
        $record['created_by'] = Auth::currentUser()['email'] ?? '';
        $record['created_at'] = Database::now();
        $record['updated_at'] = Database::now();
        $row = self::insertRow('tournaments', $record);
        self::replaceTournamentSports((string) $row['id'], $sportIds);
        $row = self::enrichedTournamentRows([$row])[0];
        self::audit('create', 'tournament', (string) $row['id'], $row);
        return $row;
    }

    public static function updateTournament(string $id, array $payload): array
    {
        Auth::canManageTournaments();
        $current = self::findById('tournaments', $id);
        $next = array_replace($current, $payload);
        $sportIds = array_key_exists('sport_ids', $payload)
            ? self::tournamentSportIds($next)
            : self::tournamentSportIds(['sport_id' => $payload['sport_id'] ?? $current['sport_id']]);
        $next['sport_ids'] = $sportIds;
        $row = self::updateRow('tournaments', $id, self::validateTournamentPayload($next));
        self::replaceTournamentSports($id, $sportIds);
        $row = self::enrichedTournamentRows([$row])[0];
        self::audit('update', 'tournament', $id, $row);
        return $row;
    }

    public static function deleteTournament(string $id): array
    {
        Auth::canManageTournaments();
        if (self::countRows('teams', 'tournament_id = :id', [':id' => $id]) > 0 || self::countRows('matches', 'tournament_id = :id', [':id' => $id]) > 0) {
            throw new RuntimeException('ลบรายการแข่งขันไม่ได้ เพราะมีทีมหรือแมตช์ผูกอยู่');
        }
        self::deleteRowsWhere('standings', 'tournament_id = :id', [':id' => $id]);
        self::deleteRowsWhere('tournament_departments', 'tournament_id = :id', [':id' => $id]);
        self::deleteRowsWhere('tournament_sport_settings', 'tournament_id = :id', [':id' => $id]);
        $deleted = self::deleteRow('tournaments', $id);
        self::audit('delete', 'tournament', $id, $deleted);
        return $deleted;
    }

    public static function tournamentWorkspace(string $tournamentId): array
    {
        Auth::canViewSportsWork();
        if ($tournamentId === '') {
            throw new InvalidArgumentException('กรุณาเลือกรายการแข่งขัน');
        }
        $tournament = self::enrichedTournamentRows([self::findById('tournaments', $tournamentId)])[0];
        foreach ($tournament['sport_ids'] as $sportId) {
            self::ensureTournamentSportSetting($tournamentId, (string) $sportId);
        }
        $sports = self::fetchAll(
            'SELECT sports.*, tournament_sport_settings.id AS setting_id,
                    tournament_sport_settings.rule_version_id, tournament_sport_settings.status AS setting_status,
                    tournament_sport_settings.notes AS setting_notes,
                    sport_rule_versions.name AS rule_name, sport_rule_versions.version_number AS rule_version_number
             FROM tournament_sports
             JOIN sports ON sports.id = tournament_sports.sport_id
             LEFT JOIN tournament_sport_settings
               ON tournament_sport_settings.tournament_id = tournament_sports.tournament_id
              AND tournament_sport_settings.sport_id = tournament_sports.sport_id
             LEFT JOIN sport_rule_versions ON sport_rule_versions.id = tournament_sport_settings.rule_version_id
             WHERE tournament_sports.tournament_id = :tournament_id
             ORDER BY sports.name',
            [':tournament_id' => $tournamentId]
        );

        $totals = [
            'sports' => count($sports),
            'departments' => 0,
            'students' => self::countRows('students'),
            'teams' => self::countRows('teams', 'tournament_id = :id', [':id' => $tournamentId]),
            'members' => 0,
            'matches' => self::countRows('matches', 'tournament_id = :id', [':id' => $tournamentId]),
            'completed_matches' => self::countRows('matches', 'tournament_id = :id AND status = :status', [':id' => $tournamentId, ':status' => 'completed']),
        ];
        $memberCount = self::fetchOne(
            'SELECT COUNT(*) AS total FROM team_members JOIN teams ON teams.id = team_members.team_id WHERE teams.tournament_id = :id',
            [':id' => $tournamentId]
        );
        $totals['members'] = (int) ($memberCount['total'] ?? 0);

        foreach ($sports as &$sport) {
            $sportId = (string) $sport['id'];
            $departments = self::fetchAll(
                'SELECT departments.code, departments.name
                 FROM tournament_departments
                 JOIN departments ON departments.code = tournament_departments.department_code
                 WHERE tournament_departments.tournament_id = :tournament_id AND tournament_departments.sport_id = :sport_id
                 ORDER BY departments.name',
                [':tournament_id' => $tournamentId, ':sport_id' => $sportId]
            );
            $teamCount = self::countRows('teams', 'tournament_id = :tournament_id AND sport_id = :sport_id', [
                ':tournament_id' => $tournamentId,
                ':sport_id' => $sportId,
            ]);
            $memberRow = self::fetchOne(
                'SELECT COUNT(*) AS total FROM team_members JOIN teams ON teams.id = team_members.team_id
                 WHERE teams.tournament_id = :tournament_id AND teams.sport_id = :sport_id',
                [':tournament_id' => $tournamentId, ':sport_id' => $sportId]
            );
            $matchCount = self::countRows('matches', 'tournament_id = :tournament_id AND sport_id = :sport_id', [
                ':tournament_id' => $tournamentId,
                ':sport_id' => $sportId,
            ]);
            $sport['departments'] = $departments;
            $sport['department_codes'] = array_column($departments, 'code');
            $sport['department_count'] = count($departments);
            $sport['team_count'] = $teamCount;
            $sport['member_count'] = (int) ($memberRow['total'] ?? 0);
            $sport['match_count'] = $matchCount;
            $sport['rule_ready'] = (string) ($sport['setting_status'] ?? '') === 'ready' && !empty($sport['rule_version_id']);
            $sport['departments_ready'] = true;
            $sport['roster_ready'] = $teamCount > 0 && (int) $sport['member_count'] > 0;
            $sport['schedule_ready'] = $matchCount > 0;
            $totals['departments'] += count($departments);
        }
        unset($sport);

        $all = static fn(string $key): bool => (bool) $sports && count(array_filter($sports, fn(array $row): bool => !empty($row[$key]))) === count($sports);
        $steps = [
            1 => true,
            2 => count($sports) > 0,
            3 => $all('rule_ready'),
            4 => true,
            5 => $totals['students'] > 0,
            6 => $totals['teams'] > 0,
            7 => $totals['members'] > 0,
            8 => $all('rule_ready') && $all('roster_ready'),
            9 => $totals['matches'] > 0,
            10 => $totals['completed_matches'] > 0,
            11 => false,
        ];
        $suggestedStep = 11;
        foreach ($steps as $number => $complete) {
            if (!$complete) {
                $suggestedStep = $number;
                break;
            }
        }

        return [
            'tournament' => $tournament,
            'sports' => $sports,
            'totals' => $totals,
            'steps' => $steps,
            'suggested_step' => $suggestedStep,
        ];
    }

    public static function saveTournamentSportSetting(array $payload): array
    {
        Auth::canManageTournaments();
        $tournamentId = trim((string) ($payload['tournament_id'] ?? ''));
        $sportId = trim((string) ($payload['sport_id'] ?? ''));
        self::assertTournamentSport($tournamentId, $sportId);
        $ruleId = trim((string) ($payload['rule_version_id'] ?? ''));
        $rule = self::findById('sport_rule_versions', $ruleId);
        if ((string) $rule['sport_id'] !== $sportId) {
            throw new InvalidArgumentException('ชุดกติกาต้องเป็นของกีฬาที่เลือก');
        }
        $status = (string) ($payload['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'ready', 'locked'], true)) {
            throw new InvalidArgumentException('สถานะการตั้งค่ากีฬาไม่ถูกต้อง');
        }
        $record = [
            'tournament_id' => $tournamentId,
            'sport_id' => $sportId,
            'rule_version_id' => $ruleId,
            'status' => $status,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'updated_at' => Database::now(),
        ];
        $existing = self::fetchOne(
            'SELECT * FROM tournament_sport_settings WHERE tournament_id = :tournament_id AND sport_id = :sport_id',
            [':tournament_id' => $tournamentId, ':sport_id' => $sportId]
        );
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($existing) {
                $row = self::updateRow('tournament_sport_settings', (string) $existing['id'], $record);
            } else {
                $record['id'] = self::nextId('tournament_sport_settings');
                $record['created_at'] = Database::now();
                $row = self::insertRow('tournament_sport_settings', $record);
            }

            // A newly selected rule applies to future work. Existing live/completed
            // matches remain pinned to their historical rule, while untouched
            // scheduled matches can safely follow the tournament setting.
            $candidates = self::fetchAll(
                "SELECT matches.id FROM matches
                 WHERE matches.tournament_id = :tournament_id
                   AND matches.sport_id = :sport_id
                   AND matches.status = 'scheduled'
                   AND matches.rule_version_id <> :rule_version_id
                   AND NOT EXISTS (SELECT 1 FROM match_events WHERE match_events.match_id = matches.id)
                   AND NOT EXISTS (SELECT 1 FROM match_results WHERE match_results.match_id = matches.id)",
                [':tournament_id' => $tournamentId, ':sport_id' => $sportId, ':rule_version_id' => $ruleId]
            );
            foreach ($candidates as $candidate) {
                $matchId = (string) $candidate['id'];
                self::deleteRowsWhere('match_periods', 'match_id = :id', [':id' => $matchId]);
                self::updateRow('matches', $matchId, ['rule_version_id' => $ruleId]);
                self::ensureMatchPeriods($matchId, self::decodeSportRule($rule));
            }
            $migrated = count($candidates);
            self::audit('configure_sport', 'tournament', $tournamentId, [
                'sport_id' => $sportId,
                'previous_rule_version_id' => $existing['rule_version_id'] ?? null,
                'rule_version_id' => $ruleId,
                'status' => $status,
                'migrated_scheduled_match_ids' => array_column($candidates, 'id'),
            ]);
            $pdo->commit();
            return array_replace($row, ['migrated_matches' => $migrated]);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function saveTournamentDepartments(array $payload): array
    {
        Auth::canManageTournaments();
        $tournamentId = trim((string) ($payload['tournament_id'] ?? ''));
        $sportId = trim((string) ($payload['sport_id'] ?? ''));
        self::assertTournamentSport($tournamentId, $sportId);
        $codes = $payload['department_codes'] ?? [];
        if (!is_array($codes)) {
            $codes = preg_split('/\s*,\s*/', trim((string) $codes), -1, PREG_SPLIT_NO_EMPTY);
        }
        $codes = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $codes))));
        if (!$codes) {
            throw new InvalidArgumentException('กรุณาเลือกสาขาที่เข้าร่วมอย่างน้อย 1 สาขา');
        }
        foreach ($codes as $code) {
            if (!self::activeDepartmentExists($code)) {
                throw new InvalidArgumentException('ไม่พบรหัสสาขา: ' . $code);
            }
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::deleteRowsWhere(
                'tournament_departments',
                'tournament_id = :tournament_id AND sport_id = :sport_id',
                [':tournament_id' => $tournamentId, ':sport_id' => $sportId]
            );
            foreach ($codes as $code) {
                self::insertRow('tournament_departments', [
                    'id' => self::nextId('tournament_departments'),
                    'tournament_id' => $tournamentId,
                    'sport_id' => $sportId,
                    'department_code' => $code,
                    'created_at' => Database::now(),
                ]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        self::audit('configure_departments', 'tournament', $tournamentId, [
            'sport_id' => $sportId,
            'department_codes' => $codes,
        ]);
        return ['tournament_id' => $tournamentId, 'sport_id' => $sportId, 'department_codes' => $codes];
    }

    public static function generateTournamentFixtures(array $payload): array
    {
        Auth::canManageTournaments();
        $tournamentId = trim((string) ($payload['tournament_id'] ?? ''));
        $sportId = trim((string) ($payload['sport_id'] ?? ''));
        self::assertTournamentSport($tournamentId, $sportId);
        $tournament = self::findById('tournaments', $tournamentId);
        if (self::countRows('matches', 'tournament_id = :tournament_id AND sport_id = :sport_id', [':tournament_id' => $tournamentId, ':sport_id' => $sportId]) > 0) {
            throw new RuntimeException('กีฬานี้มีแมตช์อยู่แล้ว กรุณาจัดการแมตช์เดิมก่อนสร้างสายใหม่');
        }
        $teams = self::fetchAll("SELECT id FROM teams WHERE tournament_id = :tournament_id AND sport_id = :sport_id AND status NOT IN ('draft', 'rejected', 'withdrawn') ORDER BY id", [':tournament_id' => $tournamentId, ':sport_id' => $sportId]);
        if (count($teams) < 2) {
            throw new RuntimeException('ต้องมีอย่างน้อย 2 ทีมในกีฬาที่เลือก');
        }
        $ids = array_column($teams, 'id');
        $format = (string) $tournament['format'];
        $fixtures = [];
        $byes = [];
        if ($format === 'knockout') {
            [$fixtures, $byes] = self::knockoutFixtures($ids);
        } elseif ($format === 'round_robin') {
            $fixtures = self::roundRobinFixtures($ids, 'พบกันหมด');
        } else {
            if (count($ids) < 4) {
                throw new RuntimeException('รอบแบ่งกลุ่มต้องมีอย่างน้อย 4 ทีม');
            }
            $groupCount = min(intdiv(count($ids), 2), max(2, min(8, (int) ($payload['group_count'] ?? 2))));
            $groups = array_fill(0, $groupCount, []);
            foreach ($ids as $index => $id) {
                $groups[$index % $groupCount][] = $id;
            }
            foreach ($groups as $index => $groupIds) {
                $fixtures = array_merge($fixtures, self::roundRobinFixtures($groupIds, 'กลุ่ม ' . chr(65 + $index)));
            }
            if ($format === 'group_knockout') {
                [$elimination] = self::knockoutFixtures(array_fill(0, $groupCount * 2, null), 'รอบแพ้คัดออก');
                $fixtures = array_merge($fixtures, $elimination);
            }
        }
        if (!$fixtures) {
            throw new RuntimeException('ยังไม่สามารถจัดคู่จากทีมที่เลือกได้');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $created = [];
            foreach ($fixtures as $fixture) {
                $created[] = self::createMatch($fixture + ['tournament_id' => $tournamentId, 'sport_id' => $sportId]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        self::audit('generate_fixtures', 'tournament', $tournamentId, ['sport_id' => $sportId, 'format' => $format, 'count' => count($created)]);
        return ['format' => $format, 'sport_id' => $sportId, 'matches' => $created, 'bye_team_ids' => $byes];
    }

    private static function roundRobinFixtures(array $teamIds, string $roundName): array
    {
        $fixtures = [];
        for ($i = 0; $i < count($teamIds); $i++) {
            for ($j = $i + 1; $j < count($teamIds); $j++) {
                $fixtures[] = ['round_name' => $roundName, 'team1_id' => $teamIds[$i], 'team2_id' => $teamIds[$j]];
            }
        }
        return $fixtures;
    }

    private static function knockoutFixtures(array $teamIds, string $prefix = ''): array
    {
        $slots = array_map(static fn($id): array => ['active' => true, 'team_id' => $id], $teamIds);
        $target = 1;
        while ($target < count($slots)) {
            $target *= 2;
        }
        while (count($slots) < $target) {
            $slots[] = ['active' => false, 'team_id' => null];
        }
        $fixtures = [];
        $byes = [];
        while (count($slots) > 1) {
            $roundSize = count($slots);
            $label = $roundSize === 2 ? 'รอบชิงชนะเลิศ' : ($roundSize === 4 ? 'รอบรองชนะเลิศ' : 'รอบ ' . $roundSize . ' ทีม');
            if ($prefix !== '') {
                $label = $prefix . ' · ' . $label;
            }
            $next = [];
            for ($i = 0; $i < $roundSize; $i += 2) {
                $first = $slots[$i];
                $second = $slots[$i + 1];
                if (!$first['active'] && !$second['active']) {
                    $next[] = ['active' => false, 'team_id' => null];
                } elseif (!$first['active'] || !$second['active']) {
                    $advancing = $first['active'] ? $first : $second;
                    if ($advancing['team_id'] !== null) {
                        $byes[] = $advancing['team_id'];
                    }
                    $next[] = $advancing;
                } else {
                    $fixtures[] = ['round_name' => $label, 'team1_id' => $first['team_id'], 'team2_id' => $second['team_id']];
                    $next[] = ['active' => true, 'team_id' => null];
                }
            }
            $slots = $next;
        }
        return [$fixtures, array_values(array_unique($byes))];
    }

    private static function validateTournamentPayload(array $payload): array
    {
        $sportIds = self::tournamentSportIds($payload);
        $sportId = (string) $sportIds[0];
        $name = trim((string) ($payload['name'] ?? ''));
        $format = (string) ($payload['format'] ?? 'knockout');
        $status = (string) ($payload['status'] ?? 'draft');
        if ($name === '') {
            throw new InvalidArgumentException('กรุณากรอกชื่อรายการแข่ง');
        }
        if (!in_array($format, ['knockout', 'round_robin', 'group', 'group_knockout'], true)) {
            throw new InvalidArgumentException('รูปแบบรายการไม่ถูกต้อง');
        }
        if (!in_array($status, ['draft', 'data_entry', 'ready', 'registration', 'closed', 'in_progress', 'completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('สถานะรายการไม่ถูกต้อง');
        }
        $startsAt = trim((string) ($payload['starts_at'] ?? ''));
        $endsAt = trim((string) ($payload['ends_at'] ?? ''));
        if ($startsAt !== '' && $endsAt !== '' && $endsAt < $startsAt) {
            throw new InvalidArgumentException('วันสิ้นสุดต้องไม่ก่อนวันเริ่ม');
        }
        return [
            'academic_year' => trim((string) ($payload['academic_year'] ?? '')),
            'sport_id' => $sportId,
            'name' => $name,
            'logo_url' => trim((string) ($payload['logo_url'] ?? '')),
            'format' => $format,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
        ];
    }

    private static function tournamentSportIds(array $payload): array
    {
        $raw = $payload['sport_ids'] ?? [];
        if (!is_array($raw)) {
            $raw = preg_split('/\s*,\s*/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!$raw && !empty($payload['sport_id'])) {
            $raw = [$payload['sport_id']];
        }
        $ids = [];
        foreach ($raw as $value) {
            $id = trim((string) $value);
            if ($id !== '' && ctype_digit($id)) {
                $ids[] = (int) $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            throw new InvalidArgumentException('กรุณาเลือกกีฬาอย่างน้อย 1 รายการ');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("SELECT id FROM sports WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $found = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $missing = array_values(array_diff($ids, $found));
        if ($missing) {
            throw new RuntimeException('ไม่พบกีฬาในฐานข้อมูล: ' . implode(', ', $missing));
        }
        return $ids;
    }

    private static function replaceTournamentSports(string $tournamentId, array $sportIds): void
    {
        $existing = array_map('intval', array_column(self::fetchAll(
            'SELECT sport_id FROM tournament_sports WHERE tournament_id = :id',
            [':id' => $tournamentId]
        ), 'sport_id'));
        $removed = array_diff($existing, $sportIds);
        foreach ($removed as $sportId) {
            $params = [':tournament_id' => $tournamentId, ':sport_id' => $sportId];
            $teamCount = self::countRows('teams', 'tournament_id = :tournament_id AND sport_id = :sport_id', $params);
            $matchCount = self::countRows('matches', 'tournament_id = :tournament_id AND sport_id = :sport_id', $params);
            if ($teamCount > 0 || $matchCount > 0) {
                throw new RuntimeException('นำกีฬาออกจากทัวร์นาเมนต์ไม่ได้ เพราะมีทีม หรือแมตช์เชื่อมโยงอยู่ กรุณาจัดการข้อมูลที่เกี่ยวข้องก่อน');
            }
            self::deleteRowsWhere('tournament_departments', 'tournament_id = :tournament_id AND sport_id = :sport_id', $params);
            self::deleteRowsWhere('tournament_sport_settings', 'tournament_id = :tournament_id AND sport_id = :sport_id', $params);
            self::deleteRowsWhere('tournament_sports', 'tournament_id = :tournament_id AND sport_id = :sport_id', $params);
        }
        foreach ($sportIds as $sportId) {
            if (!in_array((int) $sportId, $existing, true)) {
                Database::pdo()->prepare(
                    'INSERT INTO tournament_sports (tournament_id, sport_id) VALUES (:tournament_id, :sport_id)'
                )->execute([':tournament_id' => $tournamentId, ':sport_id' => $sportId]);
            }
            self::ensureTournamentSportSetting($tournamentId, (string) $sportId);
        }
    }

    private static function assertTournamentSport(string $tournamentId, string $sportId): void
    {
        if ($tournamentId === '' || $sportId === '' || !self::fetchOne(
            'SELECT sport_id FROM tournament_sports WHERE tournament_id = :tournament_id AND sport_id = :sport_id',
            [':tournament_id' => $tournamentId, ':sport_id' => $sportId]
        )) {
            throw new RuntimeException('กีฬานี้ไม่ได้อยู่ในทัวร์นาเมนต์ที่เลือก');
        }
    }

    private static function ensureTournamentSportSetting(string $tournamentId, string $sportId): array
    {
        $existing = self::fetchOne(
            'SELECT * FROM tournament_sport_settings WHERE tournament_id = :tournament_id AND sport_id = :sport_id',
            [':tournament_id' => $tournamentId, ':sport_id' => $sportId]
        );
        if ($existing) {
            return $existing;
        }
        $rule = self::activeSportRule($sportId);
        return self::insertRow('tournament_sport_settings', [
            'id' => self::nextId('tournament_sport_settings'),
            'tournament_id' => $tournamentId,
            'sport_id' => $sportId,
            'rule_version_id' => $rule['id'],
            'status' => 'pending',
            'notes' => '',
            'created_at' => Database::now(),
            'updated_at' => Database::now(),
        ]);
    }

    private static function tournamentSportRule(string $tournamentId, string $sportId): array
    {
        $setting = self::fetchOne(
            'SELECT rule_version_id FROM tournament_sport_settings
             WHERE tournament_id = :tournament_id AND sport_id = :sport_id',
            [':tournament_id' => $tournamentId, ':sport_id' => $sportId]
        );
        if (!empty($setting['rule_version_id'])) {
            return self::decodeSportRule(self::findById('sport_rule_versions', (string) $setting['rule_version_id']));
        }
        return self::activeSportRule($sportId);
    }

    private static function enrichedTournamentRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $csv = (string) ($row['sport_ids_csv'] ?? (is_array($row['sport_ids'] ?? null) ? implode(',', $row['sport_ids']) : ($row['sport_ids'] ?? '')));
            if ($csv === '' && !empty($row['id'])) {
                $links = self::fetchAll('SELECT sport_id FROM tournament_sports WHERE tournament_id = :id ORDER BY sport_id', [':id' => $row['id']]);
                $csv = implode(',', array_column($links, 'sport_id'));
            }
            if ($csv === '') {
                $csv = (string) ($row['sport_id'] ?? '');
            }
            $ids = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $csv, -1, PREG_SPLIT_NO_EMPTY))));
            if (!$ids && !empty($row['sport_id'])) {
                $ids = [(int) $row['sport_id']];
            }
            $row['sport_ids'] = $ids;
            $names = (string) ($row['sport_names'] ?? '');
            if ($names === '' && $ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = Database::pdo()->prepare("SELECT name FROM sports WHERE id IN ({$placeholders}) ORDER BY id");
                $stmt->execute($ids);
                $names = implode(' · ', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
            $row['sport_name'] = $names ?: (string) ($row['primary_sport_name'] ?? '');
            unset($row['primary_sport_name'], $row['sport_ids_csv']);
        }
        unset($row);
        return $rows;
    }

}
