<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;

use GitHub\GitHubUser;


require_once dirname(__DIR__, 3) . '/entities/RepositoryUser.php';

class GitHubUserTest extends UnitTestCase
{
    public function testProcessEventsCreatesPullRequestEvents()
    {
        $user = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $events = [];

        $keepGoing = GitHubUser::processEvents($events, $user, [[
            'id' => '100',
            'created_at' => '2026-04-26T10:00:00+00:00',
            'type' => 'PullRequestEvent',
            'repo' => ['name' => 'owner/repo'],
            'payload' => [
                'action' => 'opened',
                'number' => 123,
            ],
        ]]);

        $this->assertTrue($keepGoing);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\PROpenedEvent::class, $events[0]);
        $this->assertEquals('GitHub PR owner/repo#123', (string)$events[0]->pr);
    }

    public function testProcessEventsCreatesReopenedPullRequestEvents()
    {
        $user = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $events = [];

        GitHubUser::processEvents($events, $user, [[
            'id' => '101',
            'created_at' => '2026-04-26T10:00:00+00:00',
            'type' => 'PullRequestEvent',
            'repo' => ['name' => 'owner/repo'],
            'payload' => [
                'action' => 'reopened',
                'number' => 124,
            ],
        ]]);

        $this->assertCount(1, $events);
        $this->assertEquals('GitHub PR owner/repo#124', (string)$events[0]->pr);
    }

    public function testProcessEventsIgnoresNonOpeningEvents()
    {
        $user = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $events = [];

        $keepGoing = GitHubUser::processEvents($events, $user, [
            [
                'id' => '102',
                'created_at' => '2026-04-26T10:00:00+00:00',
                'type' => 'PullRequestEvent',
                'repo' => ['name' => 'owner/repo'],
                'payload' => [
                    'action' => 'closed',
                    'number' => 125,
                ],
            ],
            [
                'id' => '103',
                'created_at' => '2026-04-26T10:00:00+00:00',
                'type' => 'IssuesEvent',
                'repo' => ['name' => 'owner/repo'],
                'payload' => [
                    'action' => 'opened',
                ],
            ],
        ]);

        $this->assertTrue($keepGoing);
        $this->assertSame([], $events);
    }

    public function testProcessEventsDoesNotFilterByProcessedIdWhileGithubEventsAreIncomplete()
    {
        $user = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $user->repository_last_processed_id = '200';
        $events = [];

        $keepGoing = GitHubUser::processEvents($events, $user, [[
            'id' => '199',
            'created_at' => '2026-04-26T10:00:00+00:00',
            'type' => 'PullRequestEvent',
            'repo' => ['name' => 'owner/repo'],
            'payload' => [
                'action' => 'opened',
                'number' => 123,
            ],
        ]]);

        $this->assertTrue($keepGoing);
        $this->assertCount(1, $events);
        $this->assertEquals('GitHub PR owner/repo#123', (string)$events[0]->pr);
    }

    public function testStatsReturnsEmptyArrayWhenGithubErrors()
    {
        $this->replaceGitHubClient(new class {
            public function api($endpoint) {
                return new class {
                    public function show($username) {
                        throw new \Github\Exception\RuntimeException('temporary');
                    }
                };
            }
        });
        $user = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $user->repository_user = 'github:student';

        $this->assertNull(GitHubUser::name(new \RepositoryUser($user)));
    }

    public function testIsValidReturnsFalseForNotFound()
    {
        $this->replaceGitHubClient(new class {
            public function api($endpoint) {
                return new class {
                    public function show($username) {
                        throw new \Github\Exception\RuntimeException('Not Found');
                    }
                };
            }
        });
        $user = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $user->repository_user = 'github:student';

        $this->assertFalse(GitHubUser::isValid(new \RepositoryUser($user)));
    }

    public function testGetUnprocessedEventsFetchesPagesAndUpdatesUserState()
    {
        $this->ensureGitHubEtagHelpers();
        $this->replaceGitHubClient($this->mockPaginatedEventsGitHubClient());

        $user = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $user->repository_user = 'github:student';
        $user->repository_last_processed_id = '100';
        $repoUser = new \RepositoryUser($user);

        $events = GitHubUser::getUnprocessedEvents($repoUser);

        $this->assertCount(2, $events);
        $this->assertEquals('GitHub PR owner/repo#2', (string)$events[0]->pr);
        $this->assertEquals('GitHub PR owner/repo#1', (string)$events[1]->pr);
        $this->assertEquals('"etag-1"', $user->repository_etag);
        $this->assertEquals(300, $user->repository_last_processed_id);
    }

    public function testGetUnprocessedEventsKeepsLastProcessedIdWhenNoEvents()
    {
        $this->ensureGitHubEtagHelpers();
        $this->replaceGitHubClient($this->mockPaginatedEventsGitHubClient([]));

        $user = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $user->repository_user = 'github:student';
        $user->repository_last_processed_id = '100';
        $repoUser = new \RepositoryUser($user);

        $events = GitHubUser::getUnprocessedEvents($repoUser);

        $this->assertSame([], $events);
        $this->assertEquals('"etag-1"', $user->repository_etag);
        $this->assertEquals('100', $user->repository_last_processed_id);
    }

    private function ensureGitHubEtagHelpers(): void
    {
        if (!function_exists('\\GitHub\\github_remove_etag')) {
            eval('namespace GitHub; function github_set_etag($etag) {} function github_remove_etag() {}');
        }
    }

    private function mockPaginatedEventsGitHubClient(?array $firstPage = null): \Github\Client
    {
        $firstPage ??= [
            [
                'id' => '300',
                'created_at' => '2026-04-26T11:00:00+00:00',
                'type' => 'PullRequestEvent',
                'repo' => ['name' => 'owner/repo'],
                'payload' => [
                    'action' => 'opened',
                    'number' => 1,
                ],
            ],
        ];

        $nextPage = [
            [
                'id' => '250',
                'created_at' => '2026-04-26T10:00:00+00:00',
                'type' => 'PullRequestEvent',
                'repo' => ['name' => 'owner/repo'],
                'payload' => [
                    'action' => 'reopened',
                    'number' => 2,
                ],
            ],
        ];

        return new class($firstPage, $nextPage) extends \Github\Client {
            private \Psr\Http\Message\ResponseInterface $lastResponse;

            public function __construct(private array $firstPage, private array $nextPage)
            {
                parent::__construct();
                $this->lastResponse = $this->jsonResponse([], ['etag' => '"etag-0"']);
            }

            public function user($type): \Github\Api\AbstractApi
            {
                return new class($this) extends \Github\Api\AbstractApi {
                    public function __construct(private object $client)
                    {
                        parent::__construct($client);
                    }

                    public function events($username): array
                    {
                        $headers = ['etag' => '"etag-1"'];
                        if ($this->client->hasNextPage()) {
                            $headers['Link'] = '<https://api.github.test/events?page=2>; rel="next"';
                        }
                        $this->client->setLastResponse($this->client->jsonResponse($this->client->firstPage(), $headers));

                        return $this->client->firstPage();
                    }
                };
            }

            public function getLastResponse(): ?\Psr\Http\Message\ResponseInterface
            {
                return $this->lastResponse;
            }

            public function getHttpClient(): \Http\Client\Common\HttpMethodsClientInterface
            {
                return new class($this) implements \Http\Client\Common\HttpMethodsClientInterface {
                    public function __construct(private object $client) {}

                    public function get($uri, array $headers = []): \Psr\Http\Message\ResponseInterface
                    {
                        $response = $this->client->jsonResponse($this->client->nextPage(), ['etag' => '"etag-2"']);
                        $this->client->setLastResponse($response);
                        return $response;
                    }

                    public function head($uri, array $headers = []): \Psr\Http\Message\ResponseInterface { return $this->get($uri, $headers); }
                    public function trace($uri, array $headers = []): \Psr\Http\Message\ResponseInterface { return $this->get($uri, $headers); }
                    public function post($uri, array $headers = [], $body = null): \Psr\Http\Message\ResponseInterface { return $this->get($uri, $headers); }
                    public function put($uri, array $headers = [], $body = null): \Psr\Http\Message\ResponseInterface { return $this->get($uri, $headers); }
                    public function patch($uri, array $headers = [], $body = null): \Psr\Http\Message\ResponseInterface { return $this->get($uri, $headers); }
                    public function delete($uri, array $headers = [], $body = null): \Psr\Http\Message\ResponseInterface { return $this->get($uri, $headers); }
                    public function options($uri, array $headers = [], $body = null): \Psr\Http\Message\ResponseInterface { return $this->get($uri, $headers); }
                    public function send(string $method, $uri, array $headers = [], $body = null): \Psr\Http\Message\ResponseInterface { return $this->get($uri, $headers); }
                    public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
                    {
                        return $this->get((string)$request->getUri());
                    }
                };
            }

            public function firstPage(): array
            {
                return $this->firstPage;
            }

            public function nextPage(): array
            {
                return $this->nextPage;
            }

            public function hasNextPage(): bool
            {
                return $this->firstPage !== [];
            }

            public function setLastResponse(\Psr\Http\Message\ResponseInterface $response): void
            {
                $this->lastResponse = $response;
            }

            public function jsonResponse(array $data, array $headers): \Psr\Http\Message\ResponseInterface
            {
                return new \Nyholm\Psr7\Response(
                    200,
                    ['Content-Type' => 'application/json'] + $headers,
                    json_encode($data)
                );
            }
        };
    }
}
