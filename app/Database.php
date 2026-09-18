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

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if (self::isSqlite()) {
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }
        self::migrate(self::$pdo);
        self::seedBootstrapAdmin(self::$pdo);

        return self::$pdo;
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

        $host = getenv('DB_HOST');
        $database = getenv('DB_DATABASE');
        if ($host && $database) {
            $port = getenv('DB_PORT') ?: '3306';
            return "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        }

        return 'mysql:host=127.0.0.1;port=3306;dbname=prsport;charset=utf8mb4';
    }

    public static function now(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    private static function migrate(PDO $pdo): void
    {
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? '.mysql' : '';
        $schema = file_get_contents(DATABASE_PATH . DIRECTORY_SEPARATOR . 'schema' . $suffix . '.sql');
        $seed = file_get_contents(DATABASE_PATH . DIRECTORY_SEPARATOR . 'seed' . $suffix . '.sql');
        if ($schema === false || $seed === false) {
            throw new RuntimeException('ไม่พบไฟล์ schema หรือ seed');
        }
        $pdo->exec($schema);
        self::ensurePortableColumns($pdo);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            self::ensureMysqlIndexes($pdo);
        }
        $pdo->exec($seed);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec(
                'INSERT IGNORE INTO tournament_sports (tournament_id, sport_id)
                 SELECT id, sport_id FROM tournaments WHERE sport_id IS NOT NULL'
            );
        } else {
            $pdo->exec(
                'INSERT OR IGNORE INTO tournament_sports (tournament_id, sport_id)
                 SELECT id, sport_id FROM tournaments WHERE sport_id IS NOT NULL'
            );
        }
        self::backfillCompetitionModel($pdo);
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
            ['teams', 'idx_teams_tournament', 'tournament_id'],
            ['team_members', 'idx_team_members_team', 'team_id'],
            ['team_members', 'idx_team_members_student', 'student_id'],
            ['matches', 'idx_matches_tournament', 'tournament_id'],
            ['match_events', 'idx_match_events_match', 'match_id, elapsed_seconds'],
            ['sport_rule_versions', 'idx_rule_versions_sport', 'sport_id, is_active'],
            ['teams', 'idx_teams_sport', 'sport_id'],
            ['news', 'idx_news_status_type', 'status, news_type'],
            ['audit_logs', 'idx_audit_created_at', 'created_at'],
            ['tasks', 'idx_tasks_status_due', 'status, due_at'],
            ['tasks', 'idx_tasks_assignee', 'assignee_email'],
            ['user_invites', 'idx_user_invites_status_expires', 'status, expires_at'],
            ['report_templates', 'idx_report_templates_category', 'category_id'],
            ['reports', 'idx_reports_status_year', 'status, academic_year'],
            ['reports', 'idx_reports_category_template', 'category_id, template_id'],
            ['reports', 'idx_reports_tournament', 'tournament_id'],
            ['report_kpis', 'idx_report_kpis_report', 'report_id'],
            ['report_attachments', 'idx_report_attachments_report', 'report_id'],
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

        foreach ($pdo->query("PRAGMA table_info({$table})") as $row) {
            if ((string) $row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    private static function backfillCompetitionModel(PDO $pdo): void
    {
        $now = self::now();
        $insert = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $pdo->exec("{$insert} INTO universities (id, code, name, logo_url, is_active, created_at, updated_at)
                    VALUES (1, 'RMUTT', 'มหาวิทยาลัยเทคโนโลยีราชมงคลธัญบุรี', 'public/assets/logo.png', 1, '{$now}', '{$now}')");
        $pdo->exec("{$insert} INTO faculties (id, university_id, code, name, logo_url, is_active, created_at, updated_at)
                    VALUES (1, 1, '102', 'คณะครุศาสตร์อุตสาหกรรม', 'public/assets/logo.png', 1, '{$now}', '{$now}')");
        $pdo->exec('UPDATE departments SET faculty_id = 1 WHERE faculty_id IS NULL');
        $pdo->exec('UPDATE teams SET sport_id = (SELECT sport_id FROM tournaments WHERE tournaments.id = teams.tournament_id) WHERE sport_id IS NULL');
        $pdo->exec('UPDATE matches SET sport_id = COALESCE((SELECT sport_id FROM teams WHERE teams.id = matches.team1_id), (SELECT sport_id FROM tournaments WHERE tournaments.id = matches.tournament_id)) WHERE sport_id IS NULL');

        $sports = $pdo->query('SELECT id, name, type, max_players, rules FROM sports ORDER BY id')->fetchAll();
        $nextId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM sport_rule_versions')->fetchColumn();
        $exists = $pdo->prepare('SELECT id FROM sport_rule_versions WHERE sport_id = :sport_id AND is_active = 1 LIMIT 1');
        $create = $pdo->prepare(
            'INSERT INTO sport_rule_versions
             (id, sport_id, version_number, name, category, player_type, team_size, scoring_system, match_format, period_format, overtime_rules, tie_break_rules, ranking_rules, event_types, custom_rules, is_active, created_by, created_at, updated_at)
             VALUES (:id, :sport_id, 1, :name, :category, :player_type, :team_size, :scoring_system, :match_format, :period_format, :overtime_rules, :tie_break_rules, :ranking_rules, :event_types, :custom_rules, 1, :created_by, :created_at, :updated_at)'
        );
        foreach ($sports as $sport) {
            $exists->execute([':sport_id' => $sport['id']]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $create->execute([
                ':id' => $nextId++,
                ':sport_id' => $sport['id'],
                ':name' => 'กติกามาตรฐาน',
                ':category' => (string) $sport['type'],
                ':player_type' => (string) $sport['type'],
                ':team_size' => max(1, (int) $sport['max_players']),
                ':scoring_system' => json_encode(['mode' => 'points', 'win_by' => 'highest', 'event_points' => 1], JSON_UNESCAPED_UNICODE),
                ':match_format' => json_encode(['allow_draw' => true], JSON_UNESCAPED_UNICODE),
                ':period_format' => json_encode(['count' => 2, 'duration_minutes' => 45, 'names' => ['ครึ่งแรก', 'ครึ่งหลัง']], JSON_UNESCAPED_UNICODE),
                ':overtime_rules' => json_encode(['enabled' => false, 'periods' => 2, 'duration_minutes' => 15], JSON_UNESCAPED_UNICODE),
                ':tie_break_rules' => json_encode(['method' => 'draw'], JSON_UNESCAPED_UNICODE),
                ':ranking_rules' => json_encode(['win_points' => 3, 'draw_points' => 1, 'loss_points' => 0, 'order' => ['points', 'score_difference', 'scored']], JSON_UNESCAPED_UNICODE),
                ':event_types' => json_encode([
                    ['code' => 'score', 'label' => 'ทำคะแนน', 'points' => 1],
                    ['code' => 'foul', 'label' => 'ฟาวล์', 'points' => 0],
                    ['code' => 'yellow_card', 'label' => 'ใบเหลือง', 'points' => 0],
                    ['code' => 'red_card', 'label' => 'ใบแดง', 'points' => 0],
                    ['code' => 'substitution', 'label' => 'เปลี่ยนตัว', 'points' => 0],
                    ['code' => 'note', 'label' => 'เหตุการณ์อื่น', 'points' => 0],
                ], JSON_UNESCAPED_UNICODE),
                ':custom_rules' => json_encode(['legacy_note' => (string) ($sport['rules'] ?? '')], JSON_UNESCAPED_UNICODE),
                ':created_by' => 'migration',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
        $pdo->exec('UPDATE matches SET rule_version_id = (SELECT id FROM sport_rule_versions WHERE sport_rule_versions.sport_id = matches.sport_id AND is_active = 1 ORDER BY version_number DESC LIMIT 1) WHERE rule_version_id IS NULL');
    }
}
