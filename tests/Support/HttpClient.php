<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Support;

use RuntimeException;

/**
 * cookie を保持する最小の HTTP クライアント（リダイレクトは追わない）。
 */
final class HttpClient
{
    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(private readonly string $baseUrl)
    {
    }

    public function get(string $path): HttpResponse
    {
        return $this->request('GET', $path, null);
    }

    /** @param array<string, string|list<string>> $fields */
    public function post(string $path, array $fields): HttpResponse
    {
        return $this->request('POST', $path, http_build_query($fields));
    }

    /** @return array<string, string> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /** @param array<string, string> $cookies */
    public function setCookies(array $cookies): void
    {
        $this->cookies = $cookies;
    }

    private function request(string $method, string $path, ?string $body): HttpResponse
    {
        $headers = [];
        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = "{$name}={$value}";
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }
        if ($body !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $context = stream_context_create(['http' => [
            'method'          => $method,
            'header'          => implode("\r\n", $headers),
            'content'         => $body ?? '',
            'follow_location' => 0,
            'ignore_errors'   => true,
            'timeout'         => 10,
        ]]);
        $responseBody = @file_get_contents($this->baseUrl . '/' . ltrim($path, '/'), false, $context);
        if ($responseBody === false || !isset($http_response_header)) {
            throw new RuntimeException("{$method} {$path} に接続できませんでした");
        }
        $response = HttpResponse::fromRaw($http_response_header, $responseBody);
        $this->storeCookies($response);
        return $response;
    }

    private function storeCookies(HttpResponse $response): void
    {
        foreach ($response->headers('set-cookie') as $line) {
            [$pair] = explode(';', $line, 2);
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if ($value === '' || $value === 'deleted') {
                unset($this->cookies[$name]);
                continue;
            }
            $this->cookies[$name] = $value;
        }
    }
}
