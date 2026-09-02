<?php
declare(strict_types=1);

$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set((string) $appConfig['timezone']);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) $appConfig['session_name']);
    session_set_cookie_params([
        'lifetime' => (int) $appConfig['session_lifetime'],
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > (int) $appConfig['session_lifetime']) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/Auth.php';

spl_autoload_register(static function (string $class): void {
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $class)) {
        return;
    }
    foreach ([__DIR__ . '/services/' . $class . '.php', __DIR__ . '/' . $class . '.php'] as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

set_exception_handler(static function (Throwable $exception): void {
    error_log($exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
    if ($isApi) {
        $status = $exception instanceof DomainException ? 422 : 500;
        $message = $exception instanceof DomainException ? $exception->getMessage() : 'Não foi possível concluir a operação. Tente novamente.';
        json_error($message, $status);
    }
    http_response_code($exception instanceof DomainException ? 422 : 500);
    $friendlyMessage = $exception instanceof DomainException
        ? $exception->getMessage()
        : 'Ocorreu um erro inesperado. Tente novamente.';
    require __DIR__ . '/views/error.php';
    exit;
});
