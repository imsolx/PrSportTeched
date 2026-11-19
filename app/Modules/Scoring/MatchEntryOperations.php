<?php
declare(strict_types=1);

/** Multi-entrant registration, timing, ranking and confirmation; composed only into ScoringService. */
trait MatchEntryOperations
{
    private static function assertRankingMatch(array $match): void
    {
        $rule = self::matchScoringContext($match)['rule'];
        if (!in_array(self::resolvedMatchScoringType($match, $rule), ['TIME', 'RACE_POSITION'], true)) {
            throw new InvalidArgumentException('แมตช์นี้ไม่ใช่การแข่งขันแบบจัดอันดับด้วยเวลา');
        }
    }

    public static function matchEntries(string $matchId): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        self::assertRankingMatch($match);
        return self::fetchAll('SELECT match_entries.*, departments.name AS department_name
            FROM match_entries LEFT JOIN departments ON departments.code = match_entries.department_code
            WHERE match_entries.match_id = :id ORDER BY COALESCE(lane_number, 999), id', [':id' => $matchId]);
    }

    public static function matchEntryCandidates(string $matchId): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        self::assertRankingMatch($match);
        $teams = self::fetchAll('SELECT id, name, department_code FROM teams WHERE tournament_id = :tournament AND sport_id = :sport ORDER BY name',
            [':tournament' => $match['tournament_id'], ':sport' => $match['sport_id']]);
        $athletes = self::fetchAll('SELECT DISTINCT students.id, students.student_code, students.prefix, students.first_name, students.last_name,
                students.department_code, teams.id AS team_id, teams.name AS team_name
            FROM team_members JOIN teams ON teams.id = team_members.team_id
            JOIN students ON students.id = team_members.student_id
            WHERE teams.tournament_id = :tournament AND teams.sport_id = :sport ORDER BY students.first_name, students.id',
            [':tournament' => $match['tournament_id'], ':sport' => $match['sport_id']]);
        $department = trim((string) (Auth::currentUser()['department_code'] ?? ''));
        if ($department !== '' && !in_array((string) (Auth::currentUser()['role'] ?? ''), [Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN], true)) {
            $teams = array_values(array_filter($teams, static fn(array $row): bool => (string) $row['department_code'] === $department));
            $athletes = array_values(array_filter($athletes, static fn(array $row): bool => (string) $row['department_code'] === $department));
        }
        return ['teams' => $teams, 'athletes' => $athletes];
    }

    private static function assertEntryDepartmentScope(?string $departmentCode): void
    {
        $user = Auth::currentUser();
        if (in_array((string) ($user['role'] ?? ''), [Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN], true)) return;
        $scope = trim((string) ($user['department_code'] ?? ''));
        if ($scope !== '' && $scope !== (string) $departmentCode) {
            throw new RuntimeException('ผู้เข้าแข่งขันอยู่นอกสังกัดที่คุณรับผิดชอบ');
        }
    }

    public static function saveMatchEntry(array $payload): array
    {
        return MatchScoringGuard::transaction(static function () use ($payload): array {
            Auth::canManageResults();
            $matchId = trim((string) ($payload['match_id'] ?? ''));
            $match = MatchScoringGuard::lock($matchId);
            self::assertMatchResultAccess($match);
            self::matchScoringContext($match, $payload ?? []);
            self::assertRankingMatch($match);
            if ((string) $match['status'] === 'completed') throw new RuntimeException('ผลได้รับการยืนยันแล้ว');
            $type = strtolower(trim((string) ($payload['entry_type'] ?? 'athlete')));
            if (!in_array($type, ['athlete', 'team', 'affiliation'], true)) throw new InvalidArgumentException('ประเภทผู้เข้าแข่งขันไม่ถูกต้อง');
            $source = strtolower(trim((string) ($payload['source_type'] ?? 'manual')));
            if (!in_array($source, ['manual', 'import', 'roster'], true)) throw new InvalidArgumentException('แหล่งข้อมูลไม่ถูกต้อง');
            $studentId = self::nullableId($payload['student_id'] ?? null);
            $teamId = self::nullableId($payload['team_id'] ?? null);
            $department = trim((string) ($payload['department_code'] ?? ''));
            $label = trim((string) ($payload['display_name'] ?? ''));
            $members = self::payloadObject($payload['members'] ?? []);
            if ($studentId !== null) {
                if ($type !== 'athlete') throw new InvalidArgumentException('รหัสนักกีฬาใช้ได้เฉพาะผู้เข้าแข่งขันรายบุคคล');
                $student = self::findById('students', (string) $studentId);
                $department = (string) $student['department_code'];
                $label = trim(implode(' ', array_filter([$student['prefix'] ?? '', $student['first_name'] ?? '', $student['last_name'] ?? ''])));
                if (!self::fetchOne('SELECT team_members.id FROM team_members JOIN teams ON teams.id = team_members.team_id
                    WHERE team_members.student_id = :student AND teams.tournament_id = :tournament AND teams.sport_id = :sport LIMIT 1',
                    [':student' => $studentId, ':tournament' => $match['tournament_id'], ':sport' => $match['sport_id']])) {
                    throw new InvalidArgumentException('นักกีฬาไม่ได้ลงทะเบียนในรายการและกีฬาของแมตช์นี้');
                }
            }
            if ($teamId !== null) {
                $team = self::findById('teams', (string) $teamId);
                if ((string) $team['tournament_id'] !== (string) $match['tournament_id'] || (string) $team['sport_id'] !== (string) $match['sport_id']) {
                    throw new InvalidArgumentException('ทีมไม่อยู่ในรายการและกีฬาของแมตช์นี้');
                }
                if ($department !== '' && $department !== (string) $team['department_code']) throw new InvalidArgumentException('นักกีฬาและทีมอยู่คนละสังกัด');
                $department = (string) $team['department_code'];
                if ($type === 'team') $label = (string) $team['name'];
                if ($studentId !== null && !self::fetchOne('SELECT id FROM team_members WHERE team_id = :team AND student_id = :student',
                    [':team' => $teamId, ':student' => $studentId])) throw new InvalidArgumentException('นักกีฬาไม่ได้อยู่ในทีมที่เลือก');
                if ($type === 'team') {
                    $members = self::fetchAll('SELECT students.id, students.prefix, students.first_name, students.last_name
                        FROM team_members JOIN students ON students.id = team_members.student_id
                        WHERE team_members.team_id = :team ORDER BY team_members.id', [':team' => $teamId]);
                }
            }
            if ($type === 'team' && $teamId === null) throw new InvalidArgumentException('กรุณาเลือกทีมสำหรับวิ่งผลัด');
            if ($type === 'affiliation') {
                if ($department === '') throw new InvalidArgumentException('กรุณาเลือกสังกัด');
                $affiliation = self::fetchOne('SELECT name FROM departments WHERE code = :code', [':code' => $department]);
                if (!$affiliation) throw new InvalidArgumentException('ไม่พบสังกัด');
                $label = (string) $affiliation['name'];
                $studentId = null;
                $teamId = null;
            }
            if ($department !== '' && !self::fetchOne('SELECT code FROM departments WHERE code = :code', [':code' => $department])) {
                throw new InvalidArgumentException('ไม่พบสังกัด');
            }
            self::assertEntryDepartmentScope($department);
            if ($label === '') throw new InvalidArgumentException('กรุณาระบุชื่อผู้เข้าแข่งขัน');
            $lane = self::nullableInt($payload['lane_number'] ?? null);
            if ($lane !== null && ($lane < 1 || $lane > 100)) throw new InvalidArgumentException('ลู่ต้องอยู่ระหว่าง 1 ถึง 100');
            $currentId = trim((string) ($payload['id'] ?? ''));
            if ($currentId !== '') {
                $current = self::findById('match_entries', $currentId);
                if ((string) $current['match_id'] !== $matchId) throw new InvalidArgumentException('ผู้เข้าแข่งขันไม่อยู่ในแมตช์นี้');
                self::assertEntryDepartmentScope((string) ($current['department_code'] ?? ''));
            }
            foreach (self::fetchAll('SELECT id, lane_number, student_id, team_id, department_code, entry_type FROM match_entries WHERE match_id = :id', [':id' => $matchId]) as $entry) {
                if ((string) $entry['id'] === $currentId) continue;
                if ($lane !== null && (int) $entry['lane_number'] === $lane) throw new InvalidArgumentException('ลู่นี้มีผู้เข้าแข่งขันแล้ว');
                if ($studentId !== null && (string) $entry['student_id'] === (string) $studentId) throw new InvalidArgumentException('นักกีฬาซ้ำในแมตช์');
                if ($type === 'team' && $teamId !== null && (string) $entry['team_id'] === (string) $teamId && $entry['entry_type'] === 'team') throw new InvalidArgumentException('ทีมซ้ำในแมตช์');
                if ($type === 'affiliation' && $entry['entry_type'] === 'affiliation' && (string) $entry['department_code'] === $department) throw new InvalidArgumentException('สังกัดซ้ำในแมตช์');
            }
            $record = ['match_id' => $matchId, 'entry_type' => $type, 'student_id' => $studentId, 'team_id' => $teamId,
                'department_code' => $department ?: null, 'display_name' => $label, 'lane_number' => $lane,
                'source_type' => $source, 'members_json' => json_encode($members, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => Database::now()];
            if ($currentId !== '') {
                $row = self::updateRow('match_entries', $currentId, $record);
            } else {
                $record['id'] = self::nextId('match_entries');
                $record['created_at'] = Database::now();
                $record['result_status'] = 'PENDING';
                $record['points'] = 0;
                $row = self::insertRow('match_entries', $record);
            }
            self::audit($currentId !== '' ? 'update_entry' : 'create_entry', 'match', $matchId, $row);
            return $row;
        });
    }

    public static function importMatchEntries(array $payload): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', (string) ($payload['match_id'] ?? ''));
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload);
        $rows = $payload['rows'] ?? [];
        if (!is_array($rows) || !$rows || count($rows) > 200) throw new InvalidArgumentException('ข้อมูลนำเข้าต้องมี 1-200 แถว');
        $saved = [];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                if (!is_array($row)) throw new InvalidArgumentException('แถวนำเข้าไม่ถูกต้อง');
                $saved[] = self::saveMatchEntry(array_replace($row, ['match_id' => $payload['match_id'] ?? '', 'source_type' => 'import']));
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return ['created' => count($saved), 'entries' => $saved];
    }

    public static function recordMatchEntryResult(array $payload): array
    {
        Auth::canManageResults();
        $entry = self::findById('match_entries', (string) ($payload['id'] ?? ''));
        $match = self::findById('matches', (string) $entry['match_id']);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        self::assertRankingMatch($match);
        self::assertEntryDepartmentScope((string) ($entry['department_code'] ?? ''));
        if ((string) $match['status'] !== 'live') throw new RuntimeException('บันทึกผลได้เฉพาะแมตช์ที่กำลังแข่งขัน');
        $status = strtoupper(trim((string) ($payload['status'] ?? 'FINISHED')));
        if (!in_array($status, ['PENDING', 'FINISHED', 'DNS', 'DNF', 'DQ'], true)) throw new InvalidArgumentException('สถานะผลไม่ถูกต้อง');
        $verified = self::nullableInt($payload['verified_time_ms'] ?? null);
        $provisional = self::nullableInt($payload['provisional_time_ms'] ?? null);
        if ($verified !== null && $verified <= 0) throw new InvalidArgumentException('เวลาที่ตรวจสอบแล้วต้องมากกว่า 0');
        if ($provisional !== null && $provisional <= 0) throw new InvalidArgumentException('เวลาเบื้องต้นต้องมากกว่า 0');
        if ($status === 'FINISHED' && $verified === null && $provisional === null) throw new InvalidArgumentException('กรุณาระบุเวลาเข้าเส้นชัย');
        if ($status !== 'FINISHED') $verified = null;
        $patch = ['result_status' => $status, 'verified_time_ms' => $verified, 'provisional_time_ms' => $provisional];
        $expectedRevision = (int) ($payload['expected_revision'] ?? $entry['revision_number'] ?? 0);
        if ($expectedRevision !== (int) ($entry['revision_number'] ?? 0)) {
            throw new RuntimeException('ผลถูกแก้ไขโดยผู้ใช้อื่นแล้ว กรุณารีเฟรชก่อนบันทึกอีกครั้ง');
        }
        $reason = trim((string) ($payload['reason'] ?? ''));
        $changed = (string) $entry['result_status'] !== $status || (string) ($entry['verified_time_ms'] ?? '') !== (string) ($verified ?? '')
            || (string) ($entry['provisional_time_ms'] ?? '') !== (string) ($provisional ?? '');
        if (!$changed) return $entry;
        if ((string) $entry['result_status'] !== 'PENDING' && $reason === '') throw new InvalidArgumentException('กรุณาระบุเหตุผลในการแก้ผล');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload ?? []);
            self::insertRow('match_entry_revisions', ['id' => self::nextId('match_entry_revisions'), 'entry_id' => $entry['id'],
                'old_value' => json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'new_value' => json_encode($patch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'reason' => $reason !== '' ? $reason : 'บันทึกผลครั้งแรก',
                'updated_by' => Auth::currentUser()['email'] ?? '', 'created_at' => Database::now()]);
            $update = $pdo->prepare('UPDATE match_entries SET result_status = :status, verified_time_ms = :verified,
                provisional_time_ms = :provisional, revision_number = revision_number + 1, updated_at = :now
                WHERE id = :id AND revision_number = :expected');
            $update->execute([':status' => $status, ':verified' => $verified, ':provisional' => $provisional,
                ':now' => Database::now(), ':id' => $entry['id'], ':expected' => $expectedRevision]);
            if ($update->rowCount() !== 1) throw new RuntimeException('ผลถูกแก้ไขโดยผู้ใช้อื่นแล้ว กรุณารีเฟรชก่อนบันทึกอีกครั้ง');
            $row = self::findById('match_entries', (string) $entry['id']);
            self::rankMatchEntries((string) $match['id']);
            self::audit('record_entry_result', 'match', (string) $match['id'], ['entry_id' => $entry['id'], 'old' => $entry, 'new' => $row, 'reason' => $reason]);
            $pdo->commit();
            return self::findById('match_entries', (string) $entry['id']);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    private static function rankMatchEntries(string $matchId): void
    {
        $match = self::findById('matches', $matchId);
        $rule = self::matchScoringContext($match)['rule'];
        $awards = $rule['scoring_config_data']['rank_points'] ?? [5, 3, 1];
        if (!is_array($awards) || count($awards) > 100 || array_filter($awards, static fn($points): bool => !is_numeric($points) || (int) $points < 0)) {
            throw new InvalidArgumentException('เกณฑ์คะแนนตามอันดับไม่ถูกต้อง');
        }
        $rows = self::fetchAll('SELECT id, result_status, verified_time_ms FROM match_entries WHERE match_id = :id
            ORDER BY verified_time_ms, id', [':id' => $matchId]);
        $times = [];
        foreach ($rows as $row) {
            if ($row['result_status'] === 'FINISHED' && $row['verified_time_ms'] !== null) $times[] = (int) $row['verified_time_ms'];
        }
        sort($times, SORT_NUMERIC);
        $update = Database::pdo()->prepare('UPDATE match_entries SET rank_number = :rank, points = :points WHERE id = :id');
        foreach ($rows as $row) {
            $rank = $row['result_status'] === 'FINISHED' && $row['verified_time_ms'] !== null
                ? array_search((int) $row['verified_time_ms'], $times, true) + 1 : null;
            $update->execute([':rank' => $rank, ':points' => $rank !== null ? ($awards[$rank - 1] ?? 0) : 0, ':id' => $row['id']]);
        }
    }

    public static function confirmMatchEntries(string $matchId): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
        self::matchScoringContext($match, $payload ?? []);
        self::assertRankingMatch($match);
        if ((string) $match['status'] !== 'live') throw new RuntimeException('ยืนยันผลได้เฉพาะแมตช์ที่กำลังแข่งขัน');
        $entries = self::matchEntries($matchId);
        if (count($entries) < 2) throw new InvalidArgumentException('ต้องมีผู้เข้าแข่งขันอย่างน้อย 2 ราย');
        $user = Auth::currentUser();
        $department = trim((string) ($user['department_code'] ?? ''));
        if ($department !== '' && !in_array((string) ($user['role'] ?? ''), [Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN], true)) {
            foreach ($entries as $entry) {
                if ((string) ($entry['department_code'] ?? '') !== $department) {
                    throw new RuntimeException('ผลรวมมีผู้เข้าแข่งขันนอกสังกัดของคุณ ต้องให้เจ้าหน้าที่กลางหรือผู้ดูแลยืนยัน');
                }
            }
        }
        foreach ($entries as $entry) {
            if ($entry['result_status'] === 'PENDING' || ($entry['result_status'] === 'FINISHED' && $entry['verified_time_ms'] === null)) {
                throw new InvalidArgumentException('กรุณาตรวจสอบผลทุกคนก่อนยืนยัน');
            }
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertScoringWriteCurrent($match, $payload ?? []);
            self::rankMatchEntries($matchId);
            $winner = self::fetchOne('SELECT team_id FROM match_entries WHERE match_id = :id AND rank_number = 1 ORDER BY id LIMIT 1', [':id' => $matchId]);
            $result = self::updateRow('matches', $matchId, ['status' => 'completed', 'clock_status' => 'stopped',
                'winner_team_id' => $winner['team_id'] ?? null, 'confirmed_by' => Auth::currentUser()['email'] ?? '', 'confirmed_at' => Database::now()]);
            self::audit('confirm_entry_results', 'match', $matchId, ['entries' => count($entries)]);
            $pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

}
