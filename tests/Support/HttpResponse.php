<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Support;

final class HttpResponse
{
    /** @param array<string, list<string>> $headers */
    private function __construct(
        public readonly int $status,
        private readonly array $headers,
        public readonly string $body,
    ) {
    }

    /** @param list<string> $rawHeaders */
    public static function fromRaw(array $rawHeaders, string $body): self
    {
        $status = 0;
        $headers = [];
        foreach ($rawHeaders as $line) {
            if (preg_match('#\AHTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                $headers = [];
                continue;
            }
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))][] = trim($value);
        }
        return new self($status, $headers, $body);
    }

    /** @return list<string> */
    public function headers(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function header(string $name): ?string
    {
        return $this->headers($name)[0] ?? null;
    }

    public function csrfToken(): string
    {
        if (preg_match('/name="csrf_token" value="([0-9a-f]{64})"/', $this->body, $m) !== 1) {
            throw new \RuntimeException('csrf_token が見つかりません');
        }
        return $m[1];
    }
}
