<?php
declare(strict_types=1);

final class AppService
{
    private const AI_MODELS = [
        'local-summary',
        'gemini-3.8-flash',
        'gemini-3.5-flash',
        'gemini-3.5-flash-lite',
        'gemini-2.0-flash',
    ];

    public static function handle(string $action, array $payload = [], array $files = []): array
    {
        switch ($action) {
            case 'auth.current':
                return ['user' => Auth::currentUser(), 'csrf' => Auth::csrfToken()];
            case 'auth.login':
            case 'verifyTeamLogin':
                return Auth::login(self::legacyArg($payload));
            case 'auth.logout':
                return Auth::logout();
            case 'auth.profile':
                return self::updateProfile($payload);
            case 'invites.preview':
                return self::invitePreview((string) ($payload['token'] ?? self::legacyArg($payload, 0, '')));
            case 'invites.accept':
                return self::acceptInvite(isset($payload['args']) ? self::legacyArg($payload) : $payload);

            case 'setupMvpDatabase':
            case 'setupDatabase':
                Auth::canManageMasterData();
                return ['ok' => true, 'database' => Database::info()];
            case 'getDatabaseInfo':
                Auth::canManageMasterData();
                return ['database' => Database::info()];

            case 'public.home':
            case 'getPublicHomeData':
                return self::publicHomeData();
            case 'public.sports':
            case 'getPublicSports':
                return self::publicSports();
            case 'public.tournaments':
            case 'getPublicTournaments':
                return self::publicTournaments();
            case 'public.schedule':
            case 'getPublicSchedule':
                return self::publicSchedule(self::legacyArg($payload));
            case 'public.results':
            case 'getPublicResults':
                return self::publicResults(self::legacyArg($payload));
            case 'public.bracket':
                return self::publicBracket($payload);
            case 'public.standings':
                return self::publicStandings((string) ($payload['tournament_id'] ?? ''));
            case 'public.news':
            case 'getPublishedNews':
                return self::publishedNews(self::legacyArg($payload));
            case 'public.hall':
            case 'getPublishedHallOfFame':
                return self::publishedHallOfFame();
            case 'public.athletes':
            case 'getPublicAthleteBoards':
                return self::publicAthleteBoards(self::legacyArg($payload));

            case 'dashboard.summary':
            case 'getDashboardSummary':
                return self::dashboardSummary();

            case 'departments.list':
            case 'listDepartments':
                return self::listDepartments();
            case 'organizations.list':
                return self::listOrganizations();
            case 'organizations.save':
                return self::saveOrganization($payload);
            case 'media.upload':
                return self::uploadMedia($payload);

            case 'sports.list':
            case 'listSports':
                return self::listSports();
            case 'sports.save':
                return empty($payload['id']) ? self::createSport($payload) : self::updateSport((string) $payload['id'], $payload);
            case 'createSport':
                return self::createSport(self::legacyArg($payload));
            case 'updateSport':
                return self::updateSport((string) self::legacyArg($payload, 0), self::legacyArg($payload, 1));
            case 'toggleSport':
                return self::toggleSport((string) self::legacyArg($payload, 0), self::legacyArg($payload, 1));
            case 'sports.delete':
            case 'deleteSport':
                return self::deleteSport((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'sportRules.list':
                return self::listSportRules((string) ($payload['sport_id'] ?? ''));
            case 'sportRules.save':
                return self::saveSportRule($payload);

            case 'tournaments.list':
            case 'listTournaments':
                return self::listTournaments();
            case 'tournaments.save':
                return empty($payload['id']) ? self::createTournament($payload) : self::updateTournament((string) $payload['id'], $payload);
            case 'createTournament':
                return self::createTournament(self::legacyArg($payload));
            case 'updateTournament':
                return self::updateTournament((string) self::legacyArg($payload, 0), self::legacyArg($payload, 1));
            case 'tournaments.delete':
            case 'deleteTournament':
                return self::deleteTournament((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'tournaments.workspace':
                return self::tournamentWorkspace((string) ($payload['tournament_id'] ?? ''));
            case 'tournaments.sportSetting':
                return self::saveTournamentSportSetting($payload);
            case 'tournaments.generateFixtures':
                return self::generateTournamentFixtures($payload);
            case 'tournaments.departments':
                return self::saveTournamentDepartments($payload);

            case 'students.list':
            case 'listStudents':
                return self::listStudents(self::legacyArg($payload));
            case 'students.save':
                return empty($payload['id']) ? self::createStudent($payload) : self::updateStudent((string) $payload['id'], $payload);
            case 'createStudent':
                return self::createStudent(self::legacyArg($payload));
            case 'updateStudent':
                return self::updateStudent((string) self::legacyArg($payload, 0), self::legacyArg($payload, 1));
            case 'students.delete':
            case 'deleteStudent':
                return self::deleteStudent((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'students.import':
            case 'importStudentsCsv':
                return self::importStudentsCsv($payload['rows'] ?? self::legacyArg($payload, 0, []));

            case 'teams.list':
            case 'listTeams':
                return self::listTeams((string) ($payload['tournament_id'] ?? self::legacyArg($payload, 0, '')));
            case 'teams.save':
                return empty($payload['id']) ? self::createTeam($payload) : self::updateTeam((string) $payload['id'], $payload);
            case 'createTeam':
                return self::createTeam(self::legacyArg($payload));
            case 'updateTeam':
                return self::updateTeam((string) self::legacyArg($payload, 0), self::legacyArg($payload, 1));
            case 'teams.delete':
            case 'deleteTeam':
                return self::deleteTeam((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'teams.status':
            case 'updateTeamStatus':
                return self::updateTeamStatus(
                    (string) ($payload['id'] ?? self::legacyArg($payload, 0)),
                    (string) ($payload['status'] ?? self::legacyArg($payload, 1)),
                    (string) ($payload['reason'] ?? self::legacyArg($payload, 2, ''))
                );

            case 'teamMembers.list':
            case 'listTeamMembers':
                return self::listTeamMembers((string) ($payload['team_id'] ?? self::legacyArg($payload, 0, '')));
            case 'teamMembers.import':
            case 'importTeamMembers':
                return self::importTeamMembers($payload['rows'] ?? self::legacyArg($payload, 0, []));
            case 'teamMembers.save':
                return empty($payload['id']) ? self::importTeamMembers([$payload]) : self::updateTeamMember((string) $payload['id'], $payload);
            case 'updateTeamMember':
                return self::updateTeamMember((string) self::legacyArg($payload, 0), self::legacyArg($payload, 1));
            case 'teamMembers.delete':
            case 'deleteTeamMember':
                return self::deleteTeamMember((string) ($payload['id'] ?? self::legacyArg($payload, 0)));

            case 'matches.list':
            case 'listMatches':
                return self::listMatches((string) ($payload['tournament_id'] ?? self::legacyArg($payload, 0, '')));
            case 'matches.forNews':
            case 'listMatchesForNews':
                return self::listMatchesForNews();
            case 'matches.save':
                return empty($payload['id']) ? self::createMatch($payload) : self::updateMatch((string) $payload['id'], $payload);
            case 'createMatch':
                return self::createMatch(self::legacyArg($payload));
            case 'updateMatch':
                return self::updateMatch((string) self::legacyArg($payload, 0), self::legacyArg($payload, 1));
            case 'matches.delete':
            case 'deleteMatch':
                return self::deleteMatch((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'matches.result':
            case 'saveMatchResult':
                return self::saveMatchResult(
                    (string) ($payload['match_id'] ?? self::legacyArg($payload, 0)),
                    isset($payload['args']) ? (array) self::legacyArg($payload, 1, []) : ($payload['data'] ?? $payload)
                );
            case 'matches.center':
                return self::matchCenter((string) ($payload['match_id'] ?? ''));
            case 'matches.clock':
                return self::updateMatchClock((string) ($payload['match_id'] ?? ''), $payload);
            case 'matches.event':
                return self::saveMatchEvent($payload);
            case 'matches.eventVoid':
                return self::voidMatchEvent((string) ($payload['id'] ?? ''));
            case 'standings.list':
                return self::listStandings((string) ($payload['tournament_id'] ?? ''));

            case 'news.list':
            case 'listNews':
                return self::listNews();
            case 'news.save':
            case 'saveNews':
                return self::saveNews(isset($payload['args']) ? self::legacyArg($payload) : $payload);
            case 'news.publish':
            case 'publishNews':
                return self::publishNews((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'news.delete':
            case 'deleteNews':
                return self::deleteNews((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'news.upload':
            case 'uploadNewsImage':
                return self::uploadNewsImage($payload, $files);

            case 'users.list':
            case 'listUsers':
                return self::listUsers();
            case 'users.options':
            case 'getUserFormOptions':
                return self::userFormOptions();
            case 'users.save':
            case 'saveUser':
                return self::saveUser(isset($payload['args']) ? self::legacyArg($payload) : $payload);
            case 'users.delete':
            case 'deleteUser':
                return self::deleteUser((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'invites.list':
                return self::listInvites();
            case 'invites.create':
                return self::createInvite(isset($payload['args']) ? self::legacyArg($payload) : $payload);
            case 'invites.revoke':
                return self::revokeInvite((string) ($payload['id'] ?? self::legacyArg($payload, 0)));
            case 'invites.toggle':
                return self::setInvitesEnabled($payload);
            case 'audit.list':
                return self::listAuditLogs($payload);

            case 'ai.models':
            case 'getAiTaskModels':
            case 'getReportAiSettings':
                return self::aiTaskModels();
            case 'ai.test':
            case 'testAiConnection':
                return self::testAiConnection();
            case 'ai.dashboard':
            case 'generateAiDashboardBrief':
                return self::generateDashboardBrief();
            case 'ai.system':
            case 'generateAiSystemBrief':
                return self::generateSystemBrief((string) ($payload['instruction'] ?? self::legacyArg($payload, 0, '')));
            case 'ai.csv':
            case 'validateAiCsv':
                return self::validateCsv((string) ($payload['kind'] ?? self::legacyArg($payload, 0, '')), $payload['rows'] ?? self::legacyArg($payload, 1, []));
            case 'ai.news':
            case 'generateAiNewsDraft':
                return self::generateNewsDraft(isset($payload['args'])
                    ? ['match_id' => (string) self::legacyArg($payload, 0, '')]
                    : $payload);
            case 'reports.options':
                return self::reportOptions();
            case 'reports.list':
                return self::listReports($payload);
            case 'reports.get':
                return self::getReport((string) ($payload['id'] ?? ''));
            case 'reports.save':
                return self::saveReport($payload);
            case 'reports.status':
                return self::updateReportStatus((string) ($payload['id'] ?? ''), (string) ($payload['status'] ?? ''));
            case 'reports.delete':
                return self::deleteReport((string) ($payload['id'] ?? ''));
            case 'reports.export':
                return self::exportStoredReport((string) ($payload['id'] ?? ''));
            case 'reports.attachment':
                return self::uploadReportAttachment($payload, $files);
            case 'reports.aiDraft':
            case 'generateReportFormDraft':
            case 'reports.summarize':
                return self::generateReportFormDraft($payload);
            case 'reports.generate':
            case 'generateFormalAiReport':
                return self::generateFormalReport(isset($payload['args']) ? self::legacyArg($payload) : $payload);
        }

        throw new InvalidArgumentException('ไม่พบ action: ' . $action);
    }

    public static function settings(): array
    {
        $settings = [
            'app_name' => 'Sport Tournament',
            'faculty_code' => '102',
            'current_academic_year' => '69',
            'staff_invites_enabled' => '1',
        ];
        $settingsKey = Database::isMysql() ? '`key`' : 'key';
        foreach (self::fetchAll("SELECT {$settingsKey}, value FROM settings") as $row) {
            $settings[(string) $row['key']] = $row['value'];
        }
        if ($settings['app_name'] === 'RMUTT Sports MVP') {
            $settings['app_name'] = 'Sport Tournament';
        }
        return $settings;
    }

    public static function publicHomeData(): array
    {
        $sports = self::publicSports();
        $schedule = self::publicSchedule([]);
        $results = self::publicResults([]);
        $news = self::publishedNews();
        $hall = self::publishedHallOfFame();
        return [
            'app' => self::settings(),
            'tournaments' => self::publicTournaments(),
            'sports' => $sports,
            'schedule' => $schedule,
            'results' => $results,
            'news' => $news,
            'hallOfFame' => $hall,
            'summary' => [
                'sports' => count($sports),
                'scheduledMatches' => count($schedule),
                'completedMatches' => count($results),
                'news' => count($news),
            ],
        ];
    }

    public static function publicSports(): array
    {
        return self::fetchAll('SELECT * FROM sports WHERE is_active = 1 ORDER BY LOWER(name) ASC');
    }

    public static function publicTournaments(): array
    {
        $sportIds = self::groupConcat('ts2.sport_id');
        $sportNames = self::groupConcat('s2.name', ' · ');
        return self::enrichedTournamentRows(self::fetchAll(
            "SELECT tournaments.*, sports.name AS primary_sport_name,
                    (SELECT {$sportIds} FROM tournament_sports ts2 WHERE ts2.tournament_id = tournaments.id) AS sport_ids_csv,
                    (SELECT {$sportNames} FROM tournament_sports ts2
                     JOIN sports s2 ON s2.id = ts2.sport_id WHERE ts2.tournament_id = tournaments.id) AS sport_names
             FROM tournaments
             LEFT JOIN sports ON sports.id = tournaments.sport_id
             WHERE tournaments.status NOT IN ('draft', 'cancelled')
             ORDER BY COALESCE(NULLIF(tournaments.starts_at, ''), tournaments.created_at) DESC"
        ));
    }

    public static function publicSchedule(array $filters): array
    {
        $rows = array_values(array_filter(self::joinedMatches(), function (array $row) use ($filters): bool {
            if (in_array((string) ($row['tournament_status'] ?? ''), ['draft', 'cancelled'], true)) {
                return false;
            }
            if (in_array((string) $row['status'], ['completed', 'cancelled'], true)) {
                return false;
            }
            if (empty($row['team1_id']) || empty($row['team2_id'])) {
                return false;
            }
            return empty($filters['tournament_id']) || (string) $row['tournament_id'] === (string) $filters['tournament_id'];
        }));
        usort($rows, fn(array $a, array $b): int => strcmp((string) ($a['scheduled_at'] ?? ''), (string) ($b['scheduled_at'] ?? '')));
        return array_slice($rows, 0, 50);
    }

    public static function publicResults(array $filters): array
    {
        $rows = array_values(array_filter(self::joinedMatches(), function (array $row) use ($filters): bool {
            if (in_array((string) ($row['tournament_status'] ?? ''), ['draft', 'cancelled'], true)) {
                return false;
            }
            if ((string) $row['status'] !== 'completed') {
                return false;
            }
            return empty($filters['tournament_id']) || (string) $row['tournament_id'] === (string) $filters['tournament_id'];
        }));
        usort($rows, fn(array $a, array $b): int => strcmp((string) ($b['confirmed_at'] ?: $b['updated_at']), (string) ($a['confirmed_at'] ?: $a['updated_at'])));
        return array_slice($rows, 0, 50);
    }

    public static function publicBracket(array $filters): array
    {
        $tournamentId = (string) ($filters['tournament_id'] ?? '');
        $sportId = (string) ($filters['sport_id'] ?? '');
        if ($tournamentId === '') {
            return [];
        }
        $visible = self::fetchOne("SELECT id FROM tournaments WHERE id = :id AND status NOT IN ('draft', 'cancelled')", [':id' => $tournamentId]);
        if (!$visible) {
            return [];
        }
        $rounds = [];
        foreach (array_reverse(self::joinedMatches()) as $match) {
            if ((string) $match['tournament_id'] !== $tournamentId
                || (string) $match['status'] === 'cancelled'
                || ($sportId !== '' && (string) ($match['sport_id'] ?? '') !== $sportId)) {
                continue;
            }
            $round = trim((string) ($match['round_name'] ?? '')) ?: 'รอบแรก';
            $rounds[$round][] = $match;
        }
        foreach ($rounds as &$matches) {
            usort($matches, static fn(array $a, array $b): int => ((int) $a['match_number'] <=> (int) $b['match_number']) ?: ((int) $a['id'] <=> (int) $b['id']));
        }
        unset($matches);
        return array_map(static fn(string $name, array $matches): array => ['name' => $name, 'matches' => $matches], array_keys($rounds), array_values($rounds));
    }

    public static function publicStandings(string $tournamentId): array
    {
        if ($tournamentId === '') {
            return [];
        }
        return self::fetchAll(
            'SELECT standings.*, teams.name AS team_name, teams.logo_url, teams.sport_id,
                    sports.name AS sport_name, departments.name AS department_name,
                    (standings.scored - standings.conceded) AS score_difference
             FROM standings
             JOIN teams ON teams.id = standings.team_id
             LEFT JOIN sports ON sports.id = teams.sport_id
             LEFT JOIN departments ON departments.code = teams.department_code
             JOIN tournaments ON tournaments.id = standings.tournament_id
             WHERE standings.tournament_id = :tournament_id
               AND tournaments.status NOT IN (\'draft\', \'cancelled\')
             ORDER BY sports.name, standings.rank_number, teams.name',
            [':tournament_id' => $tournamentId]
        );
    }

    public static function publishedNews(array $filters = []): array
    {
        $id = trim((string) ($filters['id'] ?? ''));
        $sql = $id !== ''
            ? "SELECT * FROM news WHERE status = 'published'"
            : "SELECT * FROM news WHERE status = 'published' AND news_type != 'hall_of_fame'";
        $rows = self::enrichedNewsRows(self::fetchAll($sql));
        if ($id !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['id'] ?? '') === $id));
        }
        $tournamentId = trim((string) ($filters['tournament_id'] ?? ''));
        if ($tournamentId !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['tournament_id'] ?? '') === $tournamentId));
        }
        usort($rows, [self::class, 'sortPinnedNews']);
        return array_slice($rows, 0, 20);
    }

    public static function publishedHallOfFame(): array
    {
        $rows = self::enrichedNewsRows(self::fetchAll("SELECT * FROM news WHERE status = 'published' AND news_type = 'hall_of_fame'"));
        usort($rows, [self::class, 'sortPinnedNews']);
        return array_slice($rows, 0, 6);
    }

    public static function publicAthleteBoards(array $filters): array
    {
        $sportId = (string) ($filters['sport_id'] ?? '');
        $teamId = (string) ($filters['team_id'] ?? '');
        $members = [];
        foreach (self::joinedTeamMembers('') as $member) {
            $members[(string) $member['team_id']][] = $member;
        }

        $tournamentId = (string) ($filters['tournament_id'] ?? '');
        $teams = array_values(array_filter(self::joinedTeams(), function (array $team) use ($sportId, $teamId, $tournamentId): bool {
            if (in_array((string) $team['status'], ['rejected', 'withdrawn'], true)) {
                return false;
            }
            if (in_array((string) ($team['tournament_status'] ?? ''), ['draft', 'cancelled'], true)) {
                return false;
            }
            if ($tournamentId !== '' && (string) $team['tournament_id'] !== $tournamentId) {
                return false;
            }
            if ($sportId !== '' && (string) ($team['sport_id'] ?? '') !== $sportId) {
                return false;
            }
            if ($teamId !== '' && (string) $team['id'] !== $teamId) {
                return false;
            }
            return true;
        }));

        $boards = [];
        foreach ($teams as $team) {
            $players = $members[(string) $team['id']] ?? [];
            foreach ($players as &$player) {
                unset($player['photo_url']);
            }
            unset($player);
            usort($players, fn(array $a, array $b): int => self::positionSort((string) ($a['field_position'] ?? ''), 0) <=> self::positionSort((string) ($b['field_position'] ?? ''), 0));
            $boards[] = [
                'team_id' => $team['id'],
                'team_name' => $team['name'],
                'team_status' => $team['status'],
                'department_code' => $team['department_code'],
                'department_name' => $team['department_name'],
                'tournament_id' => $team['tournament_id'],
                'tournament_name' => $team['tournament_name'],
                'sport_id' => $team['sport_id'],
                'sport_name' => $team['sport_name'],
                'sport_icon' => $team['sport_icon'],
                'sport_logo_url' => $team['sport_logo_url'] ?? '',
                'sport_type' => $team['sport_type'],
                'sport_gender' => $team['sport_gender'] ?? 'open',
                'sport_max_players' => (int) ($team['sport_max_players'] ?? 1),
                'team_logo_url' => $team['logo_url'] ?? '',
                'faculty_name' => $team['faculty_name'] ?? '',
                'university_name' => $team['university_name'] ?? '',
                'players' => $players,
            ];
        }

        usort($boards, fn(array $a, array $b): int => strcasecmp((string) ($a['sport_name'] . $a['team_name']), (string) ($b['sport_name'] . $b['team_name'])));
        return ['sports' => self::publicSports(), 'teams' => $boards];
    }

    public static function dashboardSummary(): array
    {
        Auth::requireRole([
            Auth::ROLE_SUPER_ADMIN,
            Auth::ROLE_ADMIN,
            Auth::ROLE_SPORT_HEAD,
            Auth::ROLE_REGISTRATION_OFFICER,
            Auth::ROLE_RESULT_OFFICER,
            Auth::ROLE_PUBLIC_RELATIONS,
        ]);
        return [
            'user' => Auth::currentUser(),
            'counts' => [
                'sports' => self::countRows('sports'),
                'activeSports' => self::countRows('sports', 'is_active = 1'),
                'teams' => self::countRows('teams'),
                'approvedTeams' => self::countRows('teams', "status = 'approved'"),
                'students' => self::countRows('students'),
                'tournaments' => self::countRows('tournaments'),
                'matches' => self::countRows('matches'),
                'completedMatches' => self::countRows('matches', "status = 'completed'"),
            ],
            'recentAudit' => (Auth::currentUser()['role'] ?? '') === Auth::ROLE_SUPER_ADMIN
                ? self::fetchAll('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 10') : [],
        ];
    }

    public static function listDepartments(): array
    {
        Auth::requireRole([Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN, Auth::ROLE_SPORT_HEAD, Auth::ROLE_PUBLIC_RELATIONS, Auth::ROLE_REGISTRATION_OFFICER]);
        return self::activeDepartments();
    }

    public static function listOrganizations(): array
    {
        Auth::canViewSportsWork();
        return [
            'universities' => self::fetchAll('SELECT * FROM universities ORDER BY name'),
            'faculties' => self::fetchAll(
                'SELECT faculties.*, universities.name AS university_name
                 FROM faculties JOIN universities ON universities.id = faculties.university_id ORDER BY faculties.name'
            ),
            'departments' => self::fetchAll(
                'SELECT departments.*, faculties.name AS faculty_name, universities.name AS university_name
                 FROM departments
                 LEFT JOIN faculties ON faculties.id = departments.faculty_id
                 LEFT JOIN universities ON universities.id = faculties.university_id
                 ORDER BY departments.name'
            ),
        ];
    }

    public static function saveOrganization(array $payload): array
    {
        Auth::canManageMasterData();
        $type = (string) ($payload['entity_type'] ?? 'department');
        $table = ['university' => 'universities', 'faculty' => 'faculties', 'department' => 'departments'][$type] ?? '';
        if ($table === '') {
            throw new InvalidArgumentException('ประเภทหน่วยงานไม่ถูกต้อง');
        }
        $name = trim((string) ($payload['name'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        if ($name === '' || $code === '') {
            throw new InvalidArgumentException('กรุณาระบุรหัสและชื่อหน่วยงาน');
        }
        $record = [
            'code' => $code,
            'name' => $name,
            'logo_url' => trim((string) ($payload['logo_url'] ?? '')),
            'is_active' => self::boolToInt($payload['is_active'] ?? true),
        ];
        if ($type === 'faculty') {
            $record['university_id'] = (int) ($payload['university_id'] ?? 0);
            self::findById('universities', (string) $record['university_id']);
        } elseif ($type === 'department') {
            $record['faculty_id'] = self::nullableId($payload['faculty_id'] ?? null);
            if ($record['faculty_id'] !== null) {
                self::findById('faculties', (string) $record['faculty_id']);
            }
        }
        $id = trim((string) ($payload['id'] ?? ''));
        if ($id !== '') {
            $row = self::updateRow($table, $id, $record);
            self::audit('update', $type, $id, $row);
            return $row;
        }
        $record['id'] = self::nextId($table);
        if ($table !== 'departments') {
            $record['created_at'] = Database::now();
            $record['updated_at'] = Database::now();
        }
        $row = self::insertRow($table, $record);
        self::audit('create', $type, (string) $row['id'], $row);
        return $row;
    }

    public static function uploadMedia(array $payload): array
    {
        $kind = (string) ($payload['kind'] ?? 'logo');
        if ($kind === 'profile') {
            Auth::canManageRegistration();
        } else {
            Auth::requireRole([Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN, Auth::ROLE_SPORT_HEAD, Auth::ROLE_PUBLIC_RELATIONS]);
        }
        $dataUrl = (string) ($payload['data_url'] ?? '');
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $dataUrl, $match)) {
            throw new InvalidArgumentException('รูปภาพไม่ถูกต้อง');
        }
        $bytes = base64_decode($match[2], true);
        $mime = strtolower($match[1]);
        if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024 || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('รองรับ JPG, PNG หรือ WEBP ขนาดไม่เกิน 5MB');
        }
        $folder = $kind === 'profile' ? 'profiles' : 'logos';
        $prefix = $kind === 'profile' ? 'profile' : 'logo';
        $directory = UPLOAD_PATH . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์รูปภาพได้');
        }
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $name = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        file_put_contents($directory . DIRECTORY_SEPARATOR . $name, $bytes);
        $url = app_url('public/uploads/' . $folder . '/' . $name);
        self::audit($kind === 'profile' ? 'upload_profile' : 'upload_logo', 'media', $name, ['url' => $url]);
        return ['url' => $url];
    }

    public static function listSportRules(string $sportId): array
    {
        Auth::canViewSportsWork();
        $params = [];
        $where = '';
        if ($sportId !== '') {
            $where = ' WHERE sport_rule_versions.sport_id = :sport_id';
            $params[':sport_id'] = $sportId;
        }
        $rows = self::fetchAll(
            'SELECT sport_rule_versions.*, sports.name AS sport_name,
                    (SELECT COUNT(*) FROM tournament_sport_settings tss WHERE tss.rule_version_id = sport_rule_versions.id) AS tournament_usage_count,
                    (SELECT COUNT(*) FROM matches m WHERE m.rule_version_id = sport_rule_versions.id) AS match_usage_count
             FROM sport_rule_versions
             JOIN sports ON sports.id = sport_rule_versions.sport_id' . $where . '
             ORDER BY sport_rule_versions.sport_id, sport_rule_versions.version_number DESC',
            $params
        );
        return array_map([self::class, 'decodeSportRule'], $rows);
    }

    public static function saveSportRule(array $payload): array
    {
        Auth::canManageMasterData();
        $sportId = trim((string) ($payload['sport_id'] ?? ''));
        $sport = self::findById('sports', $sportId);
        $teamSize = max(1, (int) ($payload['team_size'] ?? $sport['max_players'] ?? 1));
        $periodCount = max(1, (int) ($payload['period_count'] ?? 2));
        $periodMinutes = max(0, (int) ($payload['period_minutes'] ?? 45));
        $eventTypes = self::payloadArray($payload['event_types'] ?? []);
        if (!$eventTypes) {
            $eventTypes = self::defaultEventTypes();
        }
        $eventTypes = array_values(array_map(static function (array $event): array {
            $participant = (string) ($event['participant'] ?? 'optional');
            return [
                'code' => trim((string) ($event['code'] ?? '')),
                'label' => trim((string) ($event['label'] ?? '')),
                'points' => (int) ($event['points'] ?? 0),
                'participant' => in_array($participant, ['athlete', 'team', 'optional'], true) ? $participant : 'optional',
            ];
        }, array_filter($eventTypes, static fn($event): bool => is_array($event))));
        $id = trim((string) ($payload['id'] ?? ''));
        if ($id === '') {
            $version = (int) (self::fetchOne('SELECT MAX(version_number) AS version FROM sport_rule_versions WHERE sport_id = :sport_id', [':sport_id' => $sportId])['version'] ?? 0) + 1;
        } else {
            $current = self::findById('sport_rule_versions', $id);
            if ((string) $current['sport_id'] !== $sportId) {
                throw new InvalidArgumentException('ไม่สามารถย้ายชุดกติกาไปยังกีฬาอื่นได้ กรุณาสร้างชุดกติกาใหม่');
            }
            $usage = (int) (self::fetchOne(
                'SELECT (SELECT COUNT(*) FROM tournament_sport_settings WHERE rule_version_id = :rule_id_1)
                      + (SELECT COUNT(*) FROM matches WHERE rule_version_id = :rule_id_2) AS total',
                [':rule_id_1' => $id, ':rule_id_2' => $id]
            )['total'] ?? 0);
            if ($usage > 0) {
                throw new RuntimeException('ชุดกติกานี้ถูกนำไปใช้แล้ว จึงแก้ทับไม่ได้ กรุณาใช้คำสั่ง “สร้างเวอร์ชันใหม่”');
            }
            $version = (int) $current['version_number'];
        }
        $record = [
            'sport_id' => $sportId,
            'version_number' => $version,
            'name' => trim((string) ($payload['name'] ?? 'กติกามาตรฐาน')) ?: 'กติกามาตรฐาน',
            'category' => trim((string) ($payload['category'] ?? $sport['type'])),
            'player_type' => (string) ($payload['player_type'] ?? $sport['type']),
            'team_size' => $teamSize,
            'scoring_system' => json_encode([
                'mode' => (string) ($payload['scoring_mode'] ?? 'points'),
                'win_by' => (string) ($payload['win_by'] ?? 'highest'),
                'event_points' => max(0, (int) ($payload['event_points'] ?? 1)),
            ], JSON_UNESCAPED_UNICODE),
            'match_format' => json_encode(['allow_draw' => self::boolToInt($payload['allow_draw'] ?? false) === 1], JSON_UNESCAPED_UNICODE),
            'period_format' => json_encode(['count' => $periodCount, 'duration_minutes' => $periodMinutes], JSON_UNESCAPED_UNICODE),
            'overtime_rules' => json_encode([
                'enabled' => self::boolToInt($payload['overtime_enabled'] ?? false) === 1,
                'periods' => max(1, (int) ($payload['overtime_periods'] ?? 2)),
                'duration_minutes' => max(1, (int) ($payload['overtime_minutes'] ?? 15)),
            ], JSON_UNESCAPED_UNICODE),
            'tie_break_rules' => json_encode(['method' => (string) ($payload['tie_break_method'] ?? 'draw')], JSON_UNESCAPED_UNICODE),
            'ranking_rules' => json_encode([
                'win_points' => (int) ($payload['win_points'] ?? 3),
                'draw_points' => (int) ($payload['draw_points'] ?? 1),
                'loss_points' => (int) ($payload['loss_points'] ?? 0),
                'order' => ['points', 'score_difference', 'scored'],
            ], JSON_UNESCAPED_UNICODE),
            'event_types' => json_encode($eventTypes, JSON_UNESCAPED_UNICODE),
            'custom_rules' => json_encode(['note' => (string) ($payload['custom_rules'] ?? '')], JSON_UNESCAPED_UNICODE),
            'is_active' => self::boolToInt($payload['is_active'] ?? true),
            'created_by' => Auth::currentUser()['email'] ?? '',
        ];
        if ($record['is_active'] === 1) {
            $stmt = Database::pdo()->prepare('UPDATE sport_rule_versions SET is_active = 0 WHERE sport_id = :sport_id');
            $stmt->execute([':sport_id' => $sportId]);
        }
        if ($id === '') {
            $record['id'] = self::nextId('sport_rule_versions');
            $record['created_at'] = Database::now();
            $record['updated_at'] = Database::now();
            $row = self::insertRow('sport_rule_versions', $record);
            self::audit('create', 'sport_rule', (string) $row['id'], $row);
        } else {
            $row = self::updateRow('sport_rule_versions', $id, $record);
            self::audit('update', 'sport_rule', $id, $row);
        }
        return self::decodeSportRule($row);
    }

    public static function listSports(): array
    {
        Auth::canViewSportsWork();
        return self::fetchAll('SELECT * FROM sports ORDER BY id DESC');
    }

    public static function createSport(array $payload): array
    {
        Auth::canManageMasterData();
        $record = self::validateSportPayload($payload);
        if (self::fetchOne('SELECT id FROM sports WHERE LOWER(name) = LOWER(:name)', [':name' => $record['name']])) {
            throw new RuntimeException('ชื่อกีฬานี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น');
        }
        $record['id'] = self::nextId('sports');
        $record['is_active'] = 1;
        $record['created_at'] = Database::now();
        $record['updated_at'] = Database::now();
        $row = self::insertRow('sports', $record);
        self::saveSportRule([
            'sport_id' => $row['id'],
            'name' => 'กติกามาตรฐาน',
            'category' => $row['type'],
            'player_type' => $row['type'],
            'team_size' => $row['max_players'],
            'custom_rules' => $row['rules'],
            'is_active' => true,
        ]);
        self::audit('create', 'sport', (string) $row['id'], $row);
        return $row;
    }

    public static function updateSport(string $id, array $payload): array
    {
        Auth::canManageMasterData();
        $record = self::validateSportPayload($payload);
        if (self::fetchOne('SELECT id FROM sports WHERE LOWER(name) = LOWER(:name) AND id != :id', [
            ':name' => $record['name'],
            ':id' => $id,
        ])) {
            throw new RuntimeException('ชื่อกีฬานี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น');
        }
        $row = self::updateRow('sports', $id, $record);
        self::audit('update', 'sport', $id, $row);
        return $row;
    }

    public static function toggleSport(string $id, $active): array
    {
        Auth::canManageMasterData();
        $row = self::updateRow('sports', $id, ['is_active' => self::boolToInt($active)]);
        self::audit('toggle', 'sport', $id, ['is_active' => $row['is_active']]);
        return $row;
    }

    public static function deleteSport(string $id): array
    {
        Auth::canManageMasterData();
        if (self::countRows('tournaments', 'sport_id = :id', [':id' => $id]) > 0
            || self::countRows('tournament_sports', 'sport_id = :id', [':id' => $id]) > 0) {
            throw new RuntimeException('ลบกีฬาไม่ได้ เพราะมีรายการแข่งขันใช้งานอยู่');
        }
        $deleted = self::deleteRow('sports', $id);
        self::audit('delete', 'sport', $id, $deleted);
        return $deleted;
    }

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
        if ($existing) {
            $row = self::updateRow('tournament_sport_settings', (string) $existing['id'], $record);
        } else {
            $record['id'] = self::nextId('tournament_sport_settings');
            $record['created_at'] = Database::now();
            $row = self::insertRow('tournament_sport_settings', $record);
        }
        self::audit('configure_sport', 'tournament', $tournamentId, [
            'sport_id' => $sportId,
            'rule_version_id' => $ruleId,
            'status' => $status,
        ]);
        return $row;
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

    public static function listStudents(array $filters): array
    {
        Auth::canManageRegistration();
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            return self::fetchAll(
                'SELECT students.*, departments.name AS department_name
                 FROM students
                 LEFT JOIN departments ON departments.code = students.department_code
                 WHERE students.student_code LIKE :q OR students.first_name LIKE :q OR students.last_name LIKE :q OR students.department_code LIKE :q
                 ORDER BY students.id DESC LIMIT 200',
                [':q' => '%' . $q . '%']
            );
        }
        return self::fetchAll(
            'SELECT students.*, departments.name AS department_name
             FROM students
             LEFT JOIN departments ON departments.code = students.department_code
             ORDER BY students.id DESC LIMIT 200'
        );
    }

    public static function createStudent(array $payload): array
    {
        Auth::canManageRegistration();
        if (trim((string) ($payload['student_code'] ?? '')) === '') {
            $payload['student_code'] = 'ATH-' . self::nextId('students');
        }
        $record = self::validateStudentPayload($payload);
        if (self::fetchOne('SELECT id FROM students WHERE student_code = :code', [':code' => $record['student_code']])) {
            throw new RuntimeException('รหัสนักกีฬาซ้ำ');
        }
        $record['id'] = self::nextId('students');
        $record['created_at'] = Database::now();
        $record['updated_at'] = Database::now();
        $row = self::insertRow('students', $record);
        self::audit('create', 'student', (string) $row['id'], $row);
        return $row;
    }

    public static function updateStudent(string $id, array $payload): array
    {
        Auth::canManageRegistration();
        $current = self::findById('students', $id);
        $record = self::validateStudentPayload(array_replace($current, $payload));
        $duplicate = self::fetchOne('SELECT id FROM students WHERE student_code = :code AND id != :id', [':code' => $record['student_code'], ':id' => $id]);
        if ($duplicate) {
            throw new RuntimeException('รหัสนักกีฬาซ้ำ');
        }
        $row = self::updateRow('students', $id, $record);
        self::audit('update', 'student', $id, $row);
        return $row;
    }

    public static function deleteStudent(string $id): array
    {
        Auth::canManageRegistration();
        if (self::countRows('team_members', 'student_id = :id', [':id' => $id]) > 0) {
            throw new RuntimeException('ลบนักกีฬาไม่ได้ เพราะถูกเพิ่มในทีมแล้ว');
        }
        $deleted = self::deleteRow('students', $id);
        self::audit('delete', 'student', $id, $deleted);
        return $deleted;
    }

    public static function importStudentsCsv(array $rows): array
    {
        Auth::canManageRegistration();
        $created = [];
        $skipped = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || (empty($row['student_code']) && empty($row['first_name']))) {
                continue;
            }
            try {
                if (self::fetchOne('SELECT id FROM students WHERE student_code = :code', [':code' => (string) $row['student_code']])) {
                    $skipped[] = ['row' => $index + 1, 'student_code' => $row['student_code'], 'reason' => 'duplicate'];
                    continue;
                }
                $created[] = self::createStudent($row);
            } catch (Throwable $error) {
                $errors[] = ['row' => $index + 1, 'message' => $error->getMessage()];
            }
        }
        self::audit('import', 'students', '', ['created' => count($created), 'skipped' => count($skipped), 'errors' => count($errors)]);
        if (!$created && $errors) {
            throw new RuntimeException($errors[0]['message']);
        }
        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors, 'total' => count($rows)];
    }

    public static function listTeams(string $tournamentId = ''): array
    {
        Auth::canViewSportsWork();
        $rows = self::joinedTeams();
        if ((Auth::currentUser()['role'] ?? '') === Auth::ROLE_SPORT_HEAD) {
            $allowed = array_filter(array_map('trim', explode(',', (string) (Auth::currentUser()['sport_ids'] ?? ''))));
            $rows = array_values(array_filter($rows, static fn(array $row): bool => in_array((string) ($row['sport_id'] ?? ''), $allowed, true)));
        }
        if ($tournamentId !== '') {
            $rows = array_values(array_filter($rows, fn(array $row): bool => (string) $row['tournament_id'] === $tournamentId));
        }
        return $rows;
    }

    public static function createTeam(array $payload): array
    {
        Auth::canManageSports();
        $record = self::validateTeamPayload($payload);
        Auth::assertSportScope((string) $record['sport_id']);
        $record['id'] = self::nextId('teams');
        $record['status'] = 'submitted';
        $record['rejection_reason'] = '';
        $record['approved_by'] = '';
        $record['approved_at'] = '';
        $record['created_by'] = Auth::currentUser()['email'] ?? '';
        $record['created_at'] = Database::now();
        $record['updated_at'] = Database::now();
        $row = self::insertRow('teams', $record);
        self::audit('create', 'team', (string) $row['id'], $row);
        return $row;
    }

    public static function updateTeam(string $id, array $payload): array
    {
        Auth::canManageSports();
        $current = self::findById('teams', $id);
        Auth::assertSportScope((string) ($current['sport_id'] ?? ''));
        $record = self::validateTeamPayload(array_replace($current, $payload), true);
        Auth::assertSportScope((string) $record['sport_id']);
        $row = self::updateRow('teams', $id, $record);
        self::audit('update', 'team', $id, $row);
        return $row;
    }

    public static function deleteTeam(string $id): array
    {
        Auth::canManageSports();
        Auth::assertSportScope((string) self::findById('teams', $id)['sport_id']);
        if (self::fetchOne('SELECT id FROM matches WHERE team1_id = :id OR team2_id = :id LIMIT 1', [':id' => $id])) {
            throw new RuntimeException('ลบทีมไม่ได้ เพราะทีมนี้ถูกใช้ในแมตช์แล้ว');
        }
        $members = self::deleteRowsWhere('team_members', 'team_id = :id', [':id' => $id]);
        $team = self::deleteRow('teams', $id);
        self::audit('delete', 'team', $id, ['team' => $team, 'members' => count($members)]);
        return ['team' => $team, 'members' => count($members)];
    }

    public static function updateTeamStatus(string $id, string $status, string $reason = ''): array
    {
        Auth::canManageSports();
        Auth::assertSportScope((string) self::findById('teams', $id)['sport_id']);
        if (!in_array($status, ['draft', 'submitted', 'approved', 'rejected', 'locked', 'withdrawn'], true)) {
            throw new InvalidArgumentException('สถานะทีมไม่ถูกต้อง');
        }
        $patch = ['status' => $status, 'rejection_reason' => $reason];
        if ($status === 'approved') {
            $patch['approved_by'] = Auth::currentUser()['email'] ?? '';
            $patch['approved_at'] = Database::now();
        }
        $row = self::updateRow('teams', $id, $patch);
        self::audit('status', 'team', $id, $patch);
        return $row;
    }

    public static function listTeamMembers(string $teamId = ''): array
    {
        Auth::canManageSports();
        if ($teamId !== '') {
            Auth::assertSportScope((string) self::findById('teams', $teamId)['sport_id']);
        }
        $rows = self::joinedTeamMembers($teamId);
        if ((Auth::currentUser()['role'] ?? '') === Auth::ROLE_SPORT_HEAD) {
            $allowed = array_column(self::listTeams(), 'id');
            $rows = array_values(array_filter($rows, static fn(array $row): bool => in_array($row['team_id'], $allowed)));
        }
        return $rows;
    }

    public static function importTeamMembers(array $rows): array
    {
        Auth::canManageSports();
        $created = [];
        $updated = [];
        $skipped = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            try {
                if (!is_array($row) || empty($row['team_id'])) {
                    $skipped[] = ['row' => $index + 1, 'reason' => 'missing_team_id'];
                    continue;
                }
                $teamId = trim((string) $row['team_id']);
                $team = self::fetchOne('SELECT id, sport_id FROM teams WHERE id = :id', [':id' => $teamId]);
                if (!$team) {
                    throw new RuntimeException('ไม่พบทีมรหัส: ' . $teamId);
                }
                Auth::assertSportScope((string) ($team['sport_id'] ?? ''));

                $student = self::findStudentByCodeOrId((string) ($row['student_code'] ?? $row['student_id'] ?? ''));
                if (!$student) {
                    throw new RuntimeException('ไม่พบนักกีฬา: ' . (string) ($row['student_code'] ?? $row['student_id'] ?? ''));
                }

                $patch = [
                    'role' => trim((string) ($row['role'] ?? 'player')) ?: 'player',
                    'shirt_number' => trim((string) ($row['shirt_number'] ?? '')),
                    'field_position' => self::normalizePosition((string) ($row['field_position'] ?? $row['position'] ?? '')),
                ];
                $existing = self::fetchOne('SELECT * FROM team_members WHERE team_id = :team_id AND student_id = :student_id', [
                    ':team_id' => $teamId,
                    ':student_id' => $student['id'],
                ]);
                if ($existing) {
                    $updated[] = self::updateRow('team_members', (string) $existing['id'], $patch);
                    continue;
                }

                $record = array_replace($patch, [
                    'id' => self::nextId('team_members'),
                    'team_id' => $teamId,
                    'student_id' => $student['id'],
                    'created_at' => Database::now(),
                ]);
                $created[] = self::insertRow('team_members', $record);
            } catch (Throwable $error) {
                $errors[] = ['row' => $index + 1, 'message' => $error->getMessage()];
            }
        }
        self::audit('import_members', 'team_members', '', ['created' => count($created), 'updated' => count($updated), 'skipped' => count($skipped), 'errors' => count($errors)]);
        if (!$created && !$updated && $errors) {
            throw new RuntimeException($errors[0]['message']);
        }
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'total' => count($rows)];
    }

    public static function updateTeamMember(string $id, array $payload): array
    {
        Auth::canManageSports();
        $current = self::findById('team_members', $id);
        Auth::assertSportScope((string) self::findById('teams', (string) $current['team_id'])['sport_id']);
        $teamId = trim((string) ($payload['team_id'] ?? $current['team_id']));
        Auth::assertSportScope((string) self::findById('teams', $teamId)['sport_id']);
        $student = self::findStudentByCodeOrId((string) ($payload['student_code'] ?? $payload['student_id'] ?? $current['student_id']));
        if (!$student) {
            throw new RuntimeException('ไม่พบนักกีฬา');
        }
        if (!self::fetchOne('SELECT id FROM teams WHERE id = :id', [':id' => $teamId])) {
            throw new RuntimeException('ไม่พบทีม');
        }
        $duplicate = self::fetchOne(
            'SELECT id FROM team_members WHERE id != :id AND team_id = :team_id AND student_id = :student_id',
            [':id' => $id, ':team_id' => $teamId, ':student_id' => $student['id']]
        );
        if ($duplicate) {
            throw new RuntimeException('นักกีฬาคนนี้อยู่ในทีมแล้ว');
        }
        $row = self::updateRow('team_members', $id, [
            'team_id' => $teamId,
            'student_id' => $student['id'],
            'role' => trim((string) ($payload['role'] ?? $current['role'] ?? 'player')) ?: 'player',
            'shirt_number' => trim((string) ($payload['shirt_number'] ?? '')),
            'field_position' => self::normalizePosition((string) ($payload['field_position'] ?? '')),
        ]);
        self::audit('update_member', 'team_members', $id, $row);
        return $row;
    }

    public static function deleteTeamMember(string $id): array
    {
        Auth::canManageSports();
        $member = self::findById('team_members', $id);
        Auth::assertSportScope((string) self::findById('teams', (string) $member['team_id'])['sport_id']);
        $deleted = self::deleteRow('team_members', $id);
        self::audit('delete_member', 'team_members', $id, $deleted);
        return $deleted;
    }

    public static function listMatches(string $tournamentId = ''): array
    {
        Auth::canViewSportsWork();
        $rows = self::joinedMatches();
        if ((Auth::currentUser()['role'] ?? '') === Auth::ROLE_SPORT_HEAD) {
            $allowed = array_filter(array_map('trim', explode(',', (string) (Auth::currentUser()['sport_ids'] ?? ''))));
            $rows = array_values(array_filter($rows, static fn(array $row): bool => in_array((string) ($row['sport_id'] ?? ''), $allowed, true)));
        }
        if ($tournamentId !== '') {
            $rows = array_values(array_filter($rows, fn(array $row): bool => (string) $row['tournament_id'] === $tournamentId));
        }
        return $rows;
    }

    public static function listMatchesForNews(): array
    {
        Auth::canManageNews();
        $rows = self::joinedMatches();
        usort($rows, fn(array $a, array $b): int => strcmp((string) ($b['confirmed_at'] ?: $b['updated_at'] ?: $b['created_at']), (string) ($a['confirmed_at'] ?: $a['updated_at'] ?: $a['created_at'])));
        return array_slice($rows, 0, 50);
    }

    public static function createMatch(array $payload): array
    {
        Auth::canManageSports();
        self::validateMatchTeams($payload);
        $tournamentId = trim((string) ($payload['tournament_id'] ?? ''));
        $sportId = self::matchSportId($payload);
        Auth::assertSportScope($sportId);
        $rule = self::tournamentSportRule($tournamentId, $sportId);
        $record = [
            'id' => self::nextId('matches'),
            'tournament_id' => $tournamentId,
            'sport_id' => $sportId,
            'rule_version_id' => $rule['id'],
            'round_name' => (string) ($payload['round_name'] ?? 'รอบแรก'),
            'match_number' => (string) self::nextMatchNumber($tournamentId),
            'team1_id' => self::nullableId($payload['team1_id'] ?? null),
            'team2_id' => self::nullableId($payload['team2_id'] ?? null),
            'scheduled_at' => (string) ($payload['scheduled_at'] ?? ''),
            'venue' => (string) ($payload['venue'] ?? ''),
            'referee' => (string) ($payload['referee'] ?? ''),
            'status' => 'scheduled',
            'team1_score' => null,
            'team2_score' => null,
            'winner_team_id' => null,
            'current_period' => 1,
            'clock_seconds' => 0,
            'clock_status' => 'stopped',
            'overtime_count' => 0,
            'confirmed_by' => '',
            'confirmed_at' => '',
            'created_at' => Database::now(),
            'updated_at' => Database::now(),
        ];
        $row = self::insertRow('matches', $record);
        self::ensureMatchPeriods((string) $row['id'], $rule);
        self::audit('create', 'match', (string) $row['id'], $row);
        return $row;
    }

    public static function updateMatch(string $id, array $payload): array
    {
        Auth::canManageSports();
        $current = self::findById('matches', $id);
        Auth::assertSportScope((string) ($current['sport_id'] ?? ''));
        $next = array_replace($current, $payload);
        self::validateMatchTeams($next);
        $status = (string) ($next['status'] ?? 'scheduled');
        if (!in_array($status, ['scheduled', 'live', 'completed', 'cancelled', 'postponed'], true)) {
            throw new InvalidArgumentException('สถานะแมตช์ไม่ถูกต้อง');
        }
        $sportId = self::matchSportId($next);
        Auth::assertSportScope($sportId);
        $rule = !empty($next['rule_version_id'])
            ? self::decodeSportRule(self::findById('sport_rule_versions', (string) $next['rule_version_id']))
            : self::tournamentSportRule((string) $next['tournament_id'], $sportId);
        $row = self::updateRow('matches', $id, [
            'tournament_id' => trim((string) $next['tournament_id']),
            'sport_id' => $sportId,
            'rule_version_id' => $rule['id'],
            'round_name' => (string) ($next['round_name'] ?: 'รอบแรก'),
            'match_number' => (string) ($next['match_number'] ?? ''),
            'team1_id' => self::nullableId($next['team1_id'] ?? null),
            'team2_id' => self::nullableId($next['team2_id'] ?? null),
            'scheduled_at' => (string) ($next['scheduled_at'] ?? ''),
            'venue' => (string) ($next['venue'] ?? ''),
            'referee' => (string) ($next['referee'] ?? ''),
            'status' => $status,
            'team1_score' => self::nullableInt($next['team1_score'] ?? null),
            'team2_score' => self::nullableInt($next['team2_score'] ?? null),
        ]);
        self::ensureMatchPeriods($id, $rule);
        self::audit('update', 'match', $id, $row);
        return $row;
    }

    public static function deleteMatch(string $id): array
    {
        Auth::canManageSports();
        $match = self::findById('matches', $id);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        self::deleteRowsWhere('match_events', 'match_id = :id', [':id' => $id]);
        self::deleteRowsWhere('match_periods', 'match_id = :id', [':id' => $id]);
        $deleted = self::deleteRow('matches', $id);
        self::recalculateStandings((string) $match['tournament_id']);
        self::audit('delete', 'match', $id, $deleted);
        return $deleted;
    }

    public static function saveMatchResult(string $id, array $payload): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $id);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        $score1 = self::nullableInt($payload['team1_score'] ?? null);
        $score2 = self::nullableInt($payload['team2_score'] ?? null);
        if ($score1 === null || $score2 === null) {
            throw new InvalidArgumentException('กรุณาระบุคะแนนทั้งสองฝั่ง');
        }
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
        $winner = self::determineMatchWinner($match, $score1, $score2, $rule, self::nullableId($payload['winner_team_id'] ?? null));
        $row = self::updateRow('matches', $id, [
            'status' => 'completed',
            'clock_status' => 'stopped',
            'team1_score' => $score1,
            'team2_score' => $score2,
            'winner_team_id' => $winner,
            'confirmed_by' => Auth::currentUser()['email'] ?? '',
            'confirmed_at' => Database::now(),
        ]);
        self::recalculateStandings((string) $match['tournament_id']);
        self::audit('result', 'match', $id, $row);
        return $row;
    }

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
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
        self::ensureMatchPeriods($matchId, $rule);
        $events = self::fetchAll(
            'SELECT match_events.*, teams.name AS team_name,
                    students.student_code, students.prefix, students.first_name, students.last_name
             FROM match_events
             LEFT JOIN teams ON teams.id = match_events.team_id
             LEFT JOIN students ON students.id = match_events.student_id
             WHERE match_events.match_id = :match_id
             ORDER BY match_events.period_number DESC, match_events.elapsed_seconds DESC, match_events.id DESC',
            [':match_id' => $matchId]
        );
        foreach ($events as &$event) {
            $event['student_name'] = trim(implode(' ', array_filter([$event['prefix'] ?? '', $event['first_name'] ?? '', $event['last_name'] ?? ''])));
            $event['metadata'] = self::jsonObject((string) ($event['metadata_json'] ?? '{}'));
        }
        unset($event);
        return [
            'match' => $match,
            'rule' => $rule,
            'periods' => self::fetchAll('SELECT * FROM match_periods WHERE match_id = :match_id ORDER BY period_number', [':match_id' => $matchId]),
            'events' => $events,
            'team1_members' => $match['team1_id'] ? self::joinedTeamMembers((string) $match['team1_id']) : [],
            'team2_members' => $match['team2_id'] ? self::joinedTeamMembers((string) $match['team2_id']) : [],
        ];
    }

    public static function updateMatchClock(string $matchId, array $payload): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $matchId);
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
    }

    public static function saveMatchEvent(array $payload): array
    {
        Auth::canManageResults();
        $matchId = trim((string) ($payload['match_id'] ?? ''));
        $match = self::findById('matches', $matchId);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        if ((string) ($match['status'] ?? '') !== 'live') {
            throw new RuntimeException('ต้องเริ่มการแข่งขันก่อนจึงจะบันทึกคะแนนหรือเหตุการณ์ได้');
        }
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
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
        $teamId = self::nullableId($payload['team_id'] ?? null);
        $participant = (string) ($definition['participant'] ?? 'optional');
        $points = array_key_exists('points', $payload) && $payload['points'] !== '' ? (int) $payload['points'] : (int) ($definition['points'] ?? 0);
        if (($participant === 'athlete' || $participant === 'team' || $points !== 0) && $teamId === null) {
            throw new InvalidArgumentException('กรุณาเลือกทีมของเหตุการณ์');
        }
        if ($teamId !== null && !in_array((string) $teamId, [(string) $match['team1_id'], (string) $match['team2_id']], true)) {
            throw new InvalidArgumentException('ทีมไม่อยู่ในแมตช์นี้');
        }
        $studentId = self::nullableId($payload['student_id'] ?? null);
        if ($participant === 'athlete' && $studentId === null) {
            throw new InvalidArgumentException('กรุณาเลือกนักกีฬาผู้ทำเหตุการณ์');
        }
        if ($studentId !== null && $teamId === null) {
            throw new InvalidArgumentException('กรุณาเลือกทีมก่อนเลือกนักกีฬา');
        }
        if ($studentId !== null && $teamId !== null && !self::fetchOne('SELECT id FROM team_members WHERE team_id = :team_id AND student_id = :student_id', [':team_id' => $teamId, ':student_id' => $studentId])) {
            throw new InvalidArgumentException('นักกีฬาไม่อยู่ในทีมที่เลือก');
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
            'metadata_json' => json_encode(self::payloadObject($payload['metadata'] ?? []), JSON_UNESCAPED_UNICODE),
            'is_voided' => 0,
            'created_by' => Auth::currentUser()['email'] ?? '',
            'created_at' => Database::now(),
        ];
        $row = self::insertRow('match_events', $record);
        self::syncMatchScoreFromEvents($matchId);
        self::audit('create_event', 'match', $matchId, $row);
        return $row;
    }

    public static function voidMatchEvent(string $id): array
    {
        Auth::canManageResults();
        $event = self::findById('match_events', $id);
        $match = self::findById('matches', (string) $event['match_id']);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        $row = self::updateRow('match_events', $id, [
            'is_voided' => 1,
            'voided_by' => Auth::currentUser()['email'] ?? '',
            'voided_at' => Database::now(),
        ]);
        self::syncMatchScoreFromEvents((string) $event['match_id']);
        self::audit('void_event', 'match', (string) $event['match_id'], $row);
        return $row;
    }

    public static function listStandings(string $tournamentId): array
    {
        Auth::canViewSportsWork();
        if ($tournamentId === '') {
            return [];
        }
        self::recalculateStandings($tournamentId);
        return self::fetchAll(
            'SELECT standings.*, teams.name AS team_name, teams.logo_url, teams.sport_id, sports.name AS sport_name,
                    departments.name AS department_name, (standings.scored - standings.conceded) AS score_difference
             FROM standings
             JOIN teams ON teams.id = standings.team_id
             LEFT JOIN sports ON sports.id = teams.sport_id
             LEFT JOIN departments ON departments.code = teams.department_code
             WHERE standings.tournament_id = :tournament_id
             ORDER BY sports.name, standings.rank_number, teams.name',
            [':tournament_id' => $tournamentId]
        );
    }

    public static function listNews(): array
    {
        Auth::canManageNews();
        return self::enrichedNewsRows(self::fetchAll('SELECT * FROM news ORDER BY id DESC'));
    }

    public static function saveNews(array $payload): array
    {
        Auth::canManageNews();
        $record = self::validateNewsPayload($payload);
        if (!empty($payload['id'])) {
            $row = self::updateRow('news', (string) $payload['id'], $record);
            self::replaceNewsLinks((string) $payload['id'], $payload);
            self::audit('update', 'news', (string) $payload['id'], $row);
            return $row;
        }
        $record['id'] = self::nextId('news');
        $record['created_at'] = Database::now();
        $record['updated_at'] = Database::now();
        $row = self::insertRow('news', $record);
        self::replaceNewsLinks((string) $row['id'], $payload);
        self::audit('create', 'news', (string) $row['id'], $row);
        return $row;
    }

    public static function publishNews(string $id): array
    {
        Auth::canManageNews();
        $row = self::updateRow('news', $id, ['status' => 'published', 'published_at' => Database::now()]);
        self::audit('publish', 'news', $id, []);
        return $row;
    }

    public static function deleteNews(string $id): array
    {
        Auth::canManageNews();
        $deleted = self::deleteRow('news', $id);
        self::audit('delete', 'news', $id, $deleted);
        return $deleted;
    }

    public static function uploadNewsImage(array $payload, array $files): array
    {
        Auth::canManageNews();
        $dataUrl = (string) ($payload['data_url'] ?? '');
        $fileName = (string) ($payload['file_name'] ?? 'news-cover');
        $bytes = null;
        $mime = '';

        if ($dataUrl !== '' && preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $dataUrl, $match)) {
            $mime = $match[1];
            $bytes = base64_decode($match[2], true);
        } elseif (!empty($files['file']['tmp_name'])) {
            $mime = (string) mime_content_type($files['file']['tmp_name']);
            $bytes = file_get_contents($files['file']['tmp_name']);
            $fileName = (string) $files['file']['name'];
        }

        if ($bytes === false || $bytes === null || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new InvalidArgumentException('ไฟล์รูปภาพไม่ถูกต้อง');
        }
        if (strlen($bytes) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('รูปภาพต้องไม่เกิน 5MB');
        }

        $dir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'news';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
        $safe = self::slugify(preg_replace('/\.[^.]+$/', '', $fileName) ?: 'news-cover') ?: 'news-cover';
        $name = $safe . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($target, $bytes);
        $url = app_url('public/uploads/news/' . $name);
        self::audit('upload_image', 'news', $name, ['name' => $name, 'mime_type' => $mime]);
        return ['url' => $url, 'thumbnail_url' => $url, 'name' => $name, 'id' => $name];
    }

    public static function listUsers(): array
    {
        Auth::canManageUsers();
        return self::enrichUsers(self::fetchAll('SELECT * FROM users ORDER BY id DESC'));
    }

    public static function listInvites(): array
    {
        Auth::canManageUsers();
        self::expireInvites();
        return self::enrichInvites(self::fetchAll('SELECT * FROM user_invites ORDER BY id DESC LIMIT 100'));
    }

    public static function userFormOptions(): array
    {
        Auth::canManageUsers();
        return [
            'roles' => Auth::roleOptions(),
            'departments' => self::activeDepartments(),
            'sports' => self::fetchAll('SELECT * FROM sports WHERE is_active = 1 ORDER BY name ASC'),
            'staff_invites_enabled' => Auth::truthy(self::settings()['staff_invites_enabled']),
        ];
    }

    public static function setInvitesEnabled(array $payload): array
    {
        Auth::canManageUsers();
        $enabled = Auth::truthy($payload['enabled'] ?? false) ? '1' : '0';
        $key = 'staff_invites_enabled';
        $column = Database::isMysql() ? '`key`' : 'key';
        if (self::fetchOne("SELECT {$column} FROM settings WHERE {$column} = :key", [':key' => $key])) {
            Database::pdo()->prepare("UPDATE settings SET value = :value WHERE {$column} = :key")->execute([':value' => $enabled, ':key' => $key]);
        } else {
            Database::pdo()->prepare("INSERT INTO settings ({$column}, value, note) VALUES (:key, :value, :note)")->execute([':key' => $key, ':value' => $enabled, ':note' => 'เปิดใช้ลิงก์คำเชิญทีมงาน']);
        }
        self::audit('toggle_staff_invites', 'settings', $key, ['enabled' => $enabled === '1']);
        return ['enabled' => $enabled === '1'];
    }

    public static function listAuditLogs(array $filters = []): array
    {
        Auth::canManageUsers();
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query === '') {
            return self::fetchAll('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 200');
        }
        return self::fetchAll('SELECT * FROM audit_logs WHERE user_email LIKE :user_q OR action LIKE :action_q OR entity LIKE :entity_q OR detail_json LIKE :detail_q ORDER BY id DESC LIMIT 200', [
            ':user_q' => '%' . $query . '%', ':action_q' => '%' . $query . '%', ':entity_q' => '%' . $query . '%', ':detail_q' => '%' . $query . '%',
        ]);
    }

    public static function updateProfile(array $payload): array
    {
        $currentUser = Auth::requireRole([
            Auth::ROLE_SUPER_ADMIN,
            Auth::ROLE_ADMIN,
            Auth::ROLE_SPORT_HEAD,
            Auth::ROLE_REGISTRATION_OFFICER,
            Auth::ROLE_RESULT_OFFICER,
            Auth::ROLE_PUBLIC_RELATIONS,
        ]);
        $id = (string) ($currentUser['id'] ?? '');
        $existing = self::findById('users', $id);
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('กรุณากรอกชื่อผู้ใช้งาน');
        }

        $patch = ['name' => $name];
        $currentPassword = (string) ($payload['current_password'] ?? '');
        $newPassword = (string) ($payload['new_password'] ?? '');
        $confirm = (string) ($payload['new_password_confirm'] ?? '');
        if ($newPassword !== '' || $confirm !== '' || $currentPassword !== '') {
            if (empty($existing['password_hash']) || !password_verify($currentPassword, (string) $existing['password_hash'])) {
                throw new RuntimeException('รหัสผ่านเดิมไม่ถูกต้อง');
            }
            if ($newPassword !== $confirm) {
                throw new InvalidArgumentException('รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน');
            }
            Auth::validatePasswordStrength($newPassword);
            $patch['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $patch['password_salt'] = null;
            $patch['password_updated_at'] = Database::now();
        }

        $row = self::updateRow('users', $id, $patch);
        self::audit('update_profile', 'users', $id, self::sanitizeUserAudit($row));
        return [
            'user' => Auth::sanitize($row),
            'csrf' => Auth::csrfToken(),
        ];
    }

    public static function saveUser(array $payload): array
    {
        Auth::canManageUsers();
        $id = (string) ($payload['id'] ?? '');
        $currentUser = Auth::currentUser();
        $record = self::validateUserPayload($payload, $id === '');
        if (self::fetchOne('SELECT id FROM users WHERE LOWER(email) = :email AND id != :id', [':email' => $record['email'], ':id' => $id ?: 0])) {
            throw new RuntimeException('อีเมลนี้มีอยู่ในระบบแล้ว');
        }
        if ($id !== '' && (string) ($currentUser['id'] ?? '') === $id) {
            if (!Auth::truthy($record['is_active'])) {
                throw new RuntimeException('ไม่สามารถปิดใช้งานบัญชีของตัวเองได้');
            }
            if ($record['email'] !== strtolower((string) ($currentUser['email'] ?? ''))) {
                throw new RuntimeException('ไม่สามารถเปลี่ยนอีเมลบัญชีของตัวเองได้');
            }
            if ($record['role'] !== ($currentUser['role'] ?? '')) {
                throw new RuntimeException('ไม่สามารถเปลี่ยนบทบาทบัญชีของตัวเองได้');
            }
        }

        if ($id !== '') {
            $row = self::updateRow('users', $id, $record);
            self::audit('update', 'users', $id, self::sanitizeUserAudit($row));
            return self::enrichUsers([$row])[0];
        }

        $record['id'] = self::nextId('users');
        $record['created_at'] = Database::now();
        $record['updated_at'] = Database::now();
        $row = self::insertRow('users', $record);
        self::audit('create', 'users', (string) $row['id'], self::sanitizeUserAudit($row));
        return self::enrichUsers([$row])[0];
    }

    public static function createInvite(array $payload): array
    {
        Auth::canManageUsers();
        if (!Auth::truthy(self::settings()['staff_invites_enabled'])) {
            throw new RuntimeException('ปิดรับคำเชิญทีมงานชั่วคราว');
        }
        $record = self::validateInvitePayload($payload);
        if (self::fetchOne('SELECT id FROM users WHERE LOWER(email) = :email LIMIT 1', [':email' => $record['email']])) {
            throw new RuntimeException('อีเมลนี้มีบัญชีในระบบแล้ว');
        }

        self::expireInvites();
        self::deleteRowsWhere('user_invites', 'LOWER(email) = :email AND status = :status', [
            ':email' => $record['email'],
            ':status' => 'pending',
        ]);

        $token = bin2hex(random_bytes(32));
        $now = new DateTimeImmutable('now');
        $record += [
            'id' => self::nextId('user_invites'),
            'token_hash' => self::inviteTokenHash($token),
            'invited_by' => Auth::currentUser()['email'] ?? '',
            'accepted_user_id' => null,
            'status' => 'pending',
            'expires_at' => $now->modify('+45 minutes')->format('Y-m-d H:i:s'),
            'accepted_at' => null,
            'revoked_at' => null,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ];

        $row = self::insertRow('user_invites', $record);
        $row['invite_url'] = self::inviteUrl($token);
        self::audit('create_invite', 'user_invites', (string) $row['id'], [
            'email' => $row['email'],
            'role' => $row['role'],
            'expires_at' => $row['expires_at'],
        ]);
        return self::enrichInvites([$row])[0];
    }

    public static function revokeInvite(string $id): array
    {
        Auth::canManageUsers();
        self::expireInvites();
        $invite = self::findById('user_invites', $id);
        if ((string) $invite['status'] !== 'pending') {
            return self::enrichInvites([$invite])[0];
        }
        $row = self::updateRow('user_invites', $id, [
            'status' => 'revoked',
            'revoked_at' => Database::now(),
        ]);
        self::audit('revoke_invite', 'user_invites', $id, ['email' => $row['email']]);
        return self::enrichInvites([$row])[0];
    }

    public static function invitePreview(string $token): array
    {
        if (!Auth::truthy(self::settings()['staff_invites_enabled'])) {
            throw new RuntimeException('ปิดรับคำเชิญทีมงานชั่วคราว');
        }
        self::expireInvites();
        $invite = self::activeInviteByToken($token);
        return [
            'email' => $invite['email'],
            'name' => $invite['name'],
            'role' => $invite['role'],
            'role_label' => self::roleLabel((string) $invite['role']),
            'department_code' => $invite['department_code'],
            'expires_at' => $invite['expires_at'],
        ];
    }

    public static function acceptInvite(array $payload): array
    {
        if (!Auth::truthy(self::settings()['staff_invites_enabled'])) {
            throw new RuntimeException('ปิดรับคำเชิญทีมงานชั่วคราว');
        }
        self::expireInvites();
        $token = (string) ($payload['token'] ?? '');
        $invite = self::activeInviteByToken($token);
        $email = strtolower(trim((string) ($payload['email'] ?? $invite['email'])));
        $name = trim((string) ($payload['name'] ?? $invite['name'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $confirm = (string) ($payload['password_confirm'] ?? '');

        if ($email !== strtolower((string) $invite['email'])) {
            throw new InvalidArgumentException('อีเมลไม่ตรงกับคำเชิญ');
        }
        if ($name === '') {
            throw new InvalidArgumentException('กรุณากรอกชื่อผู้ใช้งาน');
        }
        if ($password !== $confirm) {
            throw new InvalidArgumentException('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
        }
        Auth::validatePasswordStrength($password);
        if (self::fetchOne('SELECT id FROM users WHERE LOWER(email) = :email LIMIT 1', [':email' => $email])) {
            throw new RuntimeException('อีเมลนี้มีบัญชีในระบบแล้ว');
        }

        $now = Database::now();
        $user = self::insertRow('users', [
            'id' => self::nextId('users'),
            'email' => $email,
            'name' => $name,
            'role' => $invite['role'],
            'department_code' => $invite['department_code'] ?: null,
            'sport_ids' => $invite['sport_ids'] ?: '',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_salt' => null,
            'password_updated_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::updateRow('user_invites', (string) $invite['id'], [
            'status' => 'accepted',
            'accepted_user_id' => $user['id'],
            'accepted_at' => $now,
        ]);
        self::audit('accept_invite', 'user_invites', (string) $invite['id'], ['email' => $email, 'user_id' => $user['id']]);

        $_SESSION['user_id'] = (int) $user['id'];
        session_regenerate_id(true);
        return [
            'user' => Auth::sanitize($user),
            'csrf' => Auth::csrfToken(),
        ];
    }

    public static function deleteUser(string $id): array
    {
        Auth::canManageUsers();
        if ((string) (Auth::currentUser()['id'] ?? '') === $id) {
            throw new RuntimeException('ไม่สามารถลบบัญชีของตัวเองได้');
        }
        $deleted = self::deleteRow('users', $id);
        self::audit('delete', 'users', $id, self::sanitizeUserAudit($deleted));
        return self::sanitizeUserAudit($deleted);
    }

    public static function aiTaskModels(): array
    {
        Auth::requireRole([
            Auth::ROLE_SUPER_ADMIN,
            Auth::ROLE_ADMIN,
            Auth::ROLE_SPORT_HEAD,
            Auth::ROLE_REGISTRATION_OFFICER,
            Auth::ROLE_RESULT_OFFICER,
            Auth::ROLE_PUBLIC_RELATIONS,
        ]);
        $client = self::aiClient();
        return [
            'models' => self::AI_MODELS,
            'default_model' => $client->model(),
            'provider' => $client->isConfigured() ? 'google-gemini' : 'local-summary',
            'configured' => $client->isConfigured(),
        ];
    }

    public static function testAiConnection(): array
    {
        Auth::canViewSportsWork();
        $client = self::aiClient();
        $diagnostics = $client->diagnostics();
        if (!$client->isConfigured()) {
            return $diagnostics + [
                'ok' => false,
                'message' => 'ยังไม่ได้ตั้ง GEMINI_API_KEY ใน environment หรือ .env',
            ];
        }

        $started = microtime(true);
        try {
            $text = $client->generateText(
                'Reply in Thai with one concise sentence. Do not include markdown.',
                'ทดสอบการเชื่อมต่อ Gemini สำหรับระบบจัดการกีฬา ตอบว่าเชื่อมต่อสำเร็จและระบุว่าสามารถช่วยสรุปรายงานได้',
                ['temperature' => 0.1, 'max_output_tokens' => 120]
            );
            self::audit('ai_test', 'ai', 'gemini', ['model' => $client->model(), 'ok' => true]);
            return $diagnostics + [
                'ok' => true,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => $text,
            ];
        } catch (Throwable $error) {
            self::audit('ai_test', 'ai', 'gemini', ['model' => $client->model(), 'ok' => false, 'error' => $error->getMessage()]);
            return $diagnostics + [
                'ok' => false,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => $error->getMessage(),
            ];
        }
    }

    public static function generateDashboardBrief(): array
    {
        Auth::canViewSportsWork();
        $dataset = self::aiDataset('dashboard');
        $local = self::localDashboardBrief($dataset);
        $client = self::aiClient();
        if (!$client->isConfigured()) {
            return $local + ['model' => 'local-summary', 'provider' => 'local-summary'];
        }

        try {
            $text = $client->generateText(
                self::thaiSportsSystemInstruction(),
                self::aiPrompt('dashboard', $dataset),
                ['temperature' => 0.25, 'max_output_tokens' => 1400]
            );
            return self::structuredAiResult($text, $local, $client->model(), 'google-gemini');
        } catch (Throwable $error) {
            self::audit('ai_fallback', 'ai', 'dashboard', ['error' => $error->getMessage(), 'model' => $client->model()]);
            return $local + ['model' => 'local-summary', 'provider' => 'local-summary', 'fallback_reason' => $error->getMessage()];
        }
    }

    public static function generateSystemBrief(string $instruction = ''): array
    {
        Auth::canViewSportsWork();
        $dataset = self::aiDataset('system');
        $local = self::localDashboardBrief($dataset);
        $local['recommendations'][] = 'จัดลำดับงานจากกิจกรรมล่าสุดใน audit log และข้อมูลที่ยังไม่ครบก่อนเผยแพร่';
        $client = self::aiClient();
        if (!$client->isConfigured()) {
            return $local + ['model' => 'local-summary', 'provider' => 'local-summary'];
        }

        try {
            $text = $client->generateText(
                self::thaiSportsSystemInstruction(),
                self::aiPrompt('system', $dataset, $instruction),
                ['temperature' => 0.3, 'max_output_tokens' => 1700]
            );
            return self::structuredAiResult($text, $local, $client->model(), 'google-gemini');
        } catch (Throwable $error) {
            self::audit('ai_fallback', 'ai', 'system', ['error' => $error->getMessage(), 'model' => $client->model()]);
            return $local + ['model' => 'local-summary', 'provider' => 'local-summary', 'fallback_reason' => $error->getMessage()];
        }
    }

    private static function localDashboardBrief(array $dataset): array
    {
        $counts = $dataset['counts'];
        $risks = [];
        if ($counts['students'] === 0) {
            $risks[] = 'ยังไม่มีข้อมูลนักกีฬาในระบบ';
        }
        if ($counts['matches'] > 0 && $counts['completedMatches'] === 0) {
            $risks[] = 'มีแมตช์แล้วแต่ยังไม่มีผลการแข่งขันที่ยืนยัน';
        }
        if ($counts['teams'] > 0 && $counts['approvedTeams'] === 0) {
            $risks[] = 'มีทีมรอจัดการแต่ยังไม่มีทีมที่อนุมัติ';
        }
        return [
            'summary' => 'ระบบมีกีฬาเปิดใช้ ' . $counts['activeSports'] . ' ประเภท ทีมทั้งหมด ' . $counts['teams'] . ' ทีม นักกีฬา ' . $counts['students'] . ' คน และแมตช์ที่จบแล้ว ' . $counts['completedMatches'] . ' แมตช์',
            'next_actions' => [
                'ตรวจรายการแข่งขันที่อยู่ในสถานะ registration หรือ in_progress',
                'ตรวจทีมที่ยังไม่ approved ก่อนสร้างแมตช์',
                'เผยแพร่ข่าวหลังบันทึกผลการแข่งขัน',
            ],
            'risks' => $risks,
            'recommendations' => [
                'นำเข้าข้อมูลนักกีฬาและรายชื่อนักกีฬาให้ครบก่อนวันแข่งขัน',
                'ใช้ audit log เพื่อตรวจสอบการแก้ไขข้อมูลสำคัญ',
            ],
        ];
    }

    private static function localFormalReport(array $dataset): array
    {
        $base = self::localDashboardBrief($dataset);
        $counts = $dataset['counts'];
        $risks = $base['risks'] ?: ['ไม่พบประเด็นความเสี่ยงสำคัญจากข้อมูลที่มีอยู่ในระบบ ณ เวลาจัดทำรายงาน'];

        return [
            'summary' => 'จากการตรวจสอบข้อมูลในระบบบริหารจัดการแข่งขันกีฬา พบว่ามีกีฬาที่เปิดใช้งานจำนวน '
                . $counts['activeSports'] . ' ประเภท มีทีมที่บันทึกในระบบจำนวน '
                . $counts['teams'] . ' ทีม มีข้อมูลนักกีฬาจำนวน '
                . $counts['students'] . ' คน และมีการแข่งขันที่บันทึกผลเสร็จสิ้นจำนวน '
                . $counts['completedMatches'] . ' รายการ ทั้งนี้ ข้อมูลดังกล่าวควรใช้ประกอบการติดตามความพร้อมและการดำเนินงานของคณะกรรมการที่เกี่ยวข้อง',
            'next_actions' => [
                'ตรวจสอบสถานะรายการแข่งขันและทีมที่อยู่ระหว่างรอดำเนินการให้เป็นปัจจุบัน',
                'ดำเนินการยืนยันรายชื่อทีมและนักกีฬาก่อนจัดทำหรือเผยแพร่ตารางการแข่งขัน',
                'จัดทำข่าวประชาสัมพันธ์หรือประกาศผลการแข่งขันภายหลังการบันทึกผลอย่างเป็นทางการ',
            ],
            'risks' => $risks,
            'recommendations' => [
                'ควรกำหนดผู้รับผิดชอบตรวจสอบข้อมูลหลัก ได้แก่ รายชื่อนักกีฬา ทีม รายการแข่งขัน และผลการแข่งขัน ก่อนการเผยแพร่',
                'ควรใช้ข้อมูลบันทึกกิจกรรมของระบบประกอบการตรวจสอบย้อนหลัง เพื่อให้การดำเนินงานมีความโปร่งใสและตรวจสอบได้',
                'ควรจัดทำรอบเวลาการปรับปรุงข้อมูลอย่างสม่ำเสมอ เพื่อให้รายงานสำหรับผู้บริหารมีความถูกต้องและทันต่อสถานการณ์',
            ],
        ];
    }

    public static function validateCsv(string $kind, array $rows): array
    {
        if ($kind === 'students') {
            Auth::canManageRegistration();
        } else {
            Auth::canManageSports();
        }
        if (!$rows) {
            throw new InvalidArgumentException('กรุณาวาง CSV พร้อมหัวตาราง');
        }
        $required = $kind === 'students'
            ? ['first_name']
            : ['team_id', 'student_code'];
        $issues = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $issues[] = 'แถว ' . ($index + 1) . ' รูปแบบไม่ถูกต้อง';
                continue;
            }
            foreach ($required as $field) {
                if (trim((string) ($row[$field] ?? '')) === '') {
                    $issues[] = 'แถว ' . ($index + 1) . ' ขาดฟิลด์ ' . $field;
                }
            }
        }
        return [
            'status' => $issues ? 'needs_review' : 'ready',
            'summary' => 'ตรวจข้อมูล ' . count($rows) . ' แถว พบประเด็น ' . count($issues) . ' รายการ',
            'issues' => array_slice($issues, 0, 30),
            'ready_to_import' => !$issues,
        ];
    }

    public static function generateNewsDraft(array $payload = []): array
    {
        Auth::canManageNews();
        $matchId = trim((string) ($payload['match_id'] ?? ''));
        $sportId = trim((string) ($payload['sport_id'] ?? ''));
        $tournamentId = trim((string) ($payload['tournament_id'] ?? ''));
        $storyType = (string) ($payload['story_type'] ?? 'result');
        $tone = (string) ($payload['tone'] ?? 'official');
        $instruction = trim((string) ($payload['instruction'] ?? ''));
        if (!in_array($storyType, ['result', 'preview', 'highlight'], true)) {
            throw new InvalidArgumentException('รูปแบบข่าวไม่ถูกต้อง');
        }
        if (!in_array($tone, ['official', 'energetic', 'inspiring'], true)) {
            throw new InvalidArgumentException('โทนข่าวไม่ถูกต้อง');
        }
        if (mb_strlen($instruction) > 800) {
            throw new InvalidArgumentException('ประเด็นที่ต้องการเน้นต้องไม่เกิน 800 ตัวอักษร');
        }
        $matches = self::joinedMatches();
        if ($matchId !== '') {
            $match = null;
            foreach ($matches as $row) {
                if ((string) $row['id'] === $matchId) {
                    $match = $row;
                    break;
                }
            }
        } else {
            $candidates = array_values(array_filter($matches, static function (array $row) use ($sportId, $tournamentId): bool {
                return ($sportId === '' || (string) ($row['sport_id'] ?? '') === $sportId)
                    && ($tournamentId === '' || (string) ($row['tournament_id'] ?? '') === $tournamentId);
            }));
            if ($storyType !== 'preview') {
                $completed = array_values(array_filter($candidates, fn(array $row): bool => (string) $row['status'] === 'completed'));
                if ($completed) {
                    $candidates = $completed;
                }
            }
            usort($candidates, fn(array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);
            $match = $candidates[0] ?? null;
        }
        if (empty($match)) {
            throw new RuntimeException('ไม่พบแมตช์ที่ตรงกับกีฬาและรายการที่เลือก');
        }
        $request = [
            'story_type' => $storyType,
            'tone' => $tone,
            'instruction' => $instruction,
        ];
        $local = self::localNewsDraft($match, $request);
        $client = self::aiClient();
        if ($client->isConfigured()) {
            try {
                $dataset = self::aiDataset('news');
                $dataset['selected_match'] = $match;
                $dataset['news_request'] = $request;
                $text = $client->generateText(
                    self::thaiSportsSystemInstruction(),
                    self::aiPrompt('news', $dataset),
                    ['temperature' => 0.45, 'max_output_tokens' => 1600]
                );
                $draft = self::jsonObjectFromText($text);
                return [
                    'title' => trim((string) ($draft['title'] ?? $local['title'])),
                    'excerpt' => trim((string) ($draft['excerpt'] ?? $local['excerpt'])),
                    'body' => trim((string) ($draft['body'] ?? $text ?: $local['body'])),
                    'banner_title' => trim((string) ($draft['banner_title'] ?? $draft['title'] ?? $local['banner_title'])),
                    'banner_subtitle' => trim((string) ($draft['banner_subtitle'] ?? $local['banner_subtitle'])),
                    'sport_name' => (string) ($match['sport_name'] ?? ''),
                    'tournament_name' => (string) ($match['tournament_name'] ?? ''),
                    'match_id' => (string) ($match['id'] ?? ''),
                    'model' => $client->model(),
                    'provider' => 'google-gemini',
                ];
            } catch (Throwable $error) {
                self::audit('ai_fallback', 'ai', 'news', ['error' => $error->getMessage(), 'model' => $client->model()]);
            }
        }
        return $local + [
            'sport_name' => (string) ($match['sport_name'] ?? ''),
            'tournament_name' => (string) ($match['tournament_name'] ?? ''),
            'match_id' => (string) ($match['id'] ?? ''),
            'model' => 'local-summary',
            'provider' => 'local-summary',
        ];
    }

    private static function localNewsDraft(array $match, array $request = []): array
    {
        $sport = (string) ($match['sport_name'] ?: 'การแข่งขันกีฬา');
        $fixture = $match['team1_name'] . ' พบ ' . $match['team2_name'];
        $storyType = (string) ($request['story_type'] ?? 'result');
        $tone = (string) ($request['tone'] ?? 'official');
        $instruction = trim((string) ($request['instruction'] ?? ''));
        $title = $sport . ': ' . $fixture;
        if ($storyType === 'preview') {
            $title = 'เตรียมเชียร์ ' . $fixture . ' ในการแข่งขัน' . $sport;
        } elseif ($storyType === 'highlight') {
            $title = 'ไฮไลต์' . $sport . ' ' . $fixture;
        } elseif ((string) $match['status'] === 'completed') {
            $title = ($match['winner_team_id'] ? 'สรุปผล ' : 'ผลการแข่งขัน ') . $title;
        }
        if ($tone === 'energetic') {
            $title = 'เต็มพลัง! ' . $title;
        } elseif ($tone === 'inspiring') {
            $title = 'พลังนักกีฬา: ' . $title;
        }
        $scoreText = (string) ($match['status'] ?? '') === 'completed'
            ? 'ผลการแข่งขัน ' . $match['score_label']
            : 'กำหนดแข่งขัน ' . ((string) ($match['scheduled_at'] ?? '') ?: 'รอประกาศเวลา');
        $body = "การแข่งขันรายการ {$match['tournament_name']} {$fixture} {$scoreText} ณ {$match['venue']} ทีมงานจะประกาศรายละเอียดเพิ่มเติมผ่านเว็บไซต์ประชาสัมพันธ์กีฬา";
        if ($instruction !== '') {
            $body .= "\n\nประเด็นสำคัญ: {$instruction}";
        }
        return [
            'title' => $title,
            'excerpt' => 'สรุปการแข่งขัน ' . $match['display_label'],
            'body' => $body,
            'banner_title' => $storyType === 'result' && (string) $match['status'] === 'completed'
                ? $match['team1_name'] . ' ' . $match['score_label'] . ' ' . $match['team2_name']
                : $fixture,
            'banner_subtitle' => (string) ($match['tournament_name'] ?? ''),
        ];
    }

    public static function reportOptions(): array
    {
        $user = Auth::canViewReports();
        $categories = self::fetchAll('SELECT * FROM report_categories WHERE is_active = 1 ORDER BY display_order ASC, id ASC');
        $templates = self::fetchAll('SELECT * FROM report_templates WHERE is_active = 1 ORDER BY category_id ASC, display_order ASC, id ASC');
        foreach ($templates as &$template) {
            $template['sections'] = self::jsonArray((string) ($template['sections_json'] ?? '[]'));
        }
        unset($template);
        return [
            'categories' => $categories,
            'templates' => $templates,
            'tournaments' => self::publicTournaments(),
            'settings' => self::settings(),
            'permissions' => [
                'edit' => true,
                'publish' => in_array($user['role'] ?? '', [Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN], true),
                'ai' => true,
            ],
        ];
    }

    public static function listReports(array $filters = []): array
    {
        Auth::canViewReports();
        $where = [];
        $params = [];
        foreach ([
            'category_id' => 'reports.category_id',
            'template_id' => 'reports.template_id',
            'academic_year' => 'reports.academic_year',
            'status' => 'reports.status',
        ] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = "{$column} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }
        $query = trim((string) ($filters['query'] ?? ''));
        if ($query !== '') {
            $where[] = '(reports.title LIKE :query OR reports.prepared_by LIKE :query OR reports.department LIKE :query)';
            $params[':query'] = '%' . $query . '%';
        }
        $sql = 'SELECT reports.*, report_categories.name AS category_name, report_categories.code AS category_code,
                       report_templates.name AS template_name, report_templates.code AS template_code,
                       tournaments.name AS tournament_name
                FROM reports
                JOIN report_categories ON report_categories.id = reports.category_id
                JOIN report_templates ON report_templates.id = reports.template_id
                LEFT JOIN tournaments ON tournaments.id = reports.tournament_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY reports.updated_at DESC, reports.id DESC';
        return array_map([self::class, 'enrichReportSummary'], self::fetchAll($sql, $params));
    }

    public static function getReport(string $id): array
    {
        Auth::canViewReports();
        if ($id === '') {
            throw new InvalidArgumentException('กรุณาระบุรายงาน');
        }
        $row = self::fetchOne(
            'SELECT reports.*, report_categories.name AS category_name, report_categories.code AS category_code,
                    report_templates.name AS template_name, report_templates.code AS template_code,
                    report_templates.sections_json, tournaments.name AS tournament_name
             FROM reports
             JOIN report_categories ON report_categories.id = reports.category_id
             JOIN report_templates ON report_templates.id = reports.template_id
             LEFT JOIN tournaments ON tournaments.id = reports.tournament_id
             WHERE reports.id = :id LIMIT 1',
            [':id' => $id]
        );
        if (!$row) {
            throw new RuntimeException('ไม่พบรายงานที่เลือก');
        }
        return self::enrichReport($row);
    }

    public static function saveReport(array $payload): array
    {
        $user = Auth::canEditReports();
        $record = self::validateReportPayload($payload);
        if ($record['status'] === 'published'
            && !in_array($user['role'] ?? '', [Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN], true)) {
            throw new RuntimeException('เฉพาะผู้ดูแลระบบเท่านั้นที่เผยแพร่รายงานได้');
        }
        $id = trim((string) ($payload['id'] ?? ''));
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($id !== '') {
                $existing = self::findById('reports', $id);
                if ((string) ($existing['status'] ?? '') === 'published'
                    && !in_array($user['role'] ?? '', [Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN], true)) {
                    throw new RuntimeException('รายงานที่เผยแพร่แล้วแก้ไขได้เฉพาะผู้ดูแลระบบ');
                }
                self::updateRow('reports', $id, $record);
                self::deleteRowsWhere('report_kpis', 'report_id = :report_id', [':report_id' => $id]);
                self::deleteRowsWhere('report_budgets', 'report_id = :report_id', [':report_id' => $id]);
                $action = 'update';
            } else {
                $id = (string) self::nextId('reports');
                $record['id'] = (int) $id;
                $record['created_by'] = (string) ($user['email'] ?? '');
                $record['created_at'] = Database::now();
                $record['updated_at'] = Database::now();
                self::insertRow('reports', $record);
                $action = 'create';
            }
            self::saveReportKpis($id, self::payloadArray($payload['kpis'] ?? []));
            self::saveReportBudget($id, self::payloadObject($payload['budget'] ?? []));
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        self::audit($action, 'reports', $id, ['title' => $record['title'], 'status' => $record['status']]);
        return self::getReport($id);
    }

    public static function updateReportStatus(string $id, string $status): array
    {
        $user = Auth::canEditReports();
        if (!in_array($status, ['draft', 'review', 'completed', 'published'], true)) {
            throw new InvalidArgumentException('สถานะรายงานไม่ถูกต้อง');
        }
        if ($status === 'published' && !in_array($user['role'] ?? '', [Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN], true)) {
            throw new RuntimeException('เฉพาะผู้ดูแลระบบเท่านั้นที่เผยแพร่รายงานได้');
        }
        $patch = ['status' => $status, 'published_at' => $status === 'published' ? Database::now() : null];
        self::updateRow('reports', $id, $patch);
        self::audit('status', 'reports', $id, ['status' => $status]);
        return self::getReport($id);
    }

    public static function deleteReport(string $id): array
    {
        Auth::canPublishReports();
        $row = self::deleteRow('reports', $id);
        self::audit('delete', 'reports', $id, ['title' => $row['title'] ?? '']);
        return $row;
    }

    public static function exportStoredReport(string $id): array
    {
        Auth::canViewReports();
        $report = self::getReport($id);
        $dir = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $base = 'rmutt-sport-report-' . $id . '-' . date('Ymd-His');
        $html = self::storedReportHtml($report);
        $htmlPath = $dir . DIRECTORY_SEPARATOR . $base . '.html';
        $docPath = $dir . DIRECTORY_SEPARATOR . $base . '.doc';
        $jsonPath = $dir . DIRECTORY_SEPARATOR . $base . '.json';
        file_put_contents($htmlPath, $html);
        file_put_contents($docPath, $html);
        file_put_contents($jsonPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        self::audit('export', 'reports', $id, ['file' => $base]);
        return [
            'report_url' => app_url('public/reports/' . basename($htmlPath)),
            'word_url' => app_url('public/reports/' . basename($docPath)),
            'document_url' => app_url('public/reports/' . basename($jsonPath)),
            'pdf_filename' => $base . '.pdf',
        ];
    }

    public static function uploadReportAttachment(array $payload, array $files): array
    {
        Auth::canEditReports();
        $reportId = trim((string) ($payload['report_id'] ?? ''));
        self::findById('reports', $reportId);
        $dataUrl = (string) ($payload['data_url'] ?? '');
        $fileName = trim((string) ($payload['file_name'] ?? 'เอกสารประกอบ'));
        $bytes = null;
        $mime = '';
        if ($dataUrl !== '' && preg_match('/^data:([a-zA-Z0-9.+\/-]+);base64,(.+)$/s', $dataUrl, $match)) {
            $mime = strtolower($match[1]);
            $bytes = base64_decode($match[2], true);
        } elseif (!empty($files['file']['tmp_name'])) {
            $mime = strtolower((string) mime_content_type($files['file']['tmp_name']));
            $bytes = file_get_contents($files['file']['tmp_name']);
            $fileName = (string) $files['file']['name'];
        }
        $extensions = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            'application/pdf' => 'pdf', 'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];
        if ($bytes === false || $bytes === null || !isset($extensions[$mime])) {
            throw new InvalidArgumentException('รองรับไฟล์ JPG, PNG, WEBP, PDF, DOC และ DOCX เท่านั้น');
        }
        if (strlen($bytes) > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('ไฟล์แนบต้องมีขนาดไม่เกิน 8MB');
        }
        $dir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $safe = self::slugify(pathinfo($fileName, PATHINFO_FILENAME)) ?: 'report-file';
        $name = $safe . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $extensions[$mime];
        file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $bytes);
        $row = self::insertRow('report_attachments', [
            'id' => self::nextId('report_attachments'),
            'report_id' => (int) $reportId,
            'file_name' => $fileName,
            'file_url' => app_url('public/uploads/reports/' . $name),
            'file_type' => $mime,
            'file_size' => strlen($bytes),
            'note' => trim((string) ($payload['note'] ?? '')),
            'created_at' => Database::now(),
        ]);
        self::audit('upload_attachment', 'reports', $reportId, ['name' => $fileName, 'type' => $mime]);
        return $row;
    }

    public static function generateReportFormDraft(array $payload): array
    {
        Auth::canEditReports();
        $categoryId = (int) ($payload['category_id'] ?? 0);
        $templateId = (int) ($payload['template_id'] ?? 0);
        $category = self::fetchOne('SELECT * FROM report_categories WHERE id = :id AND is_active = 1', [':id' => $categoryId]);
        $template = self::fetchOne('SELECT * FROM report_templates WHERE id = :id AND category_id = :category_id AND is_active = 1', [
            ':id' => $templateId,
            ':category_id' => $categoryId,
        ]);
        if (!$category || !$template) {
            throw new InvalidArgumentException('ไม่พบรูปแบบรายงานมาตรฐานที่กำหนด');
        }

        $current = self::reportDraftBase($payload, $category, $template);
        $draft = self::localReportFormDraft($current);

        self::audit('summarize', 'reports', (string) ($payload['id'] ?? ''), [
            'category' => $category['code'] ?? '',
            'template' => $template['code'] ?? '',
            'source' => 'system-data',
        ]);

        return [
            'draft' => $draft,
            'provider' => 'system-data',
            'model' => 'automatic-summary',
            'generated_at' => Database::now(),
            'category' => $category,
            'template' => $template,
        ];
    }

    private static function reportDraftBase(array $payload, array $category, array $template): array
    {
        $tournamentId = self::nullableId($payload['tournament_id'] ?? null);
        return [
            'category' => [
                'code' => (string) ($category['code'] ?? ''),
                'name' => (string) ($category['name'] ?? ''),
                'description' => (string) ($category['description'] ?? ''),
            ],
            'template' => [
                'code' => (string) ($template['code'] ?? ''),
                'name' => (string) ($template['name'] ?? ''),
                'description' => (string) ($template['description'] ?? ''),
                'sections' => self::jsonArray((string) ($template['sections_json'] ?? '[]')),
            ],
            'current' => [
                'title' => trim((string) ($payload['title'] ?? '')),
                'academic_year' => trim((string) ($payload['academic_year'] ?? '')),
                'fiscal_year' => trim((string) ($payload['fiscal_year'] ?? '')),
                'semester' => trim((string) ($payload['semester'] ?? '')),
                'start_date' => trim((string) ($payload['start_date'] ?? '')),
                'end_date' => trim((string) ($payload['end_date'] ?? '')),
                'department' => trim((string) ($payload['department'] ?? '')),
                'responsible_person' => trim((string) ($payload['responsible_person'] ?? '')),
                'prepared_by' => trim((string) ($payload['prepared_by'] ?? '')),
                'report_date' => trim((string) ($payload['report_date'] ?? date('Y-m-d'))),
                'executive_summary' => trim((string) ($payload['executive_summary'] ?? '')),
                'instruction' => trim((string) ($payload['instruction'] ?? '')),
                'general' => self::payloadObject($payload['general'] ?? []),
                'performance' => self::payloadObject($payload['performance'] ?? []),
                'outcomes' => self::payloadObject($payload['outcomes'] ?? []),
                'problems' => self::payloadObject($payload['problems'] ?? []),
                'kpis' => self::payloadArray($payload['kpis'] ?? []),
                'budget' => self::payloadObject($payload['budget'] ?? []),
            ],
            'sports_data' => self::reportSportsData($tournamentId),
        ];
    }

    private static function reportFormDraftPrompt(array $base, array $fallback): string
    {
        return "จัดทำร่างข้อความสำหรับกรอกแบบฟอร์มรายงานประชาสัมพันธ์ด้านกีฬา โดยยึดหมวดหมู่และแบบฟอร์มที่เลือก ใช้ภาษาไทยแบบทางการ กระชับ ชัดเจน เหมาะสำหรับรายงานการแข่งขันกีฬา และอ้างอิงเฉพาะข้อมูลกีฬาที่มีในระบบเท่านั้น\n"
            . "ให้เติมข้อมูลตาม section ที่มีใน template เท่านั้น ห้ามแต่งผลการแข่งขัน ตัวเลข หรือชื่อทีมที่ไม่มีในข้อมูล\n"
            . "หากข้อมูลจริงไม่เพียงพอ ให้เขียนเชิงรายงานสถานะอย่างสุภาพ และใส่ข้อเสนอแนะให้เจ้าหน้าที่ตรวจสอบเพิ่มเติม\n"
            . "JSON schema ที่ต้องตอบกลับ:\n"
            . '{"title":"","general":{"project_name":"","rationale":"","objectives":"","target_group":"","location":"","operation_date":""},"performance":{"operation_detail":""},"outcomes":{"performance_result":"","benefits":"","participant_impact":""},"problems":{"obstacles":"","solutions":"","recommendations":""},"executive_summary":"","kpis":[{"name":"","unit":"","target":0,"actual":0,"calculation_type":"percentage","achievement":0,"status":""}],"budget":{"allocated":0,"actual":0,"note":""}}' . "\n\n"
            . "ข้อมูลปัจจุบันและข้อมูลกีฬา:\n"
            . json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            . "\n\nร่างสำรองสำหรับเทียบเคียง:\n"
            . json_encode($fallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private static function localReportFormDraft(array $base): array
    {
        $current = $base['current'];
        $sports = $base['sports_data'];
        $summary = $sports['summary'] ?? [];
        $categoryName = (string) ($base['category']['name'] ?? 'รายงานประชาสัมพันธ์กีฬา');
        $templateName = (string) ($base['template']['name'] ?? 'รายงาน');
        $title = (string) ($current['title'] ?: ('รายงานสรุปผลการดำเนินงานด้านกีฬา'
            . (!empty($current['academic_year']) ? ' ประจำปี/รุ่น ' . $current['academic_year'] : '')));
        $competition = (string) (($current['general']['competition_name'] ?? '') ?: ($current['general']['project_name'] ?? '') ?: $title);
        $sportsList = self::joinThaiList(array_slice($sports['sports'] ?? [], 0, 8));
        $departmentsList = self::joinThaiList(array_slice($sports['departments'] ?? [], 0, 8));
        $completed = (int) ($summary['completed_matches'] ?? 0);
        $matches = (int) ($summary['competitions'] ?? 0);
        $athletes = (int) ($summary['athletes'] ?? 0);
        $teams = (int) ($summary['teams'] ?? 0);
        $scope = 'มีข้อมูลทีมเข้าร่วม ' . $teams . ' ทีม นักกีฬา ' . $athletes . ' คน และรายการแข่งขัน ' . $matches . ' รายการ';
        if ($sportsList !== '') {
            $scope .= ' ครอบคลุมกีฬา ' . $sportsList;
        }
        if ($departmentsList !== '') {
            $scope .= ' จากหน่วยงาน/สาขา ' . $departmentsList;
        }

        $resultLines = [];
        foreach (array_slice($sports['matches'] ?? [], 0, 6) as $match) {
            $label = trim((string) (($match['team1_name'] ?? '-') . ' ' . ($match['score_label'] ?? '') . ' ' . ($match['team2_name'] ?? '-')));
            $resultLines[] = ($match['sport_name'] ?? 'กีฬา') . ': ' . $label;
        }
        $resultText = $resultLines
            ? "ผลการแข่งขันที่บันทึกในระบบ ได้แก่ " . implode(' | ', $resultLines)
            : "ยังไม่มีผลการแข่งขันที่บันทึกครบถ้วนในระบบ จึงควรตรวจสอบและยืนยันข้อมูลก่อนเผยแพร่ประชาสัมพันธ์";

        return [
            'title' => $title,
            'general' => [
                'project_name' => $competition,
                'rationale' => 'งานประชาสัมพันธ์ด้านกีฬาจัดทำขึ้นเพื่อรวบรวมและเผยแพร่ข้อมูลการแข่งขัน กิจกรรม ผลงานนักกีฬา และภาพรวมการมีส่วนร่วมของผู้ที่เกี่ยวข้อง ให้มีความถูกต้อง เป็นระบบ และเหมาะสมต่อการสื่อสารกับผู้ชมและทีมงาน',
                'objectives' => "1. เพื่อสรุปข้อมูล{$categoryName}ให้เป็นเอกสารทางการ\n2. เพื่อใช้ประกอบการเผยแพร่ข่าวสารกีฬาและการรายงานต่อผู้บริหาร\n3. เพื่อสนับสนุนการติดตามผลการแข่งขันและการพัฒนางานประชาสัมพันธ์กีฬา",
                'target_group' => 'ผู้จัดการแข่งขัน เจ้าหน้าที่ประชาสัมพันธ์ ผู้ควบคุมทีม นักกีฬา ผู้ชม และผู้มีส่วนเกี่ยวข้อง',
                'location' => (string) ($current['general']['location'] ?? ''),
                'operation_date' => (string) (($current['general']['operation_date'] ?? '') ?: ($current['start_date'] ?? '')),
            ],
            'performance' => [
                'operation_detail' => "การดำเนินงาน{$categoryName} ดำเนินการโดยรวบรวมข้อมูลจากระบบจัดการกีฬา ตรวจสอบรายการแข่งขัน ทีม นักกีฬา และผลการแข่งขันที่เกี่ยวข้อง จากนั้นเรียบเรียงเป็นรายงานประชาสัมพันธ์ในรูปแบบทางการ โดย{$scope} และมีการแข่งขันที่บันทึกผลแล้ว {$completed} รายการ",
            ],
            'outcomes' => [
                'performance_result' => $resultText,
                'benefits' => 'หน่วยงานสามารถนำข้อมูลไปใช้ประกอบการเผยแพร่ข่าวสาร สรุปผลการดำเนินงาน จัดทำเอกสารเสนอผู้บริหาร และเก็บเป็นหลักฐานอ้างอิงสำหรับการจัดกิจกรรมกีฬาครั้งต่อไป',
                'participant_impact' => 'ผู้เข้าร่วมและนักกีฬาได้รับการนำเสนอผลงานอย่างเป็นระบบ ช่วยสร้างการรับรู้ ความภาคภูมิใจ และส่งเสริมภาพลักษณ์ที่ดีของรายการแข่งขัน',
            ],
            'problems' => [
                'obstacles' => 'ข้อมูลบางส่วนอาจยังไม่สมบูรณ์ เช่น ผลการแข่งขัน รายชื่อทีม หรือรายละเอียดผู้เข้าร่วม จึงจำเป็นต้องตรวจสอบกับผู้รับผิดชอบก่อนเผยแพร่',
                'solutions' => 'กำหนดผู้รับผิดชอบตรวจทานข้อมูลแต่ละส่วน และบันทึกผลการแข่งขันให้ครบถ้วนก่อนจัดทำรายงานฉบับสมบูรณ์',
                'recommendations' => 'ควรจัดทำแนวทางการบันทึกข้อมูลและรอบเวลาการส่งข้อมูลประชาสัมพันธ์กีฬา เพื่อให้รายงานมีความถูกต้อง ทันเวลา และนำไปใช้เผยแพร่ได้อย่างต่อเนื่อง',
            ],
            'executive_summary' => "{$templateName}ฉบับนี้จัดทำขึ้นเพื่อสรุปสาระสำคัญของงานประชาสัมพันธ์ด้านกีฬา โดย{$scope} มีผลการแข่งขันที่บันทึกแล้ว {$completed} รายการ ข้อมูลดังกล่าวสามารถใช้ประกอบการเผยแพร่ข่าวสาร การรายงานผลต่อผู้บริหาร และการพัฒนาการดำเนินงานประชาสัมพันธ์กีฬาให้มีความเป็นระบบยิ่งขึ้น",
            'kpis' => [
                ['name' => 'ความครบถ้วนของข้อมูลประชาสัมพันธ์กีฬา', 'unit' => 'ร้อยละ', 'target' => 100, 'actual' => $matches > 0 ? round(($completed / $matches) * 100, 2) : 0, 'calculation_type' => 'manual', 'achievement' => $matches > 0 ? round(($completed / $matches) * 100, 2) : 0, 'status' => $completed >= $matches && $matches > 0 ? 'บรรลุเป้าหมาย' : 'อยู่ระหว่างดำเนินการ'],
                ['name' => 'จำนวนรายการแข่งขันที่ใช้ประกอบการสื่อสาร', 'unit' => 'รายการ', 'target' => $matches, 'actual' => $completed, 'calculation_type' => 'percentage', 'achievement' => 0, 'status' => 'อ้างอิงจากข้อมูลในระบบ'],
            ],
            'budget' => [
                'allocated' => (float) ($current['budget']['allocated'] ?? 0),
                'actual' => (float) ($current['budget']['actual'] ?? 0),
                'note' => (string) (($current['budget']['note'] ?? '') ?: 'กรุณาตรวจสอบรายละเอียดงบประมาณจากเอกสารการเงินหรือผู้รับผิดชอบก่อนเผยแพร่รายงาน'),
            ],
        ];
    }

    private static function normalizeReportFormDraft(array $draft, array $fallback): array
    {
        foreach (['general', 'performance', 'outcomes', 'problems', 'budget'] as $group) {
            $draft[$group] = self::payloadObject($draft[$group] ?? []);
            $fallback[$group] = self::payloadObject($fallback[$group] ?? []);
            $draft[$group] = array_merge($fallback[$group], array_filter($draft[$group], static fn($value): bool => $value !== null && $value !== ''));
        }
        $draft['title'] = trim((string) ($draft['title'] ?? '')) ?: $fallback['title'];
        $draft['executive_summary'] = trim((string) ($draft['executive_summary'] ?? '')) ?: $fallback['executive_summary'];
        $kpis = self::payloadArray($draft['kpis'] ?? []);
        $draft['kpis'] = $kpis ?: ($fallback['kpis'] ?? []);
        return $draft;
    }

    private static function decodeAiJsonObject(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private static function joinThaiList(array $items): string
    {
        $items = array_values(array_filter(array_map(static fn($value): string => trim((string) $value), $items)));
        if (!$items) {
            return '';
        }
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);
        return implode(', ', $items) . ' และ' . $last;
    }

    private static function validateReportPayload(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $categoryId = (int) ($payload['category_id'] ?? 0);
        $templateId = (int) ($payload['template_id'] ?? 0);
        if ($title === '' || mb_strlen($title) > 255) {
            throw new InvalidArgumentException('กรุณาระบุชื่อรายงานไม่เกิน 255 ตัวอักษร');
        }
        $category = self::fetchOne('SELECT * FROM report_categories WHERE id = :id AND is_active = 1', [':id' => $categoryId]);
        $template = self::fetchOne('SELECT * FROM report_templates WHERE id = :id AND category_id = :category_id AND is_active = 1', [
            ':id' => $templateId,
            ':category_id' => $categoryId,
        ]);
        if (!$category || !$template) {
            throw new InvalidArgumentException('หมวดหมู่หรือประเภทแบบฟอร์มไม่ถูกต้อง');
        }
        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
            throw new InvalidArgumentException('วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มต้น');
        }
        $status = (string) ($payload['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'review', 'completed', 'published'], true)) {
            throw new InvalidArgumentException('สถานะรายงานไม่ถูกต้อง');
        }
        $general = self::payloadObject($payload['general'] ?? []);
        $performance = self::payloadObject($payload['performance'] ?? []);
        $outcomes = self::payloadObject($payload['outcomes'] ?? []);
        $problems = self::payloadObject($payload['problems'] ?? []);
        return [
            'title' => $title,
            'category_id' => $categoryId,
            'template_id' => $templateId,
            'tournament_id' => self::nullableId($payload['tournament_id'] ?? null),
            'academic_year' => trim((string) ($payload['academic_year'] ?? '')),
            'fiscal_year' => trim((string) ($payload['fiscal_year'] ?? '')),
            'semester' => trim((string) ($payload['semester'] ?? '')),
            'start_date' => $startDate ?: null,
            'end_date' => $endDate ?: null,
            'department' => trim((string) ($payload['department'] ?? '')),
            'responsible_person' => trim((string) ($payload['responsible_person'] ?? '')),
            'prepared_by' => trim((string) ($payload['prepared_by'] ?? '')),
            'report_date' => trim((string) ($payload['report_date'] ?? date('Y-m-d'))),
            'status' => $status,
            'general_json' => json_encode($general, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'performance_json' => json_encode($performance, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'outcomes_json' => json_encode($outcomes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'problems_json' => json_encode($problems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'executive_summary' => trim((string) ($payload['executive_summary'] ?? '')),
            'published_at' => $status === 'published' ? Database::now() : null,
        ];
    }

    private static function saveReportKpis(string $reportId, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $target = self::nonNegativeNumber($item['target'] ?? $item['target_value'] ?? 0, 'ค่าเป้าหมาย KPI');
            $actual = self::nonNegativeNumber($item['actual'] ?? $item['actual_value'] ?? 0, 'ผลการดำเนินงาน KPI');
            $type = (string) ($item['calculation_type'] ?? 'percentage');
            if (!in_array($type, ['percentage', 'manual'], true)) {
                $type = 'percentage';
            }
            $achievement = $type === 'percentage'
                ? ($target > 0 ? round(($actual / $target) * 100, 2) : 0)
                : self::nonNegativeNumber($item['achievement'] ?? 0, 'ร้อยละความสำเร็จ KPI');
            $status = trim((string) ($item['status'] ?? ''));
            if ($status === '') {
                $status = $type === 'percentage' && $target <= 0 ? 'ไม่ใช้สูตร' : ($achievement >= 100 ? 'บรรลุเป้าหมาย' : 'อยู่ระหว่างดำเนินการ');
            }
            self::insertRow('report_kpis', [
                'id' => self::nextId('report_kpis'),
                'report_id' => (int) $reportId,
                'name' => $name,
                'unit' => trim((string) ($item['unit'] ?? '')),
                'target_value' => $target,
                'actual_value' => $actual,
                'calculation_type' => $type,
                'achievement' => $achievement,
                'status' => $status,
                'display_order' => $index,
            ]);
        }
    }

    private static function saveReportBudget(string $reportId, array $budget): void
    {
        $allocated = self::nonNegativeNumber($budget['allocated'] ?? 0, 'งบประมาณที่ได้รับ');
        $actual = self::nonNegativeNumber($budget['actual'] ?? 0, 'งบประมาณที่ใช้จริง');
        $remaining = round($allocated - $actual, 2);
        $usage = $allocated > 0 ? round(($actual / $allocated) * 100, 2) : 0;
        self::insertRow('report_budgets', [
            'id' => self::nextId('report_budgets'),
            'report_id' => (int) $reportId,
            'allocated' => $allocated,
            'actual' => $actual,
            'remaining' => $remaining,
            'usage_percentage' => $usage,
            'note' => trim((string) ($budget['note'] ?? '')),
        ]);
    }

    private static function enrichReportSummary(array $row): array
    {
        $row['status_label'] = self::reportStatusLabel((string) ($row['status'] ?? ''));
        $row['report_date_label'] = (string) ($row['report_date'] ?: $row['created_at'] ?? '');
        return $row;
    }

    private static function enrichReport(array $row): array
    {
        $row = self::enrichReportSummary($row);
        $row['sections'] = self::jsonArray((string) ($row['sections_json'] ?? '[]'));
        $row['general'] = self::jsonObject((string) ($row['general_json'] ?? '{}'));
        $row['performance'] = self::jsonObject((string) ($row['performance_json'] ?? '{}'));
        $row['outcomes'] = self::jsonObject((string) ($row['outcomes_json'] ?? '{}'));
        $row['problems'] = self::jsonObject((string) ($row['problems_json'] ?? '{}'));
        $row['kpis'] = self::fetchAll('SELECT * FROM report_kpis WHERE report_id = :id ORDER BY display_order ASC, id ASC', [':id' => $row['id']]);
        $row['budget'] = self::fetchOne('SELECT * FROM report_budgets WHERE report_id = :id LIMIT 1', [':id' => $row['id']])
            ?: ['allocated' => 0, 'actual' => 0, 'remaining' => 0, 'usage_percentage' => 0, 'note' => ''];
        $row['attachments'] = self::fetchAll('SELECT * FROM report_attachments WHERE report_id = :id ORDER BY id DESC', [':id' => $row['id']]);
        $row['sports_data'] = self::reportSportsData(self::nullableId($row['tournament_id'] ?? null));
        return $row;
    }

    private static function reportSportsData(?int $tournamentId): array
    {
        $teams = array_values(array_filter(self::joinedTeams(), fn(array $row): bool => $tournamentId === null || (int) ($row['tournament_id'] ?? 0) === $tournamentId));
        $matches = array_values(array_filter(self::joinedMatches(), fn(array $row): bool => $tournamentId === null || (int) ($row['tournament_id'] ?? 0) === $tournamentId));
        $teamIds = array_map(fn(array $row): string => (string) $row['id'], $teams);
        $members = array_values(array_filter(self::joinedTeamMembers(''), fn(array $row): bool => in_array((string) ($row['team_id'] ?? ''), $teamIds, true)));
        $sports = array_values(array_unique(array_filter(array_map(fn(array $row): string => (string) ($row['sport_name'] ?? ''), $teams))));
        $departments = array_values(array_unique(array_filter(array_map(fn(array $row): string => (string) ($row['department_name'] ?? ''), $teams))));
        $studentIds = array_values(array_unique(array_filter(array_map(fn(array $row): string => (string) ($row['student_id'] ?? ''), $members))));
        $completed = array_values(array_filter($matches, fn(array $row): bool => (string) ($row['status'] ?? '') === 'completed'));
        $wins = [];
        foreach ($completed as $match) {
            $winner = '';
            if (!empty($match['winner_team_id'])) {
                $winner = (string) $match['winner_team_id'] === (string) ($match['team1_id'] ?? '')
                    ? (string) ($match['team1_name'] ?? '')
                    : (string) ($match['team2_name'] ?? '');
            }
            if ($winner !== '') {
                $wins[$winner] = ($wins[$winner] ?? 0) + 1;
            }
        }
        arsort($wins);

        $sportBreakdown = [];
        foreach ($sports as $sportName) {
            $sportTeams = array_values(array_filter($teams, fn(array $row): bool => (string) ($row['sport_name'] ?? '') === $sportName));
            $sportTeamIds = array_map(fn(array $row): string => (string) $row['id'], $sportTeams);
            $sportMembers = array_values(array_filter($members, fn(array $row): bool => in_array((string) ($row['team_id'] ?? ''), $sportTeamIds, true)));
            $sportMatches = array_values(array_filter($matches, fn(array $row): bool => (string) ($row['sport_name'] ?? '') === $sportName));
            $sportBreakdown[] = [
                'name' => $sportName,
                'teams' => count($sportTeams),
                'athletes' => count(array_unique(array_filter(array_map(fn(array $row): string => (string) ($row['student_id'] ?? ''), $sportMembers)))),
                'matches' => count($sportMatches),
                'completed_matches' => count(array_filter($sportMatches, fn(array $row): bool => (string) ($row['status'] ?? '') === 'completed')),
            ];
        }

        $departmentBreakdown = [];
        foreach ($departments as $departmentName) {
            $departmentTeams = array_values(array_filter($teams, fn(array $row): bool => (string) ($row['department_name'] ?? '') === $departmentName));
            $departmentTeamIds = array_map(fn(array $row): string => (string) $row['id'], $departmentTeams);
            $departmentMembers = array_values(array_filter($members, fn(array $row): bool => in_array((string) ($row['team_id'] ?? ''), $departmentTeamIds, true)));
            $departmentBreakdown[] = [
                'name' => $departmentName,
                'teams' => count($departmentTeams),
                'athletes' => count(array_unique(array_filter(array_map(fn(array $row): string => (string) ($row['student_id'] ?? ''), $departmentMembers)))),
            ];
        }

        $standingParams = [];
        $standingWhere = '';
        if ($tournamentId !== null) {
            $standingWhere = ' WHERE standings.tournament_id = :tournament_id';
            $standingParams[':tournament_id'] = $tournamentId;
        }
        $standings = self::fetchAll(
            'SELECT standings.*, teams.name AS team_name, sports.name AS sport_name,
                    departments.name AS department_name, tournaments.name AS tournament_name
             FROM standings
             JOIN teams ON teams.id = standings.team_id
             LEFT JOIN sports ON sports.id = teams.sport_id
             LEFT JOIN departments ON departments.code = teams.department_code
             LEFT JOIN tournaments ON tournaments.id = standings.tournament_id'
             . $standingWhere
             . ' ORDER BY tournaments.name, sports.name, standings.rank_number, teams.name LIMIT 200',
            $standingParams
        );

        $newsRows = self::fetchAll("SELECT status, news_type FROM news");
        return [
            'summary' => [
                'sports' => count($sports),
                'competitions' => count($matches),
                'departments' => count($departments),
                'athletes' => count($studentIds),
                'personnel' => 0,
                'participants' => count($studentIds),
                'completed_matches' => count($completed),
                'teams' => count($teams),
            ],
            'sports' => $sports,
            'departments' => $departments,
            'matches' => $matches,
            'result_summary' => array_map(fn(string $name, int $count): array => ['name' => $name, 'wins' => $count], array_keys($wins), array_values($wins)),
            'sport_breakdown' => $sportBreakdown,
            'department_breakdown' => $departmentBreakdown,
            'standings' => $standings,
            'communications' => [
                'news_total' => count($newsRows),
                'news_published' => count(array_filter($newsRows, fn(array $row): bool => (string) ($row['status'] ?? '') === 'published')),
                'hall_of_fame' => count(array_filter($newsRows, fn(array $row): bool => (string) ($row['news_type'] ?? '') === 'hall_of_fame' && (string) ($row['status'] ?? '') === 'published')),
            ],
        ];
    }

    private static function reportStatusLabel(string $status): string
    {
        return [
            'draft' => 'แบบร่าง',
            'review' => 'รอตรวจสอบ',
            'completed' => 'เสร็จสมบูรณ์',
            'published' => 'เผยแพร่แล้ว',
        ][$status] ?? $status;
    }

    private static function payloadObject($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function payloadArray($value): array
    {
        $decoded = self::payloadObject($value);
        return self::isList($decoded) ? $decoded : [];
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

    private static function nonNegativeNumber($value, string $label): float
    {
        if ($value === '' || $value === null) {
            return 0.0;
        }
        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException($label . 'ต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป');
        }
        return round((float) $value, 2);
    }

    public static function generateFormalReport(array $payload): array
    {
        Auth::canManageMasterData();
        $dataset = self::aiDataset('report');
        $dataset['report_type'] = (string) ($payload['report_type'] ?? 'executive');
        $dataset['output_type'] = (string) ($payload['output_type'] ?? 'both');
        $dataset['instruction'] = trim((string) ($payload['instruction'] ?? ''));
        $title = 'รายงานระบบกีฬา ' . $dataset['report_type'] . ' ' . date('Ymd-His');
        $analysis = self::localFormalReport($dataset);
        $client = self::aiClient();
        $provider = 'local-summary';
        if ($client->isConfigured()) {
            try {
                $text = $client->generateText(
                    self::thaiSportsSystemInstruction(),
                    self::aiPrompt('report', $dataset, $dataset['instruction']),
                    ['temperature' => 0.25, 'max_output_tokens' => 2400]
                );
                $analysis = self::structuredAiResult($text, $analysis, $client->model(), 'google-gemini');
                $provider = 'google-gemini';
            } catch (Throwable $error) {
                self::audit('ai_fallback', 'ai', 'report', ['error' => $error->getMessage(), 'model' => $client->model()]);
                $analysis['fallback_reason'] = $error->getMessage();
            }
        }
        $result = [
            'title' => $title,
            'report_type' => $dataset['report_type'],
            'report_type_label' => self::reportTypeLabel((string) $dataset['report_type']),
            'executive_summary' => $analysis['summary'],
            'analysis' => $analysis['next_actions'],
            'recommendations' => $analysis['recommendations'],
            'risks' => $analysis['risks'] ?? [],
            'metrics' => $dataset['summary'],
            'created_at' => date(DATE_ATOM),
            'created_at_label' => Database::now(),
            'generated_by' => Auth::currentUser()['name'] ?? Auth::currentUser()['email'] ?? '',
            'model' => $analysis['model'] ?? $client->model(),
            'provider' => $provider,
        ];

        $dir = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $base = 'report-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $jsonPath = $dir . DIRECTORY_SEPARATOR . $base . '.json';
        file_put_contents($jsonPath, json_encode(['result' => $result, 'dataset' => $dataset], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $csvPath = $dir . DIRECTORY_SEPARATOR . $base . '.csv';
        $fh = fopen($csvPath, 'wb');
        fputcsv($fh, ['metric', 'value']);
        foreach ($dataset['summary'] as $key => $value) {
            fputcsv($fh, [$key, $value]);
        }
        fclose($fh);
        $html = self::formalReportHtml($result);
        $htmlPath = $dir . DIRECTORY_SEPARATOR . $base . '.html';
        file_put_contents($htmlPath, $html);
        $docPath = $dir . DIRECTORY_SEPARATOR . $base . '.doc';
        file_put_contents($docPath, $html);

        $result['report_url'] = app_url('public/reports/' . basename($htmlPath));
        $result['report_id'] = basename($htmlPath);
        $result['word_url'] = app_url('public/reports/' . basename($docPath));
        $result['word_id'] = basename($docPath);
        $result['document_url'] = app_url('public/reports/' . basename($jsonPath));
        $result['document_id'] = basename($jsonPath);
        $result['sheet_url'] = app_url('public/reports/' . basename($csvPath));
        $result['sheet_id'] = basename($csvPath);
        self::audit('generate_formal_report', 'reports', '', ['report_type' => $dataset['report_type'], 'document_id' => $result['document_id'], 'sheet_id' => $result['sheet_id']]);
        return $result;
    }

    private static function aiClient(): GeminiClient
    {
        return new GeminiClient();
    }

    private static function aiDataset(string $scope): array
    {
        $dashboard = self::dashboardSummary();
        $counts = $dashboard['counts'];
        $dataset = [
            'scope' => $scope,
            'generated_at' => Database::now(),
            'settings' => self::settings(),
            'counts' => $counts,
            'summary' => $counts,
            'sports' => self::fetchAll('SELECT id, name, type, gender, min_players, max_players, reserve_players, is_active FROM sports ORDER BY id DESC LIMIT 80'),
            'departments' => self::fetchAll('SELECT code, name, is_active FROM departments ORDER BY code ASC LIMIT 80'),
            'tournaments' => array_slice(self::listTournaments(), 0, 50),
            'teams' => array_slice(self::joinedTeams(), 0, 80),
            'matches' => array_slice(self::joinedMatches(), 0, 100),
            'team_members' => array_slice(self::joinedTeamMembers(''), 0, 120),
            'news' => self::enrichedNewsRows(self::fetchAll('SELECT id, title, excerpt, news_type, department_code, status, is_pinned, published_at, author_email, created_at FROM news ORDER BY id DESC LIMIT 60')),
            'recent_audit' => $dashboard['recentAudit'] ?? [],
        ];

        foreach ($dataset['teams'] as &$team) {
            unset($team['rejection_reason'], $team['approved_by'], $team['created_by']);
        }
        unset($team);
        foreach ($dataset['team_members'] as &$member) {
            unset($member['phone'], $member['card_raw_data']);
        }
        unset($member);
        return $dataset;
    }

    private static function thaiSportsSystemInstruction(): string
    {
        return 'You are an assistant for a Thai university sports management system. Answer in formal Thai suitable for Thai government and university official documents. Use polite, precise, evidence-based wording. Avoid slang, casual expressions, promotional language, exclamation marks, and unsupported claims. Use only the supplied dataset. Do not invent scores, teams, dates, or people. Return JSON when the user asks for JSON.';
    }

    private static function aiPrompt(string $task, array $dataset, string $instruction = ''): string
    {
        $json = json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $extra = $instruction !== '' ? "\nคำสั่งเพิ่มเติมจากผู้ใช้: {$instruction}\n" : '';
        if ($task === 'news') {
            return "สร้างร่างข่าวประชาสัมพันธ์ภาษาไทยจาก selected_match โดยทำตาม news_request ทั้งรูปแบบข่าว โทน และประเด็นเน้น ใช้ข้อเท็จจริงจากข้อมูลเท่านั้น เนื้อหาควรพร้อมเผยแพร่และแบ่งย่อหน้าอ่านง่าย\n{$extra}ตอบกลับเป็น JSON เท่านั้น รูปแบบ {\"title\":\"...\",\"excerpt\":\"...\",\"body\":\"...\",\"banner_title\":\"ข้อความสั้นสำหรับแบนเนอร์\",\"banner_subtitle\":\"ชื่อรายการหรือรายละเอียดสั้น\"}\nDataset:\n{$json}";
        }
        if ($task === 'report') {
            return "จัดทำสรุปผลรายงานเชิงราชการภาษาไทยจากข้อมูลระบบกีฬา สำหรับเสนอผู้บริหารหรือคณะกรรมการดำเนินงาน โดยใช้ถ้อยคำสุภาพ เป็นทางการ กระชับ ชัดเจน และยึดข้อมูลจากชุดข้อมูลเท่านั้น
ข้อกำหนดด้านภาษา:
- ใช้สำนวนเอกสารราชการ เช่น “จากการตรวจสอบข้อมูลพบว่า”, “มีผลการดำเนินงาน”, “ควรดำเนินการ”, “เพื่อให้การดำเนินงานเป็นไปด้วยความเรียบร้อย”
- หลีกเลี่ยงภาษาพูด คำโฆษณา ถ้อยคำเกินจริง เครื่องหมายอัศเจรีย์ และการคาดเดาที่ไม่มีข้อมูลรองรับ
- summary ให้เป็นย่อหน้าเดียวในรูปแบบสรุปเพื่อรายงานผู้บริหาร ไม่ใช้ bullet
- next_actions ให้เป็นรายการข้อดำเนินการถัดไปในเชิงราชการ ขึ้นต้นด้วยคำกริยาทางการ เช่น “ตรวจสอบ”, “ดำเนินการ”, “ประสานงาน”, “จัดทำ”, “เสนอ”
- risks ให้ระบุเป็นประเด็นความเสี่ยงหรือข้อควรพิจารณาอย่างเป็นกลาง
- recommendations ให้เป็นข้อเสนอแนะเชิงปฏิบัติที่เหมาะกับหน่วยงานการศึกษา
{$extra}ตอบกลับเป็น JSON เท่านั้น รูปแบบ {\"summary\":\"...\",\"next_actions\":[\"...\"],\"risks\":[\"...\"],\"recommendations\":[\"...\"]}\nDataset:\n{$json}";
        }
        if ($task === 'system') {
            return "สรุปภาพรวมระบบ อัปเดตความคืบหน้า และชี้ทางการดำเนินงานถัดไปจากข้อมูลปัจจุบัน\n{$extra}ตอบกลับเป็น JSON เท่านั้น รูปแบบ {\"summary\":\"...\",\"next_actions\":[\"...\"],\"risks\":[\"...\"],\"recommendations\":[\"...\"]}\nDataset:\n{$json}";
        }
        return "สรุปสถานะแดชบอร์ดและงานที่ควรทำต่อจากข้อมูลระบบกีฬา\n{$extra}ตอบกลับเป็น JSON เท่านั้น รูปแบบ {\"summary\":\"...\",\"next_actions\":[\"...\"],\"risks\":[\"...\"],\"recommendations\":[\"...\"]}\nDataset:\n{$json}";
    }

    private static function structuredAiResult(string $text, array $fallback, string $model, string $provider): array
    {
        $json = self::jsonObjectFromText($text);
        $summary = trim((string) ($json['summary'] ?? ''));
        if ($summary === '' && str_starts_with(ltrim($text), '{')) {
            $summary = (string) ($fallback['summary'] ?? '');
        }
        return [
            'summary' => $summary !== '' ? $summary : trim((string) ($text ?: $fallback['summary'])),
            'next_actions' => self::stringList($json['next_actions'] ?? $fallback['next_actions'] ?? []),
            'risks' => self::stringList($json['risks'] ?? $fallback['risks'] ?? []),
            'recommendations' => self::stringList($json['recommendations'] ?? $fallback['recommendations'] ?? []),
            'model' => $model,
            'provider' => $provider,
        ];
    }

    private static function jsonObjectFromText(string $text): array
    {
        $trimmed = trim($text);
        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{.*\}/s', $trimmed, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private static function stringList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn($item): string => trim((string) $item), $value), fn(string $item): bool => $item !== ''));
        }
        $text = trim((string) $value);
        return $text === '' ? [] : [$text];
    }

    private static function storedReportHtml(array $report): string
    {
        $e = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $p = static fn($value): string => trim((string) $value) !== ''
            ? '<p>' . nl2br($e($value)) . '</p>'
            : '<p class="muted">ไม่มีข้อมูลที่บันทึกในระบบ</p>';
        $sportsData = $report['sports_data'] ?? [];
        $summary = $sportsData['summary'] ?? [];
        $general = $report['general'] ?? [];
        $outcomes = $report['outcomes'] ?? [];
        $problems = $report['problems'] ?? [];
        $communications = $sportsData['communications'] ?? [];
        $logo = app_url('public/assets/sports/general.svg');

        $metricLabels = [
            'sports' => 'ชนิดกีฬา',
            'teams' => 'ทีม',
            'athletes' => 'นักกีฬา',
            'departments' => 'หน่วยงาน',
            'competitions' => 'แมตช์ทั้งหมด',
            'completed_matches' => 'แมตช์เสร็จสิ้น',
        ];
        $metricCards = '';
        foreach ($metricLabels as $key => $label) {
            $metricCards .= '<div><strong>' . $e($summary[$key] ?? 0) . '</strong><span>' . $e($label) . '</span></div>';
        }

        $sportRows = '';
        foreach ($sportsData['sport_breakdown'] ?? [] as $index => $row) {
            $sportRows .= '<tr><td class="center">' . ($index + 1) . '</td><td>' . $e($row['name']) . '</td><td class="number">' . $e($row['teams']) . '</td><td class="number">' . $e($row['athletes']) . '</td><td class="number">' . $e($row['matches']) . '</td><td class="number">' . $e($row['completed_matches']) . '</td></tr>';
        }

        $departmentRows = '';
        foreach ($sportsData['department_breakdown'] ?? [] as $index => $row) {
            $departmentRows .= '<tr><td class="center">' . ($index + 1) . '</td><td>' . $e($row['name']) . '</td><td class="number">' . $e($row['teams']) . '</td><td class="number">' . $e($row['athletes']) . '</td></tr>';
        }

        $resultRows = '';
        foreach ($sportsData['matches'] ?? [] as $index => $match) {
            $winner = '-';
            if (!empty($match['winner_team_id'])) {
                $winner = (string) $match['winner_team_id'] === (string) ($match['team1_id'] ?? '')
                    ? (string) ($match['team1_name'] ?? '-')
                    : (string) ($match['team2_name'] ?? '-');
            }
            $resultRows .= '<tr><td class="center">' . ($index + 1) . '</td><td>' . $e($match['sport_name'] ?? '-') . '</td><td>' . $e($match['round_name'] ?? '-') . '</td><td>' . $e(($match['team1_name'] ?? '-') . ' พบ ' . ($match['team2_name'] ?? '-')) . '</td><td class="center">' . $e($match['score_label'] ?? '-') . '</td><td>' . $e($winner) . '</td></tr>';
        }

        $standingRows = '';
        foreach ($sportsData['standings'] ?? [] as $index => $row) {
            $standingRows .= '<tr><td class="center">' . $e($row['rank_number'] ?? ($index + 1)) . '</td><td>' . $e($row['sport_name'] ?? '-') . '</td><td>' . $e($row['team_name'] ?? '-') . '</td><td>' . $e($row['department_name'] ?? '-') . '</td><td class="number">' . $e($row['played'] ?? 0) . '</td><td class="number">' . $e($row['won'] ?? 0) . '</td><td class="number">' . $e($row['drawn'] ?? 0) . '</td><td class="number">' . $e($row['lost'] ?? 0) . '</td><td class="number">' . $e($row['points'] ?? 0) . '</td></tr>';
        }

        $kpiRows = '';
        foreach ($report['kpis'] ?? [] as $index => $kpi) {
            $kpiRows .= '<tr><td class="center">' . ($index + 1) . '</td><td>' . $e($kpi['name'] ?? '-') . '</td><td>' . $e($kpi['unit'] ?? '-') . '</td><td class="number">' . $e($kpi['target_value'] ?? 0) . '</td><td class="number">' . $e($kpi['actual_value'] ?? 0) . '</td><td class="number">' . $e($kpi['achievement'] ?? 0) . '%</td><td>' . $e($kpi['status'] ?? '-') . '</td></tr>';
        }

        $attachments = '';
        foreach ($report['attachments'] ?? [] as $index => $file) {
            $attachments .= '<li>' . ($index + 1) . '. ' . $e($file['file_name'] ?? '-') . ($file['note'] ? ' - ' . $e($file['note']) : '') . '</li>';
        }

        $reportScope = $report['tournament_name'] ?: 'ข้อมูลการแข่งขันทั้งหมดในระบบ';
        $period = ($report['start_date'] ?: '-') . ' ถึง ' . ($report['end_date'] ?: '-');
        $budget = $report['budget'] ?? [];

        return '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>' . $e($report['title']) . '</title>
<style>
@font-face{font-family:Sarabun;src:url("../assets/sarabun-regular.ttf")}@font-face{font-family:Sarabun;src:url("../assets/sarabun-bold.ttf");font-weight:700}
@page{size:A4;margin:16mm 15mm 18mm}*{box-sizing:border-box}html,body{margin:0;padding:0}body{background:#e9edf2;color:#1f2937;font:14px/1.68 Sarabun,"TH Sarabun New",sans-serif}
main{width:210mm;margin:18px auto;background:#fff;box-shadow:0 14px 42px #0f172a22;padding:18mm 16mm}.cover{min-height:255mm;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;page-break-after:always}.cover img{width:45mm;max-height:32mm;object-fit:contain;margin-bottom:18mm}.cover .institution{font-size:17px}.cover h1{font-size:28px;line-height:1.45;margin:13mm 0 4mm;color:#123c37}.cover h2{border:0;margin:0;font-size:19px;color:#374151}.cover .scope{margin-top:15mm;font-size:16px}.cover .prepared{margin-top:auto;width:100%;border-top:1px solid #9ca3af;padding-top:8mm}
.book-page{page-break-before:always}.book-page:first-of-type{page-break-before:auto}h2{font-size:19px;color:#123c37;border-bottom:2px solid #16675f;margin:0 0 10px;padding-bottom:5px}h3{font-size:16px;margin:16px 0 7px;color:#244b47}p{margin:0 0 10px;text-align:justify}.muted{color:#6b7280}.meta,table{width:100%;border-collapse:collapse;margin:8px 0 14px;font-size:12px}.meta th{width:22%}th,td{border:1px solid #aeb8c4;padding:6px 7px;vertical-align:top}th{background:#eef4f3;font-weight:700;text-align:left}.center{text-align:center}.number{text-align:right}.metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:14px 0}.metrics div{border:1px solid #9fb5b2;background:#f4f8f7;padding:10px;text-align:center}.metrics strong{display:block;font-size:22px;color:#0f5f57}.metrics span{font-size:11px;color:#4b5563}.summary-box{border-left:5px solid #16675f;background:#f4f8f7;padding:12px 14px;margin:12px 0}.avoid-break,table,tr,.signature{break-inside:avoid;page-break-inside:avoid}.toc{list-style:none;padding:0}.toc li{display:flex;border-bottom:1px dotted #9ca3af;padding:7px 0}.toc span:last-child{margin-left:auto}.signature{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-top:30mm;text-align:center}.signature div{padding-top:20px}ol.attachments{padding-left:0;list-style:none}.report-footer{margin-top:20px;padding-top:8px;border-top:1px solid #9ca3af;text-align:center;color:#6b7280;font-size:11px}
@media print{body{background:#fff}main{width:auto;margin:0;padding:0;box-shadow:none}.cover{min-height:260mm}}
</style></head><body><main>
<section class="cover"><img src="' . $e($logo) . '" alt="โลโก้การแข่งขัน"><div class="institution">SPORT TOURNAMENT</div><h1>' . $e($report['title']) . '</h1><h2>รายงานสรุปผลจากระบบบริหารจัดการแข่งขันกีฬา</h2><div class="scope">ปี/รุ่น ' . $e($report['academic_year'] ?: '-') . '<br>' . $e($reportScope) . '</div><div class="prepared">จัดทำโดย ' . $e($report['prepared_by'] ?: '-') . '<br>วันที่จัดทำ ' . $e($report['report_date'] ?: $report['created_at']) . '</div></section>
<section class="book-page"><h2>ข้อมูลเอกสาร</h2><table class="meta"><tbody><tr><th>ชื่อรายงาน</th><td colspan="3">' . $e($report['title']) . '</td></tr><tr><th>ขอบเขตข้อมูล</th><td colspan="3">' . $e($reportScope) . '</td></tr><tr><th>ปี/รุ่น</th><td>' . $e($report['academic_year'] ?: '-') . '</td><th>ปีงบประมาณ</th><td>' . $e($report['fiscal_year'] ?: '-') . '</td></tr><tr><th>ช่วงข้อมูล</th><td>' . $e($period) . '</td><th>สถานะ</th><td>' . $e($report['status_label']) . '</td></tr><tr><th>หน่วยงาน</th><td>' . $e($report['department'] ?: '-') . '</td><th>ผู้รับผิดชอบ</th><td>' . $e($report['responsible_person'] ?: '-') . '</td></tr><tr><th>ผู้จัดทำ</th><td>' . $e($report['prepared_by'] ?: '-') . '</td><th>แหล่งข้อมูล</th><td>ระบบ SPORT TOURNAMENT</td></tr></tbody></table><h2>สารบัญ</h2><ol class="toc"><li><span>บทสรุปผู้บริหาร</span><span>1</span></li><li><span>1. ภาพรวมผลการดำเนินงาน</span><span>2</span></li><li><span>2. การมีส่วนร่วมจำแนกตามกีฬาและหน่วยงาน</span><span>3</span></li><li><span>3. ผลการแข่งขันและตารางคะแนน</span><span>4</span></li><li><span>4. ตัวชี้วัด งบประมาณ และการสื่อสาร</span><span>5</span></li><li><span>5. ผลลัพธ์ ปัญหา และข้อเสนอแนะ</span><span>6</span></li></ol></section>
<section class="book-page"><h2>บทสรุปผู้บริหาร</h2><div class="summary-box">' . $p($report['executive_summary'] ?? '') . '</div><div class="metrics">' . $metricCards . '</div><h3>ขอบเขตและที่มาของข้อมูล</h3><p>รายงานฉบับนี้ประมวลผลจากข้อมูลกีฬา ทีม นักกีฬา ตารางแข่งขัน ผลการแข่งขัน ตารางคะแนน และข่าวประชาสัมพันธ์ที่บันทึกอยู่ในระบบ SPORT TOURNAMENT ณ วันที่จัดทำรายงาน โดยใช้ข้อมูลในขอบเขต ' . $e($reportScope) . '</p></section>
<section class="book-page"><h2>1. ภาพรวมผลการดำเนินงาน</h2><h3>1.1 ข้อมูลทั่วไป</h3><table class="meta"><tbody><tr><th>โครงการ/กิจกรรม</th><td>' . $e($general['project_name'] ?? $report['title']) . '</td></tr><tr><th>การแข่งขัน</th><td>' . $e($general['competition_name'] ?? $reportScope) . '</td></tr><tr><th>วันที่ดำเนินการ</th><td>' . $e($general['operation_date'] ?? '-') . '</td></tr><tr><th>สถานที่</th><td>' . $e($general['location'] ?? '-') . '</td></tr></tbody></table><h3>1.2 หลักการและเหตุผล</h3>' . $p($general['rationale'] ?? '') . '<h3>1.3 วัตถุประสงค์</h3>' . $p($general['objectives'] ?? '') . '<h3>1.4 กลุ่มเป้าหมาย</h3>' . $p($general['target_group'] ?? '') . '<h3>1.5 รายละเอียดการดำเนินงาน</h3>' . $p($report['performance']['operation_detail'] ?? '') . '</section>
<section class="book-page"><h2>2. การมีส่วนร่วมจำแนกตามกีฬาและหน่วยงาน</h2><h3>2.1 ข้อมูลจำแนกตามชนิดกีฬา</h3>' . ($sportRows !== '' ? '<table><thead><tr><th>ลำดับ</th><th>กีฬา</th><th>ทีม</th><th>นักกีฬา</th><th>แมตช์</th><th>เสร็จสิ้น</th></tr></thead><tbody>' . $sportRows . '</tbody></table>' : '<p class="muted">ไม่มีข้อมูลกีฬาในขอบเขตที่เลือก</p>') . '<h3>2.2 ข้อมูลจำแนกตามหน่วยงาน</h3>' . ($departmentRows !== '' ? '<table><thead><tr><th>ลำดับ</th><th>หน่วยงาน/สาขา</th><th>ทีม</th><th>นักกีฬา</th></tr></thead><tbody>' . $departmentRows . '</tbody></table>' : '<p class="muted">ไม่มีข้อมูลหน่วยงานในขอบเขตที่เลือก</p>') . '</section>
<section class="book-page"><h2>3. ผลการแข่งขันและตารางคะแนน</h2><h3>3.1 ผลการแข่งขัน</h3>' . ($resultRows !== '' ? '<table><thead><tr><th>ลำดับ</th><th>กีฬา</th><th>รอบ</th><th>คู่แข่งขัน</th><th>ผล</th><th>ผู้ชนะ</th></tr></thead><tbody>' . $resultRows . '</tbody></table>' : '<p class="muted">ยังไม่มีผลการแข่งขันในขอบเขตที่เลือก</p>') . '<h3>3.2 ตารางคะแนน</h3>' . ($standingRows !== '' ? '<table><thead><tr><th>อันดับ</th><th>กีฬา</th><th>ทีม</th><th>หน่วยงาน</th><th>แข่ง</th><th>ชนะ</th><th>เสมอ</th><th>แพ้</th><th>คะแนน</th></tr></thead><tbody>' . $standingRows . '</tbody></table>' : '<p class="muted">ยังไม่มีตารางคะแนนในขอบเขตที่เลือก</p>') . '</section>
<section class="book-page"><h2>4. ตัวชี้วัด งบประมาณ และการสื่อสาร</h2><h3>4.1 ผลการดำเนินงานตามตัวชี้วัด</h3>' . ($kpiRows !== '' ? '<table><thead><tr><th>ลำดับ</th><th>ตัวชี้วัด</th><th>หน่วย</th><th>เป้าหมาย</th><th>ผลจริง</th><th>ร้อยละ</th><th>สถานะ</th></tr></thead><tbody>' . $kpiRows . '</tbody></table>' : '<p class="muted">ไม่มีตัวชี้วัดเพิ่มเติม</p>') . '<h3>4.2 งบประมาณ</h3><table class="meta"><tbody><tr><th>งบประมาณที่ได้รับ</th><td class="number">' . number_format((float) ($budget['allocated'] ?? 0), 2) . ' บาท</td></tr><tr><th>ใช้จริง</th><td class="number">' . number_format((float) ($budget['actual'] ?? 0), 2) . ' บาท</td></tr><tr><th>คงเหลือ</th><td class="number">' . number_format((float) ($budget['remaining'] ?? 0), 2) . ' บาท</td></tr><tr><th>ร้อยละการใช้จ่าย</th><td class="number">' . number_format((float) ($budget['usage_percentage'] ?? 0), 2) . '</td></tr></tbody></table><h3>4.3 การประชาสัมพันธ์</h3><table class="meta"><tbody><tr><th>ข่าวทั้งหมด</th><td>' . $e($communications['news_total'] ?? 0) . ' รายการ</td><th>ข่าวเผยแพร่</th><td>' . $e($communications['news_published'] ?? 0) . ' รายการ</td></tr><tr><th>Hall of Fame</th><td>' . $e($communications['hall_of_fame'] ?? 0) . ' รายการ</td><th></th><td></td></tr></tbody></table></section>
<section class="book-page"><h2>5. ผลลัพธ์ ปัญหา และข้อเสนอแนะ</h2><h3>5.1 ผลการดำเนินงาน</h3>' . $p($outcomes['performance_result'] ?? '') . '<h3>5.2 ผลลัพธ์และประโยชน์ที่ได้รับ</h3>' . $p($outcomes['benefits'] ?? '') . '<h3>5.3 ผลต่อผู้เข้าร่วมและนักกีฬา</h3>' . $p($outcomes['participant_impact'] ?? '') . '<h3>5.4 ปัญหาและอุปสรรค</h3>' . $p($problems['obstacles'] ?? '') . '<h3>5.5 วิธีการแก้ไข</h3>' . $p($problems['solutions'] ?? '') . '<h3>5.6 ข้อเสนอแนะและแนวทางพัฒนา</h3>' . $p($problems['recommendations'] ?? '') . '<h3>หลักฐานประกอบ</h3>' . ($attachments !== '' ? '<ol class="attachments">' . $attachments . '</ol>' : '<p class="muted">ไม่มีเอกสารแนบ</p>') . '<div class="signature"><div>ลงชื่อ ................................................<br>(' . $e($report['prepared_by'] ?: 'ผู้จัดทำรายงาน') . ')<br>ผู้จัดทำรายงาน</div><div>ลงชื่อ ................................................<br>(................................................)<br>ผู้ตรวจสอบ/ผู้อนุมัติ</div></div><div class="report-footer">รายงานฉบับนี้สร้างจากข้อมูลในระบบ SPORT TOURNAMENT เมื่อ ' . $e($report['report_date'] ?: $report['created_at']) . '</div></section>
</main></body></html>';
    }

    private static function legacyStoredReportHtml(array $report): string
    {
        $e = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $paragraph = static fn($value): string => trim((string) $value) !== ''
            ? '<p>' . nl2br($e($value)) . '</p>'
            : '<p class="muted">ไม่ระบุข้อมูล</p>';
        $general = $report['general'] ?? [];
        $outcomes = $report['outcomes'] ?? [];
        $problems = $report['problems'] ?? [];
        $summary = $report['sports_data']['summary'] ?? [];
        $metricLabels = [
            'participants' => 'ผู้เข้าร่วมทั้งหมด',
            'athletes' => 'นักกีฬา',
            'sports' => 'ชนิดกีฬา',
            'competitions' => 'การแข่งขัน',
            'departments' => 'หน่วยงานที่เข้าร่วม',
            'teams' => 'ทีม',
            'completed_matches' => 'การแข่งขันที่เสร็จสิ้น',
        ];
        $metricCards = '';
        foreach ($metricLabels as $key => $label) {
            $metricCards .= '<div><strong>' . $e($summary[$key] ?? 0) . '</strong><span>' . $e($label) . '</span></div>';
        }
        $kpiRows = '';
        foreach ($report['kpis'] ?? [] as $index => $kpi) {
            $kpiRows .= '<tr><td class="center">' . ($index + 1) . '</td><td>' . $e($kpi['name']) . '</td><td>' . $e($kpi['unit']) . '</td><td class="number">' . $e($kpi['target_value']) . '</td><td class="number">' . $e($kpi['actual_value']) . '</td><td class="number">' . $e($kpi['achievement']) . '%</td><td>' . $e($kpi['status']) . '</td></tr>';
        }
        $resultRows = '';
        foreach ($report['sports_data']['matches'] ?? [] as $index => $match) {
            $winner = '-';
            if (!empty($match['winner_team_id'])) {
                $winner = (string) $match['winner_team_id'] === (string) ($match['team1_id'] ?? '') ? $match['team1_name'] : $match['team2_name'];
            }
            $resultRows .= '<tr><td class="center">' . ($index + 1) . '</td><td>' . $e($match['sport_name']) . '</td><td>' . $e($match['round_name'] ?: '-') . '</td><td>' . $e($match['team1_name'] . ' พบ ' . $match['team2_name']) . '</td><td class="center">' . $e($match['score_label']) . '</td><td>' . $e($winner) . '</td></tr>';
        }
        $attachments = '';
        foreach ($report['attachments'] ?? [] as $index => $file) {
            $attachments .= '<li><a href="' . $e($file['file_url']) . '">' . $e($file['file_name']) . '</a>' . ($file['note'] ? ' - ' . $e($file['note']) : '') . '</li>';
        }
        $budget = $report['budget'] ?? [];
        $logo = app_url('public/assets/sports/general.svg');
        $sections = $report['sections'] ?? [];
        $show = static fn(string $section): bool => in_array($section, $sections, true);
        $competitionSection = $show('sports_results') ? '
          <section><h2>4. ผลการแข่งขัน</h2>
          ' . ($resultRows !== '' ? '<table><thead><tr><th>ลำดับ</th><th>ชนิดกีฬา</th><th>รอบ</th><th>ทีม/หน่วยงาน</th><th>ผลการแข่งขัน</th><th>ผู้ชนะ</th></tr></thead><tbody>' . $resultRows . '</tbody></table>' : '<p class="muted">ยังไม่มีข้อมูลผลการแข่งขันในรายการที่เลือก</p>') . '</section>' : '';
        $kpiSection = $show('kpi') ? '
          <section><h2>5. ผลการดำเนินงานตามตัวชี้วัด</h2>
          ' . ($kpiRows !== '' ? '<table><thead><tr><th>ลำดับ</th><th>ตัวชี้วัด</th><th>หน่วย</th><th>เป้าหมาย</th><th>ผลจริง</th><th>ความสำเร็จ</th><th>สถานะ</th></tr></thead><tbody>' . $kpiRows . '</tbody></table>' : '<p class="muted">ยังไม่มีข้อมูลตัวชี้วัด</p>') . '</section>' : '';
        $budgetSection = $show('budget') ? '
          <section><h2>6. งบประมาณ</h2><table class="key-value"><tbody>
          <tr><th>งบประมาณที่ได้รับ</th><td>' . number_format((float) ($budget['allocated'] ?? 0), 2) . ' บาท</td></tr>
          <tr><th>งบประมาณที่ใช้จริง</th><td>' . number_format((float) ($budget['actual'] ?? 0), 2) . ' บาท</td></tr>
          <tr><th>งบประมาณคงเหลือ</th><td>' . number_format((float) ($budget['remaining'] ?? 0), 2) . ' บาท</td></tr>
          <tr><th>ร้อยละการใช้จ่าย</th><td>' . number_format((float) ($budget['usage_percentage'] ?? 0), 2) . '</td></tr>
          </tbody></table></section>' : '';
        $attachmentSection = $show('attachments') ? '<section><h2>9. หลักฐานประกอบ</h2>' . ($attachments ? '<ol>' . $attachments . '</ol>' : '<p class="muted">ไม่มีเอกสารแนบ</p>') . '</section>' : '';
        $performanceSection = $show('performance') ? '<section><h2>3. ข้อมูลการดำเนินงาน</h2><div class="metrics">' . $metricCards . '</div>' . $paragraph(($report['performance']['operation_detail'] ?? '')) . '</section>' : '';
        $outcomeSection = $show('outcomes') ? '<section><h2>7. ผลที่ได้รับ</h2><h3>ผลการดำเนินงาน</h3>' . $paragraph($outcomes['performance_result'] ?? '') . '<h3>ผลลัพธ์และประโยชน์ที่ได้รับ</h3>' . $paragraph($outcomes['benefits'] ?? '') . '<h3>ผลต่อผู้เข้าร่วม/นักกีฬา</h3>' . $paragraph($outcomes['participant_impact'] ?? '') . '</section>' : '';
        $problemSection = $show('problems') ? '<section><h2>8. ปัญหาและข้อเสนอแนะ</h2><h3>ปัญหาและอุปสรรค</h3>' . $paragraph($problems['obstacles'] ?? '') . '<h3>วิธีการแก้ไข</h3>' . $paragraph($problems['solutions'] ?? '') . '<h3>ข้อเสนอแนะและแนวทางพัฒนา</h3>' . $paragraph($problems['recommendations'] ?? '') . '</section>' : '';

        return '<!doctype html>
<html lang="th"><head><meta charset="utf-8"><title>' . $e($report['title']) . '</title>
<style>
@font-face{font-family:Sarabun;src:url("../assets/sarabun-regular.ttf")}@font-face{font-family:Sarabun;src:url("../assets/sarabun-bold.ttf");font-weight:700}
@page{size:A4;margin:18mm 16mm 20mm}@page{@bottom-center{content:"หน้า " counter(page) " / " counter(pages);font-size:10pt;color:#667085}}
*{box-sizing:border-box}body{margin:0;background:#eef1f5;color:#202936;font:14px/1.7 Sarabun,"TH Sarabun New",sans-serif}
main{width:210mm;min-height:297mm;margin:20px auto;padding:18mm 16mm;background:#fff;box-shadow:0 12px 38px #1e293b1f}
header{text-align:center;border-bottom:3px double #344054;padding-bottom:15px;margin-bottom:18px}.logo{width:150px;max-height:62px;object-fit:contain}
h1{font-size:23px;line-height:1.45;margin:8px 0 2px}h2{font-size:17px;color:#174c45;margin:22px 0 8px;border-bottom:1px solid #cfd6df;padding-bottom:4px}
p{margin:0 0 9px;text-align:justify}.muted{color:#667085}.meta,.key-value{width:100%;border-collapse:collapse}.meta{margin-top:13px;text-align:left}.meta th{width:25%}
table{width:100%;border-collapse:collapse;font-size:12px;margin:8px 0 12px}th,td{border:1px solid #cfd6df;padding:6px 7px;vertical-align:top}th{background:#f3f6f8;font-weight:700}.center{text-align:center}.number{text-align:right}
.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin:12px 0}.metrics div{border:1px solid #cfd6df;padding:9px;text-align:center}.metrics strong{display:block;font-size:20px;color:#174c45}.metrics span{font-size:11px;color:#667085}
.signature{display:grid;grid-template-columns:1fr 1fr;gap:34px;margin-top:38px;text-align:center}.signature div{padding-top:26px}footer{margin-top:28px;border-top:1px solid #cfd6df;padding-top:8px;text-align:center;color:#667085;font-size:11px}
@media print{body{background:#fff}main{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}a{color:inherit;text-decoration:none}.no-print{display:none!important}}
</style></head><body><main>
<header><img class="logo" src="' . $e($logo) . '" alt="โลโก้การแข่งขัน"><div>SPORT TOURNAMENT</div><h1>' . $e($report['title']) . '</h1><div>' . $e($report['template_name']) . '</div>
<table class="meta"><tbody>
<tr><th>ปี/รุ่น</th><td>' . $e($report['academic_year'] ?: '-') . '</td><th>ปีงบประมาณ</th><td>' . $e($report['fiscal_year'] ?: '-') . '</td></tr>
<tr><th>ภาคการศึกษา</th><td>' . $e($report['semester'] ?: '-') . '</td><th>ช่วงวันที่รายงาน</th><td>' . $e(($report['start_date'] ?: '-') . ' ถึง ' . ($report['end_date'] ?: '-')) . '</td></tr>
<tr><th>หน่วยงาน</th><td>' . $e($report['department'] ?: '-') . '</td><th>สถานะ</th><td>' . $e($report['status_label']) . '</td></tr>
<tr><th>ผู้รับผิดชอบ</th><td>' . $e($report['responsible_person'] ?: '-') . '</td><th>ผู้จัดทำรายงาน</th><td>' . $e($report['prepared_by'] ?: '-') . '</td></tr>
</tbody></table></header>
<section><h2>สรุปสำหรับผู้บริหาร</h2>' . $paragraph($report['executive_summary'] ?? '') . '<div class="metrics">' . $metricCards . '</div></section>
<section><h2>1. ข้อมูลทั่วไป</h2><table class="key-value"><tbody>
<tr><th>ชื่อโครงการ/กิจกรรม</th><td>' . $e($general['project_name'] ?? '-') . '</td></tr>
<tr><th>ชื่อการแข่งขัน</th><td>' . $e($general['competition_name'] ?? $report['tournament_name'] ?? '-') . '</td></tr>
<tr><th>วันที่ดำเนินการ</th><td>' . $e($general['operation_date'] ?? '-') . '</td></tr>
<tr><th>สถานที่</th><td>' . $e($general['location'] ?? '-') . '</td></tr>
</tbody></table></section>
' . ($show('principles') ? '<section><h2>2. หลักการและวัตถุประสงค์</h2><h3>หลักการและเหตุผล</h3>' . $paragraph($general['rationale'] ?? '') . '<h3>วัตถุประสงค์</h3>' . $paragraph($general['objectives'] ?? '') . '<h3>กลุ่มเป้าหมาย</h3>' . $paragraph($general['target_group'] ?? '') . '</section>' : '') . '
' . $performanceSection . $competitionSection . $kpiSection . $budgetSection . $outcomeSection . $problemSection . $attachmentSection . '
<div class="signature"><div>ลงชื่อ ................................................<br>(' . $e($report['prepared_by'] ?: 'ผู้จัดทำรายงาน') . ')<br>ผู้จัดทำรายงาน</div><div>ลงชื่อ ................................................<br>(................................................)<br>ผู้ตรวจสอบ/ผู้อนุมัติ</div></div>
<footer>จัดทำเมื่อ ' . $e($report['report_date'] ?: $report['created_at']) . ' | ข้อมูลการแข่งขันอ้างอิงจากระบบ SPORT TOURNAMENT</footer>
</main></body></html>';
    }

    private static function formalReportHtml(array $result): string
    {
        $e = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $list = static function (array $items) use ($e): string {
            if (!$items) {
                return '<p class="muted">ไม่พบข้อมูลในส่วนนี้</p>';
            }
            return '<ol>' . implode('', array_map(fn($item): string => '<li>' . $e($item) . '</li>', $items)) . '</ol>';
        };
        $metrics = [
            'sports' => 'จำนวนกีฬา',
            'activeSports' => 'กีฬาเปิดใช้งาน',
            'teams' => 'จำนวนทีม',
            'approvedTeams' => 'ทีมที่อนุมัติ',
            'students' => 'จำนวนนักกีฬา',
            'tournaments' => 'จำนวนรายการแข่งขัน',
            'matches' => 'จำนวนแมตช์',
            'completedMatches' => 'แมตช์ที่บันทึกผลแล้ว',
        ];
        $metricRows = '';
        foreach ($metrics as $key => $label) {
            if (array_key_exists($key, $result['metrics'] ?? [])) {
                $metricRows .= '<tr><th>' . $e($label) . '</th><td>' . $e($result['metrics'][$key]) . '</td></tr>';
            }
        }

        return '<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>' . $e($result['title']) . '</title>
  <style>
    @page { size: A4; margin: 18mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #17212b; font-family: "Sarabun", "TH Sarabun New", Tahoma, sans-serif; line-height: 1.75; background: #f3f5f7; }
    main { width: 210mm; min-height: 297mm; margin: 24px auto; padding: 24mm 22mm; background: #fff; box-shadow: 0 16px 48px rgba(15, 23, 42, .12); }
    header { text-align: center; border-bottom: 3px double #2f3b52; padding-bottom: 18px; margin-bottom: 24px; }
    .kicker { color: #526070; font-size: 14px; letter-spacing: .04em; }
    h1 { margin: 8px 0 4px; font-size: 28px; line-height: 1.35; }
    h2 { margin: 24px 0 8px; font-size: 20px; color: #0f5f57; border-bottom: 1px solid #d7dde5; padding-bottom: 5px; }
    p { margin: 0 0 12px; text-align: justify; }
    .meta { display: grid; grid-template-columns: 42mm 1fr; gap: 5px 12px; margin-top: 16px; text-align: left; font-size: 15px; }
    .meta strong { color: #2f3b52; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 15px; }
    th, td { border: 1px solid #d7dde5; padding: 8px 10px; vertical-align: top; }
    th { width: 45%; background: #f5f8fa; text-align: left; color: #263548; }
    ol { margin: 8px 0 0 24px; padding: 0; }
    li { margin-bottom: 7px; }
    .signature { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 42px; }
    .sig-box { text-align: center; padding-top: 32px; }
    .muted { color: #697386; }
    footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #d7dde5; color: #697386; font-size: 13px; text-align: center; }
    @media print { body { background: #fff; } main { margin: 0; width: auto; min-height: auto; box-shadow: none; padding: 0; } }
  </style>
</head>
<body>
<main>
  <header>
    <div class="kicker">SPORT TOURNAMENT</div>
    <h1>' . $e($result['report_type_label'] ?? 'รายงานระบบกีฬา') . '</h1>
    <div class="muted">' . $e($result['title']) . '</div>
    <div class="meta">
      <strong>วันที่จัดทำรายงาน</strong><span>' . $e($result['created_at_label'] ?? '') . '</span>
      <strong>ผู้จัดทำรายงาน</strong><span>' . $e($result['generated_by'] ?? '-') . '</span>
      <strong>แหล่งข้อมูล</strong><span>ระบบบริหารจัดการแข่งขันกีฬา SPORT TOURNAMENT</span>
      <strong>เครื่องมือประมวลผล</strong><span>' . $e(($result['provider'] ?? '') . ' / ' . ($result['model'] ?? '')) . '</span>
    </div>
  </header>

  <h2>1. สรุปผู้บริหาร</h2>
  <p>' . nl2br($e($result['executive_summary'] ?? '')) . '</p>

  <h2>2. ตัวชี้วัดจากระบบ</h2>
  <table><tbody>' . $metricRows . '</tbody></table>

  <h2>3. ประเด็นที่ควรดำเนินการต่อ</h2>
  ' . $list($result['analysis'] ?? []) . '

  <h2>4. ความเสี่ยงและข้อควรพิจารณา</h2>
  ' . $list($result['risks'] ?? []) . '

  <h2>5. ข้อเสนอแนะ</h2>
  ' . $list($result['recommendations'] ?? []) . '

  <div class="signature">
    <div class="sig-box">ลงชื่อ .................................................<br>ผู้จัดทำรายงาน</div>
    <div class="sig-box">ลงชื่อ .................................................<br>ผู้ตรวจสอบ/ผู้อนุมัติ</div>
  </div>
  <footer>รายงานฉบับนี้จัดทำจากข้อมูลที่บันทึกในระบบ ณ เวลาที่ระบุข้างต้น</footer>
</main>
</body>
</html>';
    }

    private static function reportTypeLabel(string $type): string
    {
        return [
            'executive' => 'รายงานสรุปผลการดำเนินงานสำหรับผู้บริหาร',
            'competition' => 'รายงานผลการแข่งขันกีฬา',
            'registration' => 'รายงานทะเบียนนักกีฬา',
            'data_quality' => 'รายงานคุณภาพข้อมูลระบบกีฬา',
        ][$type] ?? 'รายงานระบบกีฬา';
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

    private static function validateSportPayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $type = (string) ($payload['type'] ?? 'team');
        $gender = (string) ($payload['gender'] ?? 'open');
        $min = (int) ($payload['min_players'] ?? 1);
        $max = (int) ($payload['max_players'] ?? 1);
        $reserve = (int) ($payload['reserve_players'] ?? 0);
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('ชื่อกีฬาต้องมี 1-150 ตัวอักษร');
        }
        if (!in_array($type, ['individual', 'team'], true)) {
            throw new InvalidArgumentException('ประเภทกีฬาไม่ถูกต้อง');
        }
        if (!in_array($gender, ['male', 'female', 'mixed', 'open'], true)) {
            throw new InvalidArgumentException('ประเภทเพศไม่ถูกต้อง');
        }
        if ($min < 1 || $max < $min || $reserve < 0) {
            throw new InvalidArgumentException('จำนวนผู้เล่นไม่ถูกต้อง');
        }
        return [
            'name' => $name,
            'type' => $type,
            'gender' => $gender,
            'min_players' => $min,
            'max_players' => $max,
            'reserve_players' => $reserve,
            'rules' => (string) ($payload['rules'] ?? ''),
            'icon' => self::sportIconKey((string) ($payload['icon'] ?? 'general')),
            'logo_url' => (string) ($payload['logo_url'] ?? ''),
        ];
    }

    private static function sportIconKey(string $icon): string
    {
        $icon = strtolower(trim($icon));
        $aliases = ['futsal' => 'football', 'athletics' => 'running', 'jogging' => 'running', 'pingpong' => 'table-tennis', 'table_tennis' => 'table-tennis'];
        $icon = $aliases[$icon] ?? $icon;
        return in_array($icon, ['football', 'volleyball', 'basketball', 'badminton', 'table-tennis', 'tennis', 'running', 'swimming', 'esports', 'general'], true) ? $icon : 'general';
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

    private static function validateStudentPayload(array $payload): array
    {
        $studentCode = trim((string) ($payload['student_code'] ?? ''));
        $departmentCode = trim((string) ($payload['department_code'] ?? '')) ?: 'OPEN';
        if (!self::activeDepartmentExists($departmentCode)) {
            throw new InvalidArgumentException('ไม่พบหน่วยงาน');
        }
        if (trim((string) ($payload['first_name'] ?? '')) === '') {
            throw new InvalidArgumentException('กรุณากรอกชื่อนักกีฬา');
        }
        return [
            'student_code' => $studentCode,
            'prefix' => (string) ($payload['prefix'] ?? ''),
            'first_name' => trim((string) ($payload['first_name'] ?? '')),
            'last_name' => trim((string) ($payload['last_name'] ?? '')),
            'department_code' => $departmentCode,
            'faculty_code' => (string) ($payload['faculty_code'] ?? ''),
            'entry_year' => (string) ($payload['entry_year'] ?? ''),
            'gender' => (string) ($payload['gender'] ?? 'open'),
            'status' => (string) ($payload['status'] ?? 'active'),
            'is_eligible' => self::boolToInt($payload['is_eligible'] ?? true),
            'card_raw_data' => (string) ($payload['card_raw_data'] ?? ''),
            'phone' => (string) ($payload['phone'] ?? ''),
            'photo_url' => trim((string) ($payload['photo_url'] ?? '')),
        ];
    }

    private static function validateTeamPayload(array $payload, bool $updating = false): array
    {
        $tournamentId = trim((string) ($payload['tournament_id'] ?? ''));
        $departmentCode = trim((string) ($payload['department_code'] ?? '')) ?: 'OPEN';
        $name = trim((string) ($payload['name'] ?? ''));
        $tournament = self::fetchOne('SELECT id, sport_id FROM tournaments WHERE id = :id', [':id' => $tournamentId]);
        if (!$tournament) {
            throw new RuntimeException('ไม่พบรายการแข่งขัน');
        }
        if (!self::activeDepartmentExists($departmentCode)) {
            throw new RuntimeException('ไม่พบหน่วยงานที่เลือก');
        }
        if ($name === '') {
            throw new InvalidArgumentException('กรุณากรอกชื่อทีม');
        }
        $sportId = trim((string) ($payload['sport_id'] ?? $tournament['sport_id']));
        if (!self::fetchOne('SELECT sport_id FROM tournament_sports WHERE tournament_id = :tournament_id AND sport_id = :sport_id', [':tournament_id' => $tournamentId, ':sport_id' => $sportId])) {
            throw new RuntimeException('กีฬาของทีมต้องอยู่ในรายการแข่งขันที่เลือก');
        }
        $configuredDepartments = self::countRows(
            'tournament_departments',
            'tournament_id = :tournament_id AND sport_id = :sport_id',
            [':tournament_id' => $tournamentId, ':sport_id' => $sportId]
        );
        if ($departmentCode !== 'OPEN' && $configuredDepartments > 0 && !self::fetchOne(
            'SELECT id FROM tournament_departments
             WHERE tournament_id = :tournament_id AND sport_id = :sport_id AND department_code = :department_code',
            [':tournament_id' => $tournamentId, ':sport_id' => $sportId, ':department_code' => $departmentCode]
        )) {
            throw new RuntimeException('สาขานี้ไม่ได้ถูกกำหนดให้เข้าร่วมกีฬาที่เลือก');
        }
        $record = [
            'tournament_id' => $tournamentId,
            'department_code' => $departmentCode,
            'name' => $name,
            'sport_id' => $sportId,
            'logo_url' => trim((string) ($payload['logo_url'] ?? '')),
            'captain_student_id' => self::nullableId($payload['captain_student_id'] ?? null),
        ];
        if ($updating) {
            $status = (string) ($payload['status'] ?? 'submitted');
            if (!in_array($status, ['draft', 'submitted', 'approved', 'rejected', 'locked', 'withdrawn'], true)) {
                throw new InvalidArgumentException('สถานะทีมไม่ถูกต้อง');
            }
            $record['status'] = $status;
        }
        return $record;
    }

    private static function validateMatchTeams(array $payload): void
    {
        $tournamentId = trim((string) ($payload['tournament_id'] ?? ''));
        if (!self::fetchOne('SELECT id FROM tournaments WHERE id = :id', [':id' => $tournamentId])) {
            throw new RuntimeException('ไม่พบรายการแข่งขัน');
        }
        $team1 = self::nullableId($payload['team1_id'] ?? null);
        $team2 = self::nullableId($payload['team2_id'] ?? null);
        $sportId = trim((string) ($payload['sport_id'] ?? ''));
        if ($team1 !== null && $team2 !== null && (string) $team1 === (string) $team2) {
            throw new RuntimeException('ทีม 1 และทีม 2 ต้องไม่ใช่ทีมเดียวกัน');
        }
        foreach ([$team1, $team2] as $teamId) {
            if ($teamId === null) {
                continue;
            }
            $team = self::fetchOne('SELECT id, tournament_id, sport_id FROM teams WHERE id = :id', [':id' => $teamId]);
            if (!$team || (string) $team['tournament_id'] !== (string) $tournamentId) {
                throw new RuntimeException('ทีมในแมตช์ต้องอยู่ในทัวร์นาเมนท์เดียวกัน');
            }
            if ($sportId !== '' && (string) $team['sport_id'] !== $sportId) {
                throw new RuntimeException('ทีมในแมตช์ต้องอยู่ในกีฬาที่เลือก');
            }
        }
        if ($team1 !== null && $team2 !== null) {
            $first = self::findById('teams', (string) $team1);
            $second = self::findById('teams', (string) $team2);
            if ((string) $first['sport_id'] !== (string) $second['sport_id']) {
                throw new RuntimeException('ทั้งสองทีมต้องแข่งขันในชนิดกีฬาเดียวกัน');
            }
        }
    }

    private static function validateNewsPayload(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $type = (string) ($payload['news_type'] ?? 'general');
        $status = (string) ($payload['status'] ?? 'draft');
        $departmentCode = trim((string) ($payload['department_code'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('กรุณากรอกหัวข้อข่าว');
        }
        if (!in_array($type, ['general', 'department', 'hall_of_fame'], true)) {
            throw new InvalidArgumentException('ประเภทข่าวไม่ถูกต้อง');
        }
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new InvalidArgumentException('สถานะข่าวไม่ถูกต้อง');
        }
        if ($departmentCode !== '' && !self::activeDepartmentExists($departmentCode)) {
            throw new RuntimeException('ไม่พบหน่วย/สาขาที่เลือก');
        }
        $sourceUrl = trim((string) ($payload['source_url'] ?? ''));
        if ($sourceUrl !== '' && (!filter_var($sourceUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($sourceUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            throw new InvalidArgumentException('ลิงก์เว็บไซต์ต้องเป็น URL ที่ขึ้นต้นด้วย http:// หรือ https://');
        }
        return [
            'title' => $title,
            'slug' => self::slugify((string) ($payload['slug'] ?? $title)) ?: self::slugify($title),
            'excerpt' => (string) ($payload['excerpt'] ?? ''),
            'body' => (string) ($payload['body'] ?? ''),
            'cover_image_url' => (string) ($payload['cover_image_url'] ?? ''),
            'source_url' => $sourceUrl,
            'news_type' => $type,
            'department_code' => $departmentCode !== '' ? $departmentCode : null,
            'status' => $status,
            'is_pinned' => self::boolToInt($payload['is_pinned'] ?? false),
            'published_at' => $status === 'published' ? ((string) ($payload['published_at'] ?? '') ?: Database::now()) : '',
            'author_email' => Auth::currentUser()['email'] ?? '',
        ];
    }

    private static function validateUserPayload(array $payload, bool $isNew): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $role = (string) ($payload['role'] ?? Auth::ROLE_REGISTRATION_OFFICER);
        $departmentCode = trim((string) ($payload['department_code'] ?? ''));
        $allowedRoles = array_column(Auth::roleOptions(), 'value');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('กรุณากรอกอีเมลให้ถูกต้อง');
        }
        if (!in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException('บทบาทผู้ใช้ไม่ถูกต้อง');
        }
        if ($departmentCode !== '' && !self::activeDepartmentExists($departmentCode)) {
            throw new RuntimeException('ไม่พบหน่วย/สาขาที่เลือก');
        }
        if ($isNew && empty($payload['password'])) {
            throw new InvalidArgumentException('กรุณากำหนดรหัสผ่านเริ่มต้น');
        }
        $record = [
            'email' => $email,
            'name' => trim((string) ($payload['name'] ?? $email)) ?: $email,
            'role' => $role,
            'department_code' => $departmentCode !== '' ? $departmentCode : null,
            'sport_ids' => self::normalizeCsvIds((string) ($payload['sport_ids'] ?? '')),
            'is_active' => self::boolToInt($payload['is_active'] ?? true),
        ];
        $password = (string) ($payload['password'] ?? '');
        if ($password !== '') {
            $confirm = (string) ($payload['password_confirm'] ?? $password);
            if ($password !== $confirm) {
                throw new InvalidArgumentException('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
            }
            Auth::validatePasswordStrength($password);
            $record['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $record['password_salt'] = null;
            $record['password_updated_at'] = Database::now();
        }
        return $record;
    }

    private static function validateInvitePayload(array $payload): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $role = (string) ($payload['role'] ?? Auth::ROLE_REGISTRATION_OFFICER);
        $departmentCode = trim((string) ($payload['department_code'] ?? ''));
        $allowedRoles = array_column(Auth::roleOptions(), 'value');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('กรุณากรอกอีเมลให้ถูกต้อง');
        }
        if (!in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException('บทบาทผู้ใช้ไม่ถูกต้อง');
        }
        if ($departmentCode !== '' && !self::activeDepartmentExists($departmentCode)) {
            throw new RuntimeException('ไม่พบหน่วย/สาขาที่เลือก');
        }
        return [
            'email' => $email,
            'name' => trim((string) ($payload['name'] ?? '')),
            'role' => $role,
            'department_code' => $departmentCode !== '' ? $departmentCode : null,
            'sport_ids' => self::normalizeCsvIds((string) ($payload['sport_ids'] ?? '')),
        ];
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

    private static function activeSportRule(string $sportId): array
    {
        $rule = self::fetchOne(
            'SELECT * FROM sport_rule_versions WHERE sport_id = :sport_id AND is_active = 1 ORDER BY version_number DESC LIMIT 1',
            [':sport_id' => $sportId]
        );
        if (!$rule) {
            throw new RuntimeException('กีฬานี้ยังไม่มีกติกาที่เปิดใช้งาน');
        }
        return $rule;
    }

    private static function matchSportId(array $payload): string
    {
        $teamIds = [self::nullableId($payload['team1_id'] ?? null), self::nullableId($payload['team2_id'] ?? null)];
        foreach ($teamIds as $teamId) {
            if ($teamId !== null) {
                $team = self::findById('teams', (string) $teamId);
                if (!empty($team['sport_id'])) {
                    return (string) $team['sport_id'];
                }
            }
        }
        if (!empty($payload['sport_id'])) {
            return (string) $payload['sport_id'];
        }
        $tournament = self::findById('tournaments', (string) ($payload['tournament_id'] ?? ''));
        return (string) $tournament['sport_id'];
    }

    private static function ensureMatchPeriods(string $matchId, array $rule): void
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

    private static function syncMatchScoreFromEvents(string $matchId): void
    {
        $match = self::findById('matches', $matchId);
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
            $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
            $patch['winner_team_id'] = self::determineMatchWinner($match, $score1, $score2, $rule, self::nullableId($match['winner_team_id'] ?? null));
        }
        self::updateRow('matches', $matchId, $patch);
        if ((string) $match['status'] === 'completed') {
            self::recalculateStandings((string) $match['tournament_id']);
        }
    }

    private static function recalculateStandings(string $tournamentId): void
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
        foreach (self::fetchAll("SELECT * FROM matches WHERE tournament_id = :tournament_id AND status = 'completed'", [':tournament_id' => $tournamentId]) as $match) {
            $team1 = (string) ($match['team1_id'] ?? '');
            $team2 = (string) ($match['team2_id'] ?? '');
            if (!isset($rows[$team1], $rows[$team2])) {
                continue;
            }
            $ruleId = (string) ($match['rule_version_id'] ?? '');
            if (!isset($rules[$ruleId])) {
                $decoded = $ruleId !== '' ? self::decodeSportRule(self::findById('sport_rule_versions', $ruleId)) : [];
                $rules[$ruleId] = [
                    'win' => (int) ($decoded['win_points'] ?? 3),
                    'draw' => (int) ($decoded['draw_points'] ?? 1),
                    'loss' => (int) ($decoded['loss_points'] ?? 0),
                ];
            }
            $rule = $rules[$ruleId];
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

    private static function joinedTeams(): array
    {
        $sportNames = self::groupConcat('s2.name', ' · ');
        return self::fetchAll(
            "SELECT teams.*, tournaments.name AS tournament_name, tournaments.status AS tournament_status,
                    COALESCE(teams.sport_id, tournaments.sport_id) AS sport_id,
                    sports.name AS sport_name,
                    sports.icon AS sport_icon, sports.logo_url AS sport_logo_url, sports.type AS sport_type,
                    sports.gender AS sport_gender, sports.max_players AS sport_max_players,
                    departments.name AS department_name, faculties.name AS faculty_name,
                    universities.name AS university_name
             FROM teams
             LEFT JOIN tournaments ON tournaments.id = teams.tournament_id
             LEFT JOIN sports ON sports.id = COALESCE(teams.sport_id, tournaments.sport_id)
             LEFT JOIN departments ON departments.code = teams.department_code
             LEFT JOIN faculties ON faculties.id = departments.faculty_id
             LEFT JOIN universities ON universities.id = faculties.university_id
             ORDER BY teams.id DESC"
        );
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
            "SELECT matches.*,
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
                    d2.name AS team2_department_name
             FROM matches
             LEFT JOIN tournaments ON tournaments.id = matches.tournament_id
             LEFT JOIN sports ON sports.id = COALESCE(matches.sport_id, tournaments.sport_id)
             LEFT JOIN teams team1 ON team1.id = matches.team1_id
             LEFT JOIN teams team2 ON team2.id = matches.team2_id
             LEFT JOIN departments d1 ON d1.code = team1.department_code
             LEFT JOIN departments d2 ON d2.code = team2.department_code
             ORDER BY matches.id DESC"
        );
        foreach ($rows as &$row) {
            $row['match_code'] = 'T' . ($row['tournament_id'] ?: '-') . '-M' . ($row['match_number'] ?: $row['id'] ?: '-');
            $row['team1_name'] = $row['team1_name_raw'] ?: ($row['team1_department_name'] ?: ($row['team1_id'] ?: 'ทีม 1'));
            $row['team2_name'] = $row['team2_name_raw'] ?: ($row['team2_department_name'] ?: ($row['team2_id'] ?: 'ทีม 2'));
            $row['score_label'] = (string) $row['status'] === 'completed'
                ? (string) ($row['team1_score'] ?? 0) . ' - ' . (string) ($row['team2_score'] ?? 0)
                : 'ยังไม่บันทึกผล';
            $row['status_label'] = self::matchStatusLabel((string) ($row['status'] ?? 'scheduled'));
            $row['display_label'] = $row['match_code'] . ' | ' . ($row['sport_name'] ?: 'ไม่ระบุกีฬา') . ' | ' . ($row['tournament_name'] ?: $row['tournament_id']) . ' | ' . $row['team1_name'] . ' พบ ' . $row['team2_name'] . ' | ' . $row['score_label'];
            unset($row['team1_name_raw'], $row['team2_name_raw']);
        }
        unset($row);
        return $rows;
    }

    private static function enrichedNewsRows(array $rows): array
    {
        $links = [];
        foreach (self::fetchAll('SELECT * FROM news_links') as $link) {
            $links[(string) $link['news_id']][(string) $link['entity_type']] = $link['entity_id'];
        }
        $departments = [];
        foreach (self::fetchAll('SELECT code, name FROM departments') as $department) {
            $departments[(string) $department['code']] = $department['name'];
        }
        foreach ($rows as &$row) {
            $row['news_type_label'] = self::newsTypeLabel((string) ($row['news_type'] ?? 'general'));
            $row['department_name'] = $departments[(string) ($row['department_code'] ?? '')] ?? '';
            foreach (['sport', 'tournament', 'team', 'match'] as $type) {
                $row[$type . '_id'] = $links[(string) $row['id']][$type] ?? null;
            }
        }
        unset($row);
        return $rows;
    }

    private static function replaceNewsLinks(string $newsId, array $payload): void
    {
        self::deleteRowsWhere('news_links', 'news_id = :news_id', [':news_id' => $newsId]);
        foreach (['sport', 'tournament', 'team', 'match'] as $type) {
            $entityId = self::nullableId($payload[$type . '_id'] ?? null);
            if ($entityId === null) {
                continue;
            }
            $stmt = Database::pdo()->prepare('INSERT INTO news_links (news_id, entity_type, entity_id) VALUES (:news_id, :entity_type, :entity_id)');
            $stmt->execute([':news_id' => $newsId, ':entity_type' => $type, ':entity_id' => $entityId]);
        }
    }

    private static function enrichUsers(array $rows): array
    {
        $roles = array_column(Auth::roleOptions(), 'label', 'value');
        $departments = array_column(self::fetchAll('SELECT code, name FROM departments'), 'name', 'code');
        $sports = array_column(self::fetchAll('SELECT id, name FROM sports'), 'name', 'id');
        foreach ($rows as &$row) {
            $sportIds = array_filter(explode(',', (string) ($row['sport_ids'] ?? '')));
            $row['role_label'] = $roles[$row['role']] ?? $row['role'];
            $row['department_name'] = $departments[(string) ($row['department_code'] ?? '')] ?? '';
            $row['sport_names'] = implode(', ', array_map(fn(string $id): string => $sports[$id] ?? $id, $sportIds));
            $row['password_status'] = !empty($row['password_hash']) ? 'ตั้งรหัสแล้ว' : 'ยังไม่ตั้งรหัส';
            $row['active_label'] = Auth::truthy($row['is_active']) ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
            $row = Auth::sanitize($row);
        }
        unset($row);
        return $rows;
    }

    private static function enrichInvites(array $rows): array
    {
        $departments = array_column(self::fetchAll('SELECT code, name FROM departments'), 'name', 'code');
        foreach ($rows as &$row) {
            unset($row['token_hash']);
            $row['role_label'] = self::roleLabel((string) ($row['role'] ?? ''));
            $row['department_name'] = $departments[(string) ($row['department_code'] ?? '')] ?? '';
            $row['status_label'] = self::inviteStatusLabel((string) ($row['status'] ?? 'pending'));
            $row['expires_in_minutes'] = self::minutesUntil((string) ($row['expires_at'] ?? ''));
        }
        unset($row);
        return $rows;
    }

    private static function roleLabel(string $role): string
    {
        $roles = array_column(Auth::roleOptions(), 'label', 'value');
        return $roles[$role] ?? $role;
    }

    private static function inviteStatusLabel(string $status): string
    {
        return [
            'pending' => 'รอตอบรับ',
            'accepted' => 'ตอบรับแล้ว',
            'expired' => 'หมดอายุ',
            'revoked' => 'ยกเลิกแล้ว',
        ][$status] ?? $status;
    }

    private static function inviteTokenHash(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('ไม่พบ token คำเชิญ');
        }
        return hash('sha256', $token);
    }

    private static function inviteUrl(string $token): string
    {
        $query = '?invite=' . rawurlencode($token);
        $configuredBase = rtrim((string) getenv('PRSPORT_APP_URL'), '/');
        if ($configuredBase !== '') {
            return $configuredBase . '/public/' . $query;
        }

        $path = app_url('public/');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return $path . $query;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host . $path . $query;
    }

    private static function activeInviteByToken(string $token): array
    {
        $invite = self::fetchOne(
            'SELECT * FROM user_invites WHERE token_hash = :hash LIMIT 1',
            [':hash' => self::inviteTokenHash($token)]
        );
        if (!$invite || (string) ($invite['status'] ?? '') !== 'pending') {
            throw new RuntimeException('คำเชิญนี้ไม่พร้อมใช้งานหรือถูกใช้ไปแล้ว');
        }
        if ((string) ($invite['expires_at'] ?? '') <= Database::now()) {
            self::updateRow('user_invites', (string) $invite['id'], ['status' => 'expired']);
            throw new RuntimeException('คำเชิญนี้หมดอายุแล้ว');
        }
        return $invite;
    }

    private static function expireInvites(): void
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE user_invites SET status = 'expired' WHERE status = 'pending' AND expires_at <= :now"
        );
        $stmt->execute([':now' => Database::now()]);
    }

    private static function minutesUntil(string $dateTime): int
    {
        if ($dateTime === '') {
            return 0;
        }
        $seconds = strtotime($dateTime) - time();
        return max(0, (int) ceil($seconds / 60));
    }

    private static function sanitizeUserAudit(array $row): array
    {
        unset($row['password_hash'], $row['password_salt']);
        return $row;
    }

    private static function activeDepartments(): array
    {
        return self::fetchAll(
            'SELECT departments.*, faculties.name AS faculty_name, universities.name AS university_name
             FROM departments
             LEFT JOIN faculties ON faculties.id = departments.faculty_id
             LEFT JOIN universities ON universities.id = faculties.university_id
             WHERE departments.is_active = 1
             ORDER BY faculties.name ASC, departments.name ASC'
        );
    }

    private static function activeDepartmentExists(string $code): bool
    {
        return (bool) self::fetchOne('SELECT code FROM departments WHERE code = :code AND is_active = 1', [':code' => $code]);
    }

    private static function findStudentByCodeOrId(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return self::fetchOne('SELECT * FROM students WHERE student_code = :value OR id = :value LIMIT 1', [':value' => $value]) ?: null;
    }

    private static function nextMatchNumber(string $tournamentId): int
    {
        $matchNumber = self::numericCast('match_number');
        $row = self::fetchOne("SELECT MAX({$matchNumber}) AS max_number FROM matches WHERE tournament_id = :id", [':id' => $tournamentId]);
        return ((int) ($row['max_number'] ?? 0)) + 1;
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

    private static function newsTypeLabel(string $type): string
    {
        return [
            'general' => 'ข่าวสารทั่วไป',
            'department' => 'เนื้อหาหน่วย',
            'hall_of_fame' => 'Hall of Fame',
        ][$type] ?? 'ข่าวสารทั่วไป';
    }

    private static function sortPinnedNews(array $a, array $b): int
    {
        $pinned = self::boolToInt($b['is_pinned'] ?? false) <=> self::boolToInt($a['is_pinned'] ?? false);
        if ($pinned !== 0) {
            return $pinned;
        }
        return strcmp((string) ($b['published_at'] ?: $b['created_at']), (string) ($a['published_at'] ?: $a['created_at']));
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

    private static function normalizeCsvIds(string $value): string
    {
        $items = [];
        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ($item !== '' && !in_array($item, $items, true)) {
                $items[] = $item;
            }
        }
        return implode(',', $items);
    }

    private static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', '-', $value) ?? '';
        $value = preg_replace('/[^\p{Thai}a-z0-9-]/u', '', $value) ?? '';
        $value = preg_replace('/-+/u', '-', $value) ?? '';
        return trim($value, '-');
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

    private static function boolToInt($value): int
    {
        return Auth::truthy($value) ? 1 : 0;
    }

    private static function legacyArg(array $payload, int $index = 0, $default = []): array|string|int|bool|null
    {
        if (isset($payload['args']) && is_array($payload['args'])) {
            return $payload['args'][$index] ?? $default;
        }
        return $index === 0 ? $payload : $default;
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

    private static function deleteRow(string $table, string $id): array
    {
        $row = self::findById($table, $id);
        $stmt = Database::pdo()->prepare("DELETE FROM {$table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $row;
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

    private static function countRows(string $table, string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$table}" . ($where ? ' WHERE ' . $where : '');
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
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

    private static function numericCast(string $expression): string
    {
        return Database::isMysql() ? "CAST({$expression} AS SIGNED)" : "CAST({$expression} AS INTEGER)";
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
