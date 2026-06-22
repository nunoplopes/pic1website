<?php

namespace Tests\Unit\Base;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case class for unit tests
 * 
 * Provides common setup and teardown logic
 */
abstract class UnitTestCase extends BaseTestCase
{
    private array $originalGitHubClients = [];

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach ($this->originalGitHubClients as $name => $original) {
            if ($original['exists']) {
                $GLOBALS[$name] = $original['value'];
            } else {
                unset($GLOBALS[$name]);
            }
        }
        $this->originalGitHubClients = [];

        parent::tearDown();
    }

    protected function replaceGitHubClient(object $client): object
    {
        return $this->replaceGitHubGlobal('github_client', $client);
    }

    protected function replaceCachedGitHubClient(object $client): object
    {
        return $this->replaceGitHubGlobal('github_client_cached', $client);
    }

    private function replaceGitHubGlobal(string $name, object $client): object
    {
        if (!array_key_exists($name, $this->originalGitHubClients)) {
            $this->originalGitHubClients[$name] = [
                'exists' => array_key_exists($name, $GLOBALS),
                'value' => $GLOBALS[$name] ?? null,
            ];
        }

        $GLOBALS[$name] = $client;

        return $client;
    }
}
