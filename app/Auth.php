<?php
declare(strict_types=1);

final class Auth
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SPORT_HEAD = 'sport_head';
    public const ROLE_REGISTRATION_OFFICER = 'registration_officer';
    public const ROLE_RESULT_OFFICER = 'result_officer';
    public const ROLE_PUBLIC_RELATIONS = 'public_relations';
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_GUEST = 'guest';

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function currentUser(): array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return self::guest();
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user || !self::truthy($user['is_active'] ?? false)) {
            unset($_SESSION['user_id']);
            return self::guest();
        }

        return self::sanitize($user);
    }

    public static function login(array $payload): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('กรุณากรอกอีเมลให้ถูกต้อง');
        }
        if ($password === '') {
            throw new InvalidArgumentException('กรุณากรอกรหัสผ่าน');
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE LOWER(email) = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user || !self::truthy($user['is_active'] ?? false)) {
            throw new RuntimeException('ไม่พบบัญชีผู้ดูแลที่เปิดใช้งาน');
        }

        if (empty($user['password_hash'])) {
            self::validatePasswordStrength($password);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $now = Database::now();
            $update = Database::pdo()->prepare('UPDATE users SET password_hash = :hash, password_updated_at = :now, updated_at = :now WHERE id = :id');
            $update->execute([':hash' => $hash, ':now' => $now, ':id' => $user['id']]);
            $user['password_hash'] = $hash;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('รหัสผ่านไม่ถูกต้อง');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        AppService::audit('login', 'users', (string) $user['id'], ['email' => $email, 'role' => $user['role']]);

        return [
            'user' => self::sanitize($user),
            'csrf' => self::csrfToken(),
        ];
    }

    public static function logout(): array
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        return ['user' => self::guest(), 'csrf' => self::csrfToken()];
    }

    public static function requireRole(array $roles): array
    {
        $user = self::currentUser();
        if (!self::truthy($user['is_active'] ?? false) || !in_array($user['role'] ?? self::ROLE_GUEST, $roles, true)) {
            throw new RuntimeException('ไม่มีสิทธิ์ใช้งานส่วนนี้');
        }
        return $user;
    }

    public static function canManageUsers(): array
    {
        return self::requireRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public static function canManageMasterData(): array
    {
        return self::requireRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public static function canManageRegistration(): array
    {
        return self::requireRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_REGISTRATION_OFFICER]);
    }

    public static function canManageSports(): array
    {
        return self::requireRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_SPORT_HEAD]);
    }

    public static function canViewSportsWork(): array
    {
        return self::requireRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SPORT_HEAD,
            self::ROLE_RESULT_OFFICER,
            self::ROLE_PUBLIC_RELATIONS,
        ]);
    }

    public static function canManageResults(): array
    {
        return self::requireRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_SPORT_HEAD, self::ROLE_RESULT_OFFICER]);
    }

    public static function canManageNews(): array
    {
        return self::requireRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_PUBLIC_RELATIONS]);
    }

    public static function canViewReports(): array
    {
        return self::requireRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SPORT_HEAD,
            self::ROLE_REGISTRATION_OFFICER,
            self::ROLE_RESULT_OFFICER,
            self::ROLE_PUBLIC_RELATIONS,
            self::ROLE_VIEWER,
        ]);
    }

    public static function canEditReports(): array
    {
        return self::requireRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SPORT_HEAD,
            self::ROLE_REGISTRATION_OFFICER,
            self::ROLE_RESULT_OFFICER,
            self::ROLE_PUBLIC_RELATIONS,
        ]);
    }

    public static function canPublishReports(): array
    {
        return self::requireRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public static function roleOptions(): array
    {
        return [
            ['value' => self::ROLE_SUPER_ADMIN, 'label' => 'Super Admin'],
            ['value' => self::ROLE_ADMIN, 'label' => 'Admin / เจ้าหน้าที่กลาง'],
            ['value' => self::ROLE_SPORT_HEAD, 'label' => 'หัวหน้ากีฬา'],
            ['value' => self::ROLE_REGISTRATION_OFFICER, 'label' => 'ฝ่ายทะเบียนนักกีฬา'],
            ['value' => self::ROLE_RESULT_OFFICER, 'label' => 'กรรมการบันทึกผล'],
            ['value' => self::ROLE_PUBLIC_RELATIONS, 'label' => 'ฝ่ายประชาสัมพันธ์'],
            ['value' => self::ROLE_VIEWER, 'label' => 'ผู้บริหาร / ผู้ดูข้อมูล'],
        ];
    }

    public static function validatePasswordStrength(string $password): void
    {
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
        }
    }

    public static function sanitize(array $user): array
    {
        unset($user['password_hash'], $user['password_salt']);
        $user['is_active'] = self::truthy($user['is_active'] ?? false);
        return $user;
    }

    public static function truthy($value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        return in_array(strtolower((string) $value), ['true', 'yes', '1', 'y'], true);
    }

    private static function guest(): array
    {
        return [
            'email' => '',
            'name' => 'ผู้เยี่ยมชม',
            'role' => self::ROLE_GUEST,
            'is_active' => false,
        ];
    }
}
