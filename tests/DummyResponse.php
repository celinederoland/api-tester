<?php

namespace Cdr\ApiTester\Tests;

use Symfony\Contracts\HttpClient\ResponseInterface;

class DummyResponse implements ResponseInterface
{

    public function __construct(
        private readonly int    $statusCode = 500,
        private readonly ?array $content = ['foo' => 'bar'],
    )
    {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(bool $throw = true): array
    {
        return [];
    }

    public function getContent(bool $throw = true): string
    {
        return json_encode($this->content);
    }

    public function toArray(bool $throw = true): array
    {
        return [];
    }

    public function cancel(): void
    {
    }

    public function getInfo(string $type = null): mixed
    {
        return null;
    }
}