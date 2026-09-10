<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel's Sanctum "sanctum" guard caches the resolved user on the
     * guard instance for its lifetime (RequestGuard::user()). In a real
     * deployment that's harmless — every HTTP request gets a fresh
     * process/container — but within a single test method the app (and
     * therefore the guard) persists across every simulated request, so
     * switching Bearer tokens mid-test would otherwise keep returning the
     * first user. Forgetting the guard forces it to re-resolve from the
     * new request's token.
     */
    protected function asBearerToken(string $token): PendingRequestWithForgottenGuards
    {
        Auth::forgetGuards();

        return new PendingRequestWithForgottenGuards($this, $token);
    }
}

/**
 * Tiny fluent shim so tests can write
 * $this->asBearerToken($token)->getJson(...) instead of repeating the
 * forgetGuards()+withHeader() boilerplate everywhere.
 */
class PendingRequestWithForgottenGuards
{
    public function __construct(
        private readonly BaseTestCase $test,
        private readonly string $token,
    ) {}

    public function __call(string $method, array $arguments): TestResponse
    {
        return $this->test
            ->withHeader('Authorization', "Bearer {$this->token}")
            ->{$method}(...$arguments);
    }
}
