<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function boot(): void
    {
        self::pdo();
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (!is_dir(STORAGE_PATH)) {
            mkdir(STORAGE_PATH, 0775, true);
        }

        $dsn = self::configuredDsn();
        $user = getenv('PRSPORT_DB_USER') ?: getenv('DB_USERNAME') ?: (str_starts_with($dsn, 'mysql:') ? 'root' : null);
        $pass = getenv('PRSPORT_DB_PASS') ?: getenv('DB_PASSWORD') ?: null;

        self::ensureMysqlDatabase($dsn, $user, $pass);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        // Transaction poolers cannot retain server-side named statements.
        if (str_starts_with($dsn, 'pgsql:')) {
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
        }
        self::$pdo = new PDO($dsn, $user, $pass, $options);

        if (self::isSqlite()) {
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }
        self::initializeSchema(self::$pdo, $dsn, $user);

        return self::$pdo;
    }

    private static function initializeSchema(PDO $pdo, string $dsn, ?string $user): void
    {
        // In-memory databases are fresh on every process; never cache their setup.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            self::migrate($pdo);
            self::seedBootstrapAdmin($pdo);
            return;
        }

        $signature = hash('sha256', implode('|', [
            $dsn, (string) $user,
            hash_file('sha256', __FILE__),
            hash_file('sha256', DATABASE_PATH . '/schema.pgsql.sql'),
            hash_file('sha256', DATABASE_PATH . '/seed.pgsql.sql'),
        ]));
        // Render recreates this local marker on a new container. Only mark a
        // successful migration, and serialize first requests within the instance.
        $marker = STORAGE_PATH . '/schema-' . hash('sha256', $dsn . '|' . $user) . '.ready';
        $lock = fopen($marker, 'c+');
        if ($lock === false) {
            throw new RuntimeException('Cannot open schema initialization marker');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock schema initialization marker');
            }
            if (trim((string) stream_get_contents($lock)) === $signature) {
                return;
            }
            self::migrate($pdo);
            self::seedBootstrapAdmin($pdo);
            rewind($lock);
            if (!ftruncate($lock, 0) || fwrite($lock, $signature) !== strlen($signature) || !fflush($lock)) {
                throw new RuntimeException('Cannot save schema initialization marker');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function driver(): string
    {
        return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function isMysql(): bool
    {
        return self::driver() === 'mysql';
    }

    public static function isSqlite(): bool
    {
        return self::driver() === 'sqlite';
    }

    public static function isPgsql(): bool
    {
        return self::driver() === 'pgsql';
    }

    public static function info(): array
    {
        $dsn = self::configuredDsn();
        return [
            'driver' => self::driver(),
            'dsn' => preg_replace('/password=[^;]*/i', 'password=***', $dsn),
        ];
    }

    private static function configuredDsn(): string
    {
        $dsn = getenv('PRSPORT_DB_DSN');
        if ($dsn) {
            return $dsn;
        }

        $url = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('SUPABASE_DB_URL');
        if ($url) {
            return self::postgresUrlToDsn($url);
        }

        $host = getenv('DB_HOST');
        $database = getenv('DB_DATABASE');
        if ($host && $database) {
            $port = getenv('DB_PORT') ?: '3306';
            $driver = getenv('DB_CONNECTION') ?: getenv('DB_DRIVER') ?: (str_contains($host, 'supabase') ? 'pgsql' : 'mysql');
            if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
                $sslMode = getenv('DB_SSLMODE') ?: 'require';
                return "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslMode}";
            }
            return "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        }

        return 'mysql:host=127.0.0.1;port=3306;dbname=prsport;charset=utf8mb4';
    }

    private static function postgresUrlToDsn(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            return $url;
        }

        $database = ltrim((string) $parts['path'], '/');
        if (!getenv('PRSPORT_DB_USER') && !getenv('DB_USERNAME') && isset($parts['user'])) {
            putenv('DB_USERNAME=' . rawurldecode((string) $parts['user']));
        }
        if (!getenv('PRSPORT_DB_PASS') && !getenv('DB_PASSWORD') && isset($parts['pass'])) {
            putenv('DB_PASSWORD=' . rawurldecode((string) $parts['pass']));
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        return sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            (string) $parts['host'],
            (string) ($parts['port'] ?? 5432),
            $database,
            (string) ($query['sslmode'] ?? getenv('DB_SSLMODE') ?: 'require')
        );
    }

    public static function now(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    private static function migrate(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $suffix = ['mysql' => '.mysql', 'pgsql' => '.pgsql'][$driver] ?? '';
        $schema = file_get_contents(DATABASE_PATH . DIRECTORY_SEPARATOR . 'schema' . $suffix . '.sql');
        $seed = file_get_contents(DATABASE_PATH . DIRECTORY_SEPARATOR . 'seed' . $suffix . '.sql');
        if ($schema === false || $seed === false) {
            throw new RuntimeException('ไม่พบไฟล์ schema หรือ seed');
        }
        $pdo->exec($schema);
        self::ensurePortableColumns($pdo);
        self::consolidateLegacyRegistrationRole($pdo);
        if ($driver === 'mysql') {
            self::ensureMysqlIndexes($pdo);
        }
        $pdo->exec($seed);
        $pdo->exec(self::insertIgnoreSelectSql(
            'tournament_sports',
            '(tournament_id, sport_id)',
            'SELECT id, sport_id FROM tournaments WHERE sport_id IS NOT NULL'
        ));
        self::backfillCompetitionModel($pdo);
    }

    private static function consolidateLegacyRegistrationRole(PDO $pdo): void
    {
        $roles = [
            ':operation_admin' => Auth::ROLE_ADMIN,
            ':registration_officer' => 'registration_officer',
        ];
        $users = $pdo->prepare(
            'UPDATE users SET role = :operation_admin, updated_at = :updated_at WHERE role = :registration_officer'
        );
        $users->execute($roles + [':updated_at' => self::now()]);

        $invites = $pdo->prepare(
            'UPDATE user_invites SET role = :operation_admin WHERE role = :registration_officer'
        );
        $invites->execute($roles);
    }

    private static function seedBootstrapAdmin(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $now = self::now();
        $email = getenv('PRSPORT_ADMIN_EMAIL') ?: 'admin@rmutt.local';
        $password = getenv('PRSPORT_ADMIN_PASSWORD') ?: 'Temp@1234';
        $stmt = $pdo->prepare(
            'INSERT INTO users (id, email, name, role, department_code, sport_ids, password_hash, password_salt, password_updated_at, is_active, created_at, updated_at)
             VALUES (1, :email, :name, :role, NULL, NULL, :hash, NULL, :password_updated_at, 1, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':email' => strtolower($email),
            ':name' => 'ผู้ดูแลระบบ',
            ':role' => Auth::ROLE_SUPER_ADMIN,
            ':hash' => password_hash($password, PASSWORD_DEFAULT),
            ':password_updated_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    private static function ensureMysqlDatabase(string $dsn, ?string $user, ?string $pass): void
    {
        if (!str_starts_with($dsn, 'mysql:') || !preg_match('/(?:^|;)dbname=([^;]+)/', substr($dsn, 6), $match)) {
            return;
        }

        $database = $match[1];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException('ชื่อฐานข้อมูล MySQL ไม่ถูกต้อง');
        }

        $serverDsn = preg_replace('/;?dbname=[^;]*/', '', $dsn);
        if ($serverDsn === null) {
            return;
        }

        try {
            $pdo = new PDO($serverDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $exception) {
            $code = (int) $exception->getCode();
            if (in_array($code, [1044, 1045, 42000], true)) {
                return;
            }
            throw $exception;
        }
    }

    private static function ensureMysqlIndexes(PDO $pdo): void
    {
        $indexes = [
            ['students', 'idx_students_department', 'department_code'],
            ['tournaments', 'idx_tournaments_sport', 'sport_id'],
            ['tournament_sports', 'idx_tournament_sports_sport', 'sport_id'],
            ['tournament_sport_settings', 'idx_tournament_sport_settings_rule', 'rule_version_id'],
            ['tournament_departments', 'idx_tournament_departments_lookup', 'tournament_id, sport_id'],
            ['teams', 'idx_teams_tournament', 'tournament_id'],
            ['team_members', 'idx_team_members_team', 'team_id'],
            ['team_members', 'idx_team_members_student', 'student_id'],
            ['matches', 'idx_matches_tournament', 'tournament_id'],
            ['match_events', 'idx_match_events_match', 'match_id, elapsed_seconds'],
            ['match_result_revisions', 'idx_match_result_revisions_result', 'match_result_id, revision_number'],
            ['sport_rule_versions', 'idx_rule_versions_sport', 'sport_id, is_active'],
            ['teams', 'idx_teams_sport', 'sport_id'],
            ['news', 'idx_news_status_type', 'status, news_type'],
            ['audit_logs', 'idx_audit_created_at', 'created_at'],
            ['user_invites', 'idx_user_invites_status_expires', 'status, expires_at'],
        ];

        $exists = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index'
        );

        foreach ($indexes as [$table, $name, $columns]) {
            $exists->execute([':table' => $table, ':index' => $name]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }
            $pdo->exec("CREATE INDEX {$name} ON {$table} ({$columns})");
        }
    }

    private static function ensurePortableColumns(PDO $pdo): void
    {
        $columns = [
            'departments' => [
                'faculty_id' => 'INTEGER',
                'logo_url' => 'TEXT',
            ],
            'sports' => [
                'logo_url' => 'TEXT',
            ],
            'students' => [
                'photo_url' => 'TEXT',
            ],
            'tournaments' => [
                'logo_url' => 'TEXT',
            ],
            'teams' => [
                'sport_id' => 'INTEGER',
                'logo_url' => 'TEXT',
            ],
            'matches' => [
                'sport_id' => 'INTEGER',
                'rule_version_id' => 'INTEGER',
                'current_period' => 'INTEGER NOT NULL DEFAULT 1',
                'clock_seconds' => 'INTEGER NOT NULL DEFAULT 0',
                'clock_status' => "VARCHAR(30) NOT NULL DEFAULT 'stopped'",
                'overtime_count' => 'INTEGER NOT NULL DEFAULT 0',
                'action_revision' => 'INTEGER NOT NULL DEFAULT 0',
            ],
            'match_entries' => [
                'revision_number' => 'INTEGER NOT NULL DEFAULT 0',
            ],
            'sport_rule_versions' => [
                'scoring_type' => "VARCHAR(40) NOT NULL DEFAULT 'SCORE'",
                'scoring_config' => 'TEXT',
            ],
            'news' => [
                'source_url' => 'TEXT',
            ],
        ];

        foreach ($columns as $table => $definitions) {
            foreach ($definitions as $column => $definition) {
                if (!self::columnExists($pdo, $table, $column)) {
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                }
            }
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
            );
            $stmt->execute([':table' => $table, ':column' => $column]);
            return (int) $stmt->fetchColumn() > 0;
        }

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = current_schema() AND table_name = :table AND column_name = :column'
            );
            $stmt->execute([':table' => $table, ':column' => $column]);
            return (int) $stmt->fetchColumn() > 0;
        }

        foreach ($pdo->query("PRAGMA table_info({$table})") as $row) {
            if ((string) $row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    private static function insertIgnoreSelectSql(string $table, string $columns, string $select): string
    {
        $driver = self::$pdo?->getAttribute(PDO::ATTR_DRIVER_NAME) ?: 'sqlite';
        if ($driver === 'mysql') {
            return "INSERT IGNORE INTO {$table} {$columns} {$select}";
        }
        if ($driver === 'pgsql') {
            return "INSERT INTO {$table} {$columns} {$select} ON CONFLICT DO NOTHING";
        }
        return "INSERT OR IGNORE INTO {$table} {$columns} {$select}";
    }

    private static function backfillCompetitionModel(PDO $pdo): void
    {
        $now = self::now();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insert = $driver === 'mysql' ? 'INSERT IGNORE' : ($driver === 'pgsql' ? 'INSERT' : 'INSERT OR IGNORE');
        $onConflict = $driver === 'pgsql' ? ' ON CONFLICT DO NOTHING' : '';
        // Legacy schemas require an affiliation code. This neutral record lets
        // independent teams and athletes use the same database without a faculty.
        $pdo->exec("{$insert} INTO departments (id, code, name, faculty_id, logo_url, is_active)
                    VALUES (999999, 'OPEN', 'ทั่วไป', NULL, NULL, 1){$onConflict}");
        $pdo->exec("{$insert} INTO universities (id, code, name, logo_url, is_active, created_at, updated_at)
                    VALUES (1, 'RMUTT', 'มหาวิทยาลัยเทคโนโลยีราชมงคลธัญบุรี', 'public/assets/logo.png', 1, '{$now}', '{$now}'){$onConflict}");
        $pdo->exec("{$insert} INTO faculties (id, university_id, code, name, logo_url, is_active, created_at, updated_at)
                    VALUES (1, 1, '102', 'คณะครุศาสตร์อุตสาหกรรม', 'public/assets/logo.png', 1, '{$now}', '{$now}'){$onConflict}");
        $pdo->exec("UPDATE departments SET faculty_id = 1 WHERE faculty_id IS NULL AND code <> 'OPEN'");
        $pdo->exec("UPDATE departments SET faculty_id = NULL WHERE code = 'OPEN'");
        $pdo->exec('UPDATE teams SET sport_id = (SELECT sport_id FROM tournaments WHERE tournaments.id = teams.tournament_id) WHERE sport_id IS NULL');
        $pdo->exec('UPDATE matches SET sport_id = COALESCE((SELECT sport_id FROM teams WHERE teams.id = matches.team1_id), (SELECT sport_id FROM tournaments WHERE tournaments.id = matches.tournament_id)) WHERE sport_id IS NULL');

        $sports = $pdo->query('SELECT id, name, type, max_players, rules, icon FROM sports ORDER BY id')->fetchAll();
        $nextId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM sport_rule_versions')->fetchColumn();
        $exists = $pdo->prepare('SELECT id FROM sport_rule_versions WHERE sport_id = :sport_id AND is_active = 1 LIMIT 1');
        $create = $pdo->prepare(
            'INSERT INTO sport_rule_versions
             (id, sport_id, version_number, name, category, player_type, team_size, scoring_type, scoring_config, scoring_system, match_format, period_format, overtime_rules, tie_break_rules, ranking_rules, event_types, custom_rules, is_active, created_by, created_at, updated_at)
             VALUES (:id, :sport_id, 1, :name, :category, :player_type, :team_size, :scoring_type, :scoring_config, :scoring_system, :match_format, :period_format, :overtime_rules, :tie_break_rules, :ranking_rules, :event_types, :custom_rules, 1, :created_by, :created_at, :updated_at)'
        );
        foreach ($sports as $sport) {
            $exists->execute([':sport_id' => $sport['id']]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $detectedType = ScoringTypeDetector::detect($sport);
            $create->execute([
                ':id' => $nextId++,
                ':sport_id' => $sport['id'],
                ':name' => 'กติกามาตรฐาน',
                ':category' => (string) $sport['type'],
                ':player_type' => (string) $sport['type'],
                ':team_size' => max(1, (int) $sport['max_players']),
                ':scoring_type' => $detectedType,
                ':scoring_config' => json_encode(ScoringTypeDetector::defaultConfig($detectedType), JSON_UNESCAPED_UNICODE),
                ':scoring_system' => json_encode(['mode' => 'points', 'win_by' => 'highest', 'event_points' => 1], JSON_UNESCAPED_UNICODE),
                ':match_format' => json_encode(['allow_draw' => true], JSON_UNESCAPED_UNICODE),
                ':period_format' => json_encode(['count' => 2, 'duration_minutes' => 45, 'names' => ['ครึ่งแรก', 'ครึ่งหลัง']], JSON_UNESCAPED_UNICODE),
                ':overtime_rules' => json_encode(['enabled' => false, 'periods' => 2, 'duration_minutes' => 15], JSON_UNESCAPED_UNICODE),
                ':tie_break_rules' => json_encode(['method' => 'draw'], JSON_UNESCAPED_UNICODE),
                ':ranking_rules' => json_encode(['win_points' => 3, 'draw_points' => 1, 'loss_points' => 0, 'order' => ['points', 'score_difference', 'scored']], JSON_UNESCAPED_UNICODE),
                ':event_types' => json_encode([
                    ['code' => 'score', 'label' => 'ทำคะแนน', 'points' => 1, 'participant' => 'athlete'],
                    ['code' => 'foul', 'label' => 'ฟาวล์', 'points' => 0, 'participant' => 'athlete'],
                    ['code' => 'yellow_card', 'label' => 'ใบเหลือง', 'points' => 0, 'participant' => 'athlete'],
                    ['code' => 'red_card', 'label' => 'ใบแดง', 'points' => 0, 'participant' => 'athlete'],
                    ['code' => 'substitution', 'label' => 'เปลี่ยนตัว', 'points' => 0, 'participant' => 'athlete'],
                    ['code' => 'note', 'label' => 'เหตุการณ์อื่น', 'points' => 0, 'participant' => 'optional'],
                ], JSON_UNESCAPED_UNICODE),
                ':custom_rules' => json_encode(['legacy_note' => (string) ($sport['rules'] ?? '')], JSON_UNESCAPED_UNICODE),
                ':created_by' => 'migration',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
        $upgradeRule = $pdo->prepare('UPDATE sport_rule_versions SET scoring_type = :scoring_type, scoring_config = :scoring_config WHERE id = :id');
        foreach ($pdo->query('SELECT id, scoring_type, scoring_config, scoring_system, match_format, overtime_rules, tie_break_rules, period_format FROM sport_rule_versions')->fetchAll() as $legacyRule) {
            if (trim((string) ($legacyRule['scoring_config'] ?? '')) !== '') {
                continue;
            }
            $scoring = json_decode((string) ($legacyRule['scoring_system'] ?? '{}'), true) ?: [];
            $match = json_decode((string) ($legacyRule['match_format'] ?? '{}'), true) ?: [];
            $overtime = json_decode((string) ($legacyRule['overtime_rules'] ?? '{}'), true) ?: [];
            $tieBreak = json_decode((string) ($legacyRule['tie_break_rules'] ?? '{}'), true) ?: [];
            $period = json_decode((string) ($legacyRule['period_format'] ?? '{}'), true) ?: [];
            $type = ['sets' => 'SET', 'time' => 'TIME', 'rank' => 'RACE_POSITION'][(string) ($scoring['mode'] ?? 'points')] ?? 'SCORE';
            $config = match ($type) {
                'SCORE' => [
                    'allow_draw' => !empty($match['allow_draw']),
                    'allow_extra_time' => !empty($overtime['enabled']),
                    'allow_penalty' => (string) ($tieBreak['method'] ?? 'draw') === 'penalty',
                    'include_in_standings' => true,
                ],
                'SET' => ['best_of' => max(1, (int) ($period['count'] ?? 3)), 'include_in_standings' => true],
                default => ['include_in_standings' => false],
            };
            $upgradeRule->execute([
                ':scoring_type' => $type,
                ':scoring_config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                ':id' => $legacyRule['id'],
            ]);
        }
        $pdo->exec('UPDATE matches SET rule_version_id = (SELECT id FROM sport_rule_versions WHERE sport_rule_versions.sport_id = matches.sport_id AND is_active = 1 ORDER BY version_number DESC LIMIT 1) WHERE rule_version_id IS NULL');
    }
}
