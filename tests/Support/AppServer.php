<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Support;

use RuntimeException;

/**
 * テスト用に `php -S` を空きポートで起動する（接続先 DB は環境変数で playduo_test に固定）。
 */
final class AppServer
{
    private const STARTUP_TIMEOUT_SECONDS = 5;
    private const POLL_INTERVAL_MICROSECONDS = 50_000;

    /** @var resource|null */
    private static $process = null;
    private static ?string $baseUrl = null;

    public static function baseUrl(): string
    {
        if (self::$baseUrl === null) {
            self::start();
        }
        return (string) self::$baseUrl;
    }

    private static function start(): void
    {
        $port = self::freePort();
        $env = [
            'PATH'                => (string) getenv('PATH'),
            'PLAYDUO_DB_HOST'     => (string) getenv('PLAYDUO_DB_HOST'),
            'PLAYDUO_DB_NAME'     => TestDb::NAME,
            'PLAYDUO_DB_USER'     => (string) getenv('PLAYDUO_DB_USER'),
            'PLAYDUO_DB_PASSWORD' => (string) getenv('PLAYDUO_DB_PASSWORD'),
        ];
        $docRoot = dirname(__DIR__, 2);
        $logFile = sys_get_temp_dir() . '/playduo-test-server.log';
        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $docRoot],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']],
            $pipes,
            $docRoot,
            $env,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('php -S を起動できませんでした');
        }
        self::$process = $process;
        register_shutdown_function([self::class, 'stop']);
        self::waitUntilReady($port);
        self::$baseUrl = "http://127.0.0.1:{$port}";
    }

    public static function stop(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
        self::$process = null;
        self::$baseUrl = null;
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException("空きポートを取得できませんでした: {$errstr}");
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function waitUntilReady(int $port): void
    {
        $deadline = microtime(true) + self::STARTUP_TIMEOUT_SECONDS;
        while (microtime(true) < $deadline) {
            $conn = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 0.2);
            if ($conn !== false) {
                fclose($conn);
                return;
            }
            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }
        self::stop();
        throw new RuntimeException('php -S が時間内に起動しませんでした');
    }
}
