<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;
    private static int $savepointCounter = 0;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configFile = __DIR__ . '/../config/database.php';
        if (!is_file($configFile)) {
            throw new RuntimeException('O sistema ainda não foi instalado.');
        }

        /** @var array<string, mixed> $config */
        $config = require $configFile;
        foreach (['host', 'port', 'database', 'username', 'password', 'charset'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException('Configuração de banco incompleta.');
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        return self::$connection;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $startedHere = !$pdo->inTransaction();
        $savepoint = null;

        if ($startedHere) {
            $pdo->beginTransaction();
        } else {
            $savepoint = 'pdv_savepoint_' . (++self::$savepointCounter);
            $pdo->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            $result = $callback($pdo);
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->commit();
            } elseif ($savepoint !== null && $pdo->inTransaction()) {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $exception) {
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->rollBack();
            } elseif ($savepoint !== null && $pdo->inTransaction()) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            throw $exception;
        }
    }
}
