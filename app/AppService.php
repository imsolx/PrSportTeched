<?php
declare(strict_types=1);

require_once __DIR__ . '/Modules/TournamentModule.php';

final class AppService
{
    use TournamentModule;

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
                return self::listMatches((string) ($payload['tournament_id']
                    ?? (isset($payload['args']) ? self::legacyArg($payload, 0, '') : '')));
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
            case 'matches.resultDetail':
                return self::matchResultDetail((string) ($payload['match_id'] ?? ''));
            case 'matches.center':
                return self::matchCenter((string) ($payload['match_id'] ?? ''));
            case 'matches.clock':
                return self::updateMatchClock((string) ($payload['match_id'] ?? ''), $payload);
            case 'matches.event':
                return self::saveMatchEvent($payload);
            case 'matches.eventUpdate':
                return self::updateMatchEvent((string) ($payload['id'] ?? ''), $payload);
            case 'matches.recordingDraft':
                return self::saveMatchRecordingDraft($payload);
            case 'matches.scoreCorrection':
                return self::correctMatchTeamScore($payload);
            case 'matches.eventVoid':
                return self::voidMatchEvent((string) ($payload['id'] ?? ''), $payload);
            case 'matches.resultDelete':
                return self::deleteMatchResult((string) ($payload['match_id'] ?? ''), $payload);
            case 'matches.officialOptions':
                return self::matchOfficialOptions();
            case 'matches.assignments':
                Auth::canManageMasterData();
                return self::fetchAll('SELECT * FROM match_official_assignments WHERE match_id = :id ORDER BY user_id', [':id' => (string) ($payload['match_id'] ?? '')]);
            case 'matches.assignOfficials':
                return self::assignMatchOfficials((string) ($payload['match_id'] ?? ''), (array) ($payload['assignments'] ?? []));
            case 'matches.entries':
                return self::matchEntries((string) ($payload['match_id'] ?? ''));
            case 'matches.entryCandidates':
                return self::matchEntryCandidates((string) ($payload['match_id'] ?? ''));
            case 'matches.entrySave':
                return self::saveMatchEntry($payload);
            case 'matches.entryImport':
                return self::importMatchEntries($payload);
            case 'matches.entryResult':
                return self::recordMatchEntryResult($payload);
            case 'matches.entriesConfirm':
                return self::confirmMatchEntries((string) ($payload['match_id'] ?? ''));
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
            case 'users.status':
                return self::setUserActiveStatus($payload);
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
            'staff_invite_expiry_minutes' => '45',
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
            if ((empty($row['team1_id']) || empty($row['team2_id']))
                && !self::fetchOne('SELECT id FROM match_entries WHERE match_id = :id LIMIT 1', [':id' => $row['id']])) return false;
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
        foreach ($rows as &$row) {
            if (empty($row['team1_id']) && empty($row['team2_id'])
                || self::fetchOne('SELECT id FROM match_entries WHERE match_id = :id LIMIT 1', [':id' => $row['id']])) {
                $row['race_entries'] = self::fetchAll('SELECT match_entries.display_name, match_entries.department_code,
                    departments.name AS department_name, match_entries.result_status, match_entries.verified_time_ms,
                    match_entries.rank_number, match_entries.points
                    FROM match_entries LEFT JOIN departments ON departments.code = match_entries.department_code
                    WHERE match_entries.match_id = :id ORDER BY COALESCE(rank_number, 999), lane_number, match_entries.id', [':id' => $row['id']]);
                $row['score_label'] = count($row['race_entries']) . ' ผู้เข้าแข่งขัน';
            }
        }
        unset($row);
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
        Auth::requireRole([Auth::ROLE_SUPER_ADMIN, Auth::ROLE_ADMIN, Auth::ROLE_SPORT_HEAD, Auth::ROLE_PUBLIC_RELATIONS]);
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
        $scoringType = strtoupper(trim((string) ($payload['scoring_type'] ?? 'SCORE')));
        if (!in_array($scoringType, ScoringEngine::TYPES, true)) {
            throw new InvalidArgumentException('ประเภทการคิดคะแนนไม่ถูกต้อง');
        }
        $configText = trim((string) ($payload['scoring_config'] ?? ''));
        $scoringConfig = $configText === '' ? [] : json_decode($configText, true);
        if (!is_array($scoringConfig)) {
            throw new InvalidArgumentException('Scoring Config ต้องเป็น JSON Object ที่ถูกต้อง');
        }
        $scoringConfig = array_replace(ScoringTypeDetector::defaultConfig($scoringType), $scoringConfig, self::scoringConfigFromPayload($scoringType, $payload));
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
            'scoring_type' => $scoringType,
            'scoring_config' => json_encode($scoringConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
        return self::fetchAll('SELECT sports.*,
            (SELECT scoring_type FROM sport_rule_versions
             WHERE sport_id = sports.id AND is_active = 1
             ORDER BY version_number DESC LIMIT 1) AS scoring_type
            FROM sports ORDER BY sports.id DESC');
    }

    public static function createSport(array $payload): array
    {
        Auth::canManageMasterData();
        $record = self::validateSportPayload($payload);
        $scoringType = strtoupper(trim((string) ($payload['scoring_type'] ?? '')));
        if (!in_array($scoringType, ScoringTypeDetector::SELECTABLE_TYPES, true)) {
            throw new InvalidArgumentException('กรุณาเลือกหนึ่งใน 12 รูปแบบการบันทึกผล');
        }
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
            'scoring_type' => $scoringType,
            'scoring_config' => json_encode(ScoringTypeDetector::defaultConfig($scoringType), JSON_UNESCAPED_UNICODE),
            'custom_rules' => $row['rules'],
            'is_active' => true,
        ]);
        self::audit('create', 'sport', (string) $row['id'], $row);
        return array_replace($row, ['scoring_type' => $scoringType]);
    }

    public static function updateSport(string $id, array $payload): array
    {
        Auth::canManageMasterData();
        $record = self::validateSportPayload($payload);
        $activeRule = self::fetchOne(
            'SELECT * FROM sport_rule_versions WHERE sport_id = :sport_id AND is_active = 1 ORDER BY version_number DESC LIMIT 1',
            [':sport_id' => $id]
        );
        $currentScoringType = strtoupper((string) ($activeRule['scoring_type'] ?? ''));
        $scoringType = strtoupper(trim((string) ($payload['scoring_type'] ?? $currentScoringType)));
        if (!in_array($scoringType, ScoringTypeDetector::SELECTABLE_TYPES, true)) {
            throw new InvalidArgumentException('กรุณาเลือกหนึ่งใน 12 รูปแบบการบันทึกผล');
        }
        if (self::fetchOne('SELECT id FROM sports WHERE LOWER(name) = LOWER(:name) AND id != :id', [
            ':name' => $record['name'],
            ':id' => $id,
        ])) {
            throw new RuntimeException('ชื่อกีฬานี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $row = self::updateRow('sports', $id, $record);
            if (!$activeRule || $scoringType !== $currentScoringType) {
                $decodedRule = $activeRule ? self::decodeSportRule($activeRule) : [];
                self::saveSportRule([
                    'sport_id' => $id,
                    'name' => (string) ($decodedRule['name'] ?? 'กติกามาตรฐาน'),
                    'category' => $row['type'],
                    'player_type' => $row['type'],
                    'team_size' => $row['max_players'],
                    'scoring_type' => $scoringType,
                    'scoring_config' => json_encode(ScoringTypeDetector::defaultConfig($scoringType), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'event_types' => $decodedRule['event_types_data'] ?? self::defaultEventTypes(),
                    'custom_rules' => $row['rules'],
                    'is_active' => true,
                ]);
            }
            self::audit('update', 'sport', $id, array_replace($row, [
                'previous_scoring_type' => $currentScoringType,
                'scoring_type' => $scoringType,
            ]));
            $pdo->commit();
            return array_replace($row, ['scoring_type' => $scoringType]);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
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


    public static function listStudents(array $filters): array
    {
        $user = Auth::requireRole([
            Auth::ROLE_SUPER_ADMIN,
            Auth::ROLE_ADMIN,
            Auth::ROLE_SPORT_HEAD,
        ]);
        $q = trim((string) ($filters['q'] ?? ''));
        $where = [];
        $params = [];
        if (($user['role'] ?? '') === Auth::ROLE_SPORT_HEAD) {
            $department = trim((string) ($user['department_code'] ?? ''));
            if ($department === '') {
                return [];
            }
            $where[] = 'students.department_code = :department_code';
            $params[':department_code'] = $department;
        }
        if ($q !== '') {
            $where[] = '(students.student_code LIKE :q OR students.first_name LIKE :q OR students.last_name LIKE :q OR students.department_code LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        return self::fetchAll(
            'SELECT students.*, departments.name AS department_name
             FROM students
             LEFT JOIN departments ON departments.code = students.department_code
             ' . $whereSql . '
             ORDER BY students.id DESC LIMIT 200'
            , $params
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
            $department = trim((string) (Auth::currentUser()['department_code'] ?? ''));
            $rows = array_values(array_filter($rows, static fn(array $row): bool => $department !== ''
                && (string) ($row['department_code'] ?? '') === $department
                && in_array((string) ($row['sport_id'] ?? ''), $allowed, true)));
        } elseif ((Auth::currentUser()['role'] ?? '') === Auth::ROLE_RESULT_OFFICER) {
            $user = Auth::currentUser();
            $assignments = self::fetchAll('SELECT matches.team1_id, matches.team2_id FROM match_official_assignments
                JOIN matches ON matches.id = match_official_assignments.match_id WHERE match_official_assignments.user_id = :id',
                [':id' => $user['id']]);
            $allowedIds = [];
            foreach ($assignments as $assignment) {
                $allowedIds[] = (string) ($assignment['team1_id'] ?? '');
                $allowedIds[] = (string) ($assignment['team2_id'] ?? '');
            }
            $department = trim((string) ($user['department_code'] ?? ''));
            $rows = array_values(array_filter($rows, static fn(array $row): bool => in_array((string) $row['id'], $allowedIds, true)
                && ($department === '' || (string) ($row['department_code'] ?? '') === $department)));
        }
        if ($tournamentId !== '') {
            $rows = array_values(array_filter($rows, fn(array $row): bool => (string) $row['tournament_id'] === $tournamentId));
        }
        return $rows;
    }

    private static function assertManagedTeamScope(array $team): void
    {
        if ((Auth::currentUser()['role'] ?? '') !== Auth::ROLE_SPORT_HEAD) return;
        Auth::assertSportScope((string) ($team['sport_id'] ?? ''));
        $department = trim((string) (Auth::currentUser()['department_code'] ?? ''));
        if ($department === '' || $department !== (string) ($team['department_code'] ?? '')) {
            throw new RuntimeException('ทีมนี้ไม่ได้อยู่ในสังกัดที่คุณดูแล');
        }
    }

    public static function createTeam(array $payload): array
    {
        Auth::canManageSports();
        $record = self::validateTeamPayload($payload);
        Auth::assertSportScope((string) $record['sport_id']);
        self::assertManagedTeamScope($record);
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
        self::assertManagedTeamScope($current);
        $record = self::validateTeamPayload(array_replace($current, $payload), true);
        Auth::assertSportScope((string) $record['sport_id']);
        self::assertManagedTeamScope($record);
        $row = self::updateRow('teams', $id, $record);
        self::audit('update', 'team', $id, $row);
        return $row;
    }

    public static function deleteTeam(string $id): array
    {
        Auth::canManageSports();
        self::assertManagedTeamScope(self::findById('teams', $id));
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
        self::assertManagedTeamScope(self::findById('teams', $id));
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
            self::assertManagedTeamScope(self::findById('teams', $teamId));
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
                $team = self::fetchOne('SELECT id, sport_id, department_code FROM teams WHERE id = :id', [':id' => $teamId]);
                if (!$team) {
                    throw new RuntimeException('ไม่พบทีมรหัส: ' . $teamId);
                }
                Auth::assertSportScope((string) ($team['sport_id'] ?? ''));
                self::assertManagedTeamScope($team);

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
        self::assertManagedTeamScope(self::findById('teams', (string) $current['team_id']));
        $teamId = trim((string) ($payload['team_id'] ?? $current['team_id']));
        self::assertManagedTeamScope(self::findById('teams', $teamId));
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
        self::assertManagedTeamScope(self::findById('teams', (string) $member['team_id']));
        $deleted = self::deleteRow('team_members', $id);
        self::audit('delete_member', 'team_members', $id, $deleted);
        return $deleted;
    }

    public static function matchOfficialOptions(): array
    {
        Auth::canManageMasterData();
        return self::fetchAll(
            "SELECT id, name, email, role, department_code, sport_ids FROM users
             WHERE is_active = 1 AND role IN ('result_officer', 'sport_head') ORDER BY name, id"
        );
    }

    public static function assignMatchOfficials(string $matchId, array $assignments): array
    {
        Auth::canManageMasterData();
        $match = self::findById('matches', $matchId);
        $sportId = (string) ($match['sport_id'] ?: (self::findById('tournaments', (string) $match['tournament_id'])['sport_id'] ?? ''));
        $teamIds = array_values(array_filter([(string) ($match['team1_id'] ?? ''), (string) ($match['team2_id'] ?? '')]));
        $validated = [];
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) throw new InvalidArgumentException('ข้อมูลผู้รับผิดชอบไม่ถูกต้อง');
            $userId = (string) ($assignment['user_id'] ?? '');
            $user = self::findById('users', $userId);
            if (!Auth::truthy($user['is_active'] ?? false) || !in_array((string) $user['role'], [Auth::ROLE_RESULT_OFFICER, Auth::ROLE_SPORT_HEAD], true)) {
                throw new InvalidArgumentException('ผู้รับผิดชอบต้องเป็น Match Official หรือ Sport Manager ที่เปิดใช้งาน');
            }
            $allowedSports = array_filter(array_map('trim', explode(',', (string) ($user['sport_ids'] ?? ''))));
            if (($user['role'] === Auth::ROLE_SPORT_HEAD || $allowedSports) && !in_array($sportId, $allowedSports, true)) {
                throw new InvalidArgumentException('ผู้รับผิดชอบไม่ได้ดูแลกีฬาของแมตช์นี้');
            }
            $teamId = trim((string) ($assignment['team_id'] ?? ''));
            if ($teamId !== '' && !in_array($teamId, $teamIds, true)) {
                throw new InvalidArgumentException('ทีมที่มอบหมายไม่ได้อยู่ในแมตช์นี้');
            }
            $department = trim((string) ($user['department_code'] ?? ''));
            if ($department !== '' && $teamIds) {
                $scopeTeams = $teamId !== '' ? [$teamId] : $teamIds;
                $inScope = false;
                foreach ($scopeTeams as $candidateId) {
                    $candidate = self::findById('teams', $candidateId);
                    if ((string) $candidate['department_code'] === $department) $inScope = true;
                }
                if (!$inScope) throw new InvalidArgumentException('สังกัดของผู้รับผิดชอบไม่ตรงกับทีมที่มอบหมาย');
            } elseif ($user['role'] === Auth::ROLE_SPORT_HEAD && $teamIds && $teamId === '') {
                throw new InvalidArgumentException('Sport Manager ที่ไม่ระบุสังกัดต้องระบุทีมที่รับผิดชอบ');
            }
            if (isset($validated[$userId])) throw new InvalidArgumentException('มอบหมายผู้ใช้ซ้ำในแมตช์เดียวกัน');
            $validated[$userId] = ['match_id' => $matchId, 'user_id' => $userId, 'team_id' => $teamId !== '' ? $teamId : null,
                'assigned_by' => Auth::currentUser()['email'] ?? '', 'created_at' => Database::now()];
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::deleteRowsWhere('match_official_assignments', 'match_id = :id', [':id' => $matchId]);
            $insert = $pdo->prepare('INSERT INTO match_official_assignments (match_id, user_id, team_id, assigned_by, created_at)
                VALUES (:match_id, :user_id, :team_id, :assigned_by, :created_at)');
            foreach ($validated as $row) $insert->execute([
                ':match_id' => $row['match_id'], ':user_id' => $row['user_id'], ':team_id' => $row['team_id'],
                ':assigned_by' => $row['assigned_by'], ':created_at' => $row['created_at'],
            ]);
            self::audit('assign_officials', 'match', $matchId, ['user_ids' => array_keys($validated)]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return self::fetchAll('SELECT * FROM match_official_assignments WHERE match_id = :id ORDER BY user_id', [':id' => $matchId]);
    }

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

    public static function listMatches(string $tournamentId = ''): array
    {
        Auth::canViewSportsWork();
        $rows = self::joinedMatches();
        if (in_array((string) (Auth::currentUser()['role'] ?? ''), [Auth::ROLE_SPORT_HEAD, Auth::ROLE_RESULT_OFFICER], true)) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => self::mayManageMatchResult($row)));
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
        Auth::canManageMasterData();
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
        Auth::canManageMasterData();
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
        Auth::canManageMasterData();
        $match = self::findById('matches', $id);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        self::deleteRowsWhere('match_event_requests', 'match_id = :id', [':id' => $id]);
        self::deleteRowsWhere('match_events', 'match_id = :id', [':id' => $id]);
        self::deleteRowsWhere('match_periods', 'match_id = :id', [':id' => $id]);
        self::deleteRowsWhere('match_entry_revisions', 'entry_id IN (SELECT id FROM match_entries WHERE match_id = :id)', [':id' => $id]);
        self::deleteRowsWhere('match_entries', 'match_id = :id', [':id' => $id]);
        self::deleteRowsWhere('match_official_assignments', 'match_id = :id', [':id' => $id]);
        $deleted = self::deleteRow('matches', $id);
        self::recalculateStandings((string) $match['tournament_id']);
        self::audit('delete', 'match', $id, $deleted);
        return $deleted;
    }

    public static function saveMatchResult(string $id, array $payload): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $id);
        self::assertMatchResultAccess($match);
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
        $sport = self::findById('sports', (string) $match['sport_id']);
        $requestedType = strtoupper(trim((string) ($payload['scoring_type'] ?? '')));
        $scoringType = in_array($requestedType, ScoringEngine::TYPES, true)
            ? $requestedType
            : ScoringTypeDetector::detect($sport, $rule);
        $scoringConfig = $scoringType === (string) ($rule['scoring_type'] ?? '')
            ? (array) ($rule['scoring_config_data'] ?? [])
            : ScoringTypeDetector::defaultConfig($scoringType);
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
        $existing = self::fetchOne('SELECT * FROM match_results WHERE match_id = :match_id LIMIT 1', [':match_id' => $id]);
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
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
        $sport = self::findById('sports', (string) $match['sport_id']);
        $result = self::fetchOne('SELECT * FROM match_results WHERE match_id = :match_id LIMIT 1', [':match_id' => $matchId]);
        return [
            'match' => $match,
            'rule' => $rule,
            'suggested_scoring_type' => ScoringTypeDetector::detect($sport, $rule),
            'result' => $result ? self::decodeMatchResult($result) : null,
        ];
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
        self::assertMatchReadAccess($match);
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
        self::ensureMatchPeriods($matchId, $rule);
        $resolvedScoringType = self::resolvedMatchScoringType($match, $rule);
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
        Auth::canManageResults();
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
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
        Auth::assertSportScope((string) ($match['sport_id'] ?? ''));
        if (in_array((string) ($match['status'] ?? ''), ['completed', 'cancelled', 'postponed'], true)) {
            throw new RuntimeException('แมตช์นี้ไม่เปิดให้บันทึกแบบร่างแล้ว');
        }
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
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

    public static function saveMatchEvent(array $payload): array
    {
        Auth::canManageResults();
        $matchId = trim((string) ($payload['match_id'] ?? ''));
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
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
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
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
        if ((string) ($match['status'] ?? '') !== 'live') throw new RuntimeException('แก้คะแนนได้เฉพาะแมตช์ที่กำลังแข่งขัน');
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
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

    private static function claimMatchActionRevision(string $matchId, int $expected): void
    {
        $stmt = Database::pdo()->prepare('UPDATE matches SET action_revision = action_revision + 1
            WHERE id = :id AND action_revision = :expected');
        $stmt->execute([':id' => $matchId, ':expected' => $expected]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('แมตช์มีข้อมูลใหม่แล้ว กรุณารีเฟรชก่อนบันทึกอีกครั้ง');
    }

    public static function voidMatchEvent(string $id, array $payload = []): array
    {
        Auth::canManageResults();
        $event = self::findById('match_events', $id);
        $match = self::findById('matches', (string) $event['match_id']);
        self::assertMatchResultAccess($match);
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

    private static function assertRankingMatch(array $match): void
    {
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
        if (!in_array(self::resolvedMatchScoringType($match, $rule), ['TIME', 'RACE_POSITION'], true)) {
            throw new InvalidArgumentException('แมตช์นี้ไม่ใช่การแข่งขันแบบจัดอันดับด้วยเวลา');
        }
    }

    private static function resolvedMatchScoringType(array $match, array $rule): string
    {
        $saved = self::fetchOne('SELECT scoring_type FROM match_results WHERE match_id = :id LIMIT 1', [':id' => $match['id']]);
        if ($saved && in_array((string) $saved['scoring_type'], ScoringEngine::TYPES, true)) {
            return (string) $saved['scoring_type'];
        }
        $sport = self::findById('sports', (string) $match['sport_id']);
        return ScoringTypeDetector::detect($sport, $rule);
    }

    public static function matchEntries(string $matchId): array
    {
        Auth::canManageResults();
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
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
        Auth::canManageResults();
        $matchId = trim((string) ($payload['match_id'] ?? ''));
        $match = self::findById('matches', $matchId);
        self::assertMatchResultAccess($match);
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
    }

    public static function importMatchEntries(array $payload): array
    {
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
        $rule = self::decodeSportRule(self::findById('sport_rule_versions', (string) $match['rule_version_id']));
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
            'invite_expiry_minutes' => (int) self::settings()['staff_invite_expiry_minutes'],
        ];
    }

    public static function setInvitesEnabled(array $payload): array
    {
        Auth::canManageUsers();
        $settings = self::settings();
        $enabled = Auth::truthy($payload['enabled'] ?? $settings['staff_invites_enabled']) ? '1' : '0';
        $minutes = filter_var($payload['expiry_minutes'] ?? $settings['staff_invite_expiry_minutes'], FILTER_VALIDATE_INT);
        if ($minutes === false || $minutes < 5 || $minutes > 1440) {
            throw new InvalidArgumentException('อายุคำเชิญต้องเป็นจำนวนเต็มระหว่าง 5 ถึง 1,440 นาที');
        }
        $column = Database::isMysql() ? '`key`' : 'key';
        $values = [
            'staff_invites_enabled' => [$enabled, 'เปิดใช้ลิงก์คำเชิญทีมงาน'],
            'staff_invite_expiry_minutes' => [(string) $minutes, 'อายุคำเชิญใหม่ หน่วยนาที'],
        ];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($values as $key => [$value, $note]) {
                if (self::fetchOne("SELECT {$column} FROM settings WHERE {$column} = :key", [':key' => $key])) {
                    $pdo->prepare("UPDATE settings SET value = :value WHERE {$column} = :key")->execute([':value' => $value, ':key' => $key]);
                } else {
                    $pdo->prepare("INSERT INTO settings ({$column}, value, note) VALUES (:key, :value, :note)")->execute([':key' => $key, ':value' => $value, ':note' => $note]);
                }
            }
            self::audit('toggle_staff_invites', 'settings', 'staff_invites_enabled', ['enabled' => $enabled === '1', 'expiry_minutes' => $minutes]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return ['enabled' => $enabled === '1', 'expiry_minutes' => $minutes];
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

    public static function setUserActiveStatus(array $payload): array
    {
        Auth::canManageUsers();
        if (!array_key_exists('is_active', $payload)) {
            throw new InvalidArgumentException('กรุณาระบุสถานะบัญชี');
        }
        $id = (string) ($payload['id'] ?? '');
        self::findById('users', $id);
        $enabled = Auth::truthy($payload['is_active']);
        if (!$enabled && (string) (Auth::currentUser()['id'] ?? '') === $id) {
            throw new RuntimeException('ไม่สามารถปิดใช้งานบัญชีของตัวเองได้');
        }
        $row = self::updateRow('users', $id, ['is_active' => $enabled ? 1 : 0]);
        self::audit('update_user_status', 'users', $id, ['is_active' => $enabled]);
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
            'expires_at' => $now->modify('+' . max(5, min(1440, (int) self::settings()['staff_invite_expiry_minutes'])) . ' minutes')->format('Y-m-d H:i:s'),
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
        $role = (string) ($payload['role'] ?? Auth::ROLE_ADMIN);
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
        $role = (string) ($payload['role'] ?? Auth::ROLE_ADMIN);
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

    private static function scoringConfigFromPayload(string $type, array $payload): array
    {
        $config = [];
        if (array_key_exists('include_in_standings', $payload)) {
            $config['include_in_standings'] = self::boolToInt($payload['include_in_standings']) === 1;
        }
        if (in_array($type, ['SCORE', 'PERIOD_SCORE'], true) && array_key_exists('quick_points', $payload)) {
            $tokens = preg_split('/\s*,\s*/', trim((string) $payload['quick_points'])) ?: [];
            if (!$tokens || count($tokens) > 10 || array_filter($tokens, static fn(string $value): bool => preg_match('/^(?:[1-9]|10)$/', $value) !== 1)) {
                throw new InvalidArgumentException('ปุ่มเพิ่มแต้มต้องเป็นจำนวนเต็ม 1-10 ไม่เกิน 10 ค่า');
            }
            $config['quick_points'] = array_values(array_unique(array_map('intval', $tokens)));
        }
        if ($type === 'SCORE') {
            if (array_key_exists('allow_draw', $payload)) $config['allow_draw'] = self::boolToInt($payload['allow_draw']) === 1;
            if (array_key_exists('overtime_enabled', $payload)) $config['allow_extra_time'] = self::boolToInt($payload['overtime_enabled']) === 1;
            if (array_key_exists('tie_break_method', $payload)) $config['allow_penalty'] = (string) $payload['tie_break_method'] === 'penalty';
            return $config;
        }
        if ($type === 'SET') {
            if (array_key_exists('best_of', $payload) || array_key_exists('period_count', $payload)) $config['best_of'] = max(1, (int) ($payload['best_of'] ?? $payload['period_count']));
            if (array_key_exists('points_to_win', $payload) && (int) $payload['points_to_win'] > 0) $config['points_to_win_set'] = (int) $payload['points_to_win'];
            if (array_key_exists('normal_set_points', $payload) && (int) $payload['normal_set_points'] > 0) $config['normal_set_points'] = (int) $payload['normal_set_points'];
            if (array_key_exists('final_set_points', $payload) && (int) $payload['final_set_points'] > 0) $config['final_set_points'] = (int) $payload['final_set_points'];
            if (array_key_exists('set_win_by', $payload)) $config['win_by'] = max(1, (int) $payload['set_win_by']);
            if (array_key_exists('max_points', $payload) && (int) $payload['max_points'] > 0) $config['max_points'] = (int) $payload['max_points'];
            return $config;
        }
        if ($type === 'PERIOD_SCORE') {
            if (array_key_exists('period_count', $payload)) $config['periods'] = max(1, (int) $payload['period_count']);
            if (array_key_exists('overtime_enabled', $payload)) $config['allow_overtime'] = self::boolToInt($payload['overtime_enabled']) === 1;
        }
        if (in_array($type, ['ROUND', 'END_SCORE'], true) && array_key_exists('target_score', $payload)) {
            $config['target_score'] = $type === 'ROUND' ? max(0, (int) $payload['target_score']) : max(1, (int) $payload['target_score']);
        }
        if ($type === 'MAP' && (array_key_exists('best_of', $payload) || array_key_exists('period_count', $payload))) $config['best_of'] = max(1, (int) ($payload['best_of'] ?? $payload['period_count']));
        if (in_array($type, ['JUDGED', 'TARGET', 'POINT_RANKING'], true) && array_key_exists('aggregation', $payload)) {
            $config['aggregation'] = strtoupper((string) $payload['aggregation']);
        }
        if (in_array($type, ['COUNT', 'COMBINED'], true) && array_key_exists('numeric_win_by', $payload)) {
            $winBy = strtoupper((string) $payload['numeric_win_by']);
            if (!in_array($winBy, ['HIGHEST', 'LOWEST'], true)) throw new InvalidArgumentException('วิธีตัดสินค่าตัวเลขไม่ถูกต้อง');
            $config['win_by'] = $winBy;
        }
        return $config;
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
                $decoded = $ruleId !== '' ? self::decodeSportRule(self::findById('sport_rule_versions', $ruleId)) : [];
                $detected = $decoded && !empty($match['sport_id'])
                    ? ScoringTypeDetector::detect(self::findById('sports', (string) $match['sport_id']), $decoded)
                    : 'SCORE';
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
