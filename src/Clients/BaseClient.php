<?php

declare(strict_types=1);

namespace Bsa\Core\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class BaseClient
{
    protected const RETRY_TIME_IN_MILLISECONDS = 200;

    public function __construct(protected string $baseUrl) {}

    public function setBaseUrl(string $url): void
    {
        $this->baseUrl = $url;
    }

    protected function getHttpInstance(array $headers = []): PendingRequest
    {
        $instance = Http::baseUrl($this->baseUrl)
            ->withHeaders($headers)
            ->acceptJson()
            ->throw()
            ->retry(
                3,
                self::RETRY_TIME_IN_MILLISECONDS,
                fn ($exception) => $exception instanceof ConnectionException
            );

        // In a local environment, ignore SSL
        if (in_array(config('app.env'), ['local', 'testing'])) {
            $instance->withoutVerifying();
        }

        return $instance;
    }

    abstract protected function getClient(): PendingRequest;
}
