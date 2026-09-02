<?php
declare(strict_types=1);

final class Auth
{
    private const ROLES = ['admin', 'counter', 'waiter', 'kitchen'];

    public static function user(): ?array
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $login, string $password): bool
    {
        $login = mb_strtolower(trim($login));
        if ($login === '' || $password === '') {
            return false;
        }

        $pdo = Database::connection();
        self::assertNotRateLimited($pdo, $login);

        $statement = $pdo->prepare(
            'SELECT id, name, login, email, password_hash, role, active
             FROM users
             WHERE (LOWER(login) = :login_name OR LOWER(email) = :login_email)
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'login_name' => $login,
            'login_email' => $login,
        ]);
        $user = $statement->fetch();
        $valid = is_array($user) && (bool) $user['active'] && password_verify($password, (string) $user['password_hash']);

        self::recordAttempt($pdo, $login, $valid);
        if (!$valid) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'login' => (string) $user['login'],
            'email' => $user['email'],
            'role' => (string) $user['role'],
        ];
        $_SESSION['last_activity'] = time();
        $_SESSION['user_validated_at'] = time();
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
        audit_log('login', 'user', (int) $user['id']);
        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            audit_log('logout', 'user', (int) self::user()['id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            self::denyUnauthenticated();
        }

        if (time() - (int) ($_SESSION['user_validated_at'] ?? 0) >= 60) {
            $statement = Database::connection()->prepare(
                'SELECT id, name, login, email, role FROM users WHERE id = :id AND active = 1 AND deleted_at IS NULL LIMIT 1'
            );
            $statement->execute(['id' => $user['id']]);
            $freshUser = $statement->fetch();
            if (!$freshUser) {
                $_SESSION = [];
                session_regenerate_id(true);
                self::denyUnauthenticated();
            }
            $_SESSION['user'] = [
                'id' => (int) $freshUser['id'],
                'name' => (string) $freshUser['name'],
                'login' => (string) $freshUser['login'],
                'email' => $freshUser['email'],
                'role' => (string) $freshUser['role'],
            ];
            $_SESSION['user_validated_at'] = time();
            $user = $_SESSION['user'];
        }
        return $user;
    }

    public static function requireRoles(string ...$roles): array
    {
        $user = self::requireLogin();
        if (!in_array($user['role'], $roles, true)) {
            if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                json_error('Você não tem permissão para esta ação.', 403);
            }
            http_response_code(403);
            require __DIR__ . '/views/forbidden.php';
            exit;
        }
        return $user;
    }

    public static function redirectPath(string $role): string
    {
        return match ($role) {
            'admin' => '/admin/',
            'counter' => '/balcao/',
            'waiter' => '/garcom/',
            'kitchen' => '/cozinha/',
            default => '/login.php',
        };
    }

    private static function assertNotRateLimited(PDO $pdo, string $login): void
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE login = :login AND ip_address = :ip AND success = 0
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $statement->execute(['login' => $login, 'ip' => client_ip()]);
        if ((int) $statement->fetchColumn() >= 5) {
            throw new DomainException('Muitas tentativas. Aguarde 15 minutos e tente novamente.');
        }
    }

    private static function recordAttempt(PDO $pdo, string $login, bool $success): void
    {
        $pdo->prepare(
            'INSERT INTO login_attempts (login, ip_address, success, attempted_at)
             VALUES (:login, :ip, :success, NOW())'
        )->execute(['login' => $login, 'ip' => client_ip(), 'success' => $success ? 1 : 0]);
        if ($success) {
            $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)')->execute();
        }
    }

    private static function denyUnauthenticated(): never
    {
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_error('Faça login para continuar.', 401);
        }
        header('Location: /login.php');
        exit;
    }
}
