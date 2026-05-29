<?php

namespace Tests\Integration\Pages;

use Tests\Mocks\MockGitHubClient;
require_once dirname(__DIR__, 3) . '/entities/Patch.php';

class DiscoverPageTest extends PageTestCase
{
    public function testDiscoverPageBuildsSearchFormAndTerminatesWithTemplate()
    {
        $this->setUpPageRuntime(
            'discover',
            $this->createPageUser('ist9500', 'Student Discover', ROLE_STUDENT)
        );

        try {
            $this->runPage('discover');
            $this->fail('discover.php should terminate with discover template fields');
        } catch (PageTerminated $terminated) {
            $this->assertSame('discover.html.twig', $terminated->template);
            $fields = $terminated->extraFields;

            $this->assertArrayHasKey('form', $fields);
            $this->assertArrayNotHasKey('projects', $fields);
            $this->assertArrayNotHasKey('topics', $fields);
            $this->assertArrayNotHasKey('language', $fields);
            $this->assertNull($terminated->errorMessage);
        }
    }

    public function testDiscoverPageBuildsProjectsTopicsAndPatchSummary()
    {
        $oldClient = $this->mockDiscoverGitHubClient();
        $oldEntityManager = $this->mockDiscoverEntityManager([
            'github:owner/repo-a' => [
                ['status' => \PatchStatus::Merged, 'patches' => 2],
                ['status' => \PatchStatus::MergedIllegal, 'patches' => 1],
                ['status' => \PatchStatus::PROpen, 'patches' => 1],
            ],
            'github:owner/repo-b' => [],
        ]);

        try {
            $this->setUpPageRuntime(
                'discover',
                $this->createPageUser('ist9501', 'Student Search', ROLE_STUDENT),
                'GET',
                [
                    'keywords' => 'api',
                    'language' => 'PHP',
                    'newbies' => '1',
                    'search' => '',
                ]
            );

            $this->runPage('discover');
            $this->fail('discover.php should terminate with discover template fields');
        } catch (PageTerminated $terminated) {
            $this->assertSame('discover.html.twig', $terminated->template);
            $fields = $terminated->extraFields;

            $this->assertSame('PHP', $fields['language']);
            $this->assertArrayHasKey('topics', $fields);
            $topics = $fields['topics'];
            sort($topics);
            $this->assertSame(['backend', 'cli', 'php'], $topics);

            $this->assertArrayHasKey('projects', $fields);
            $this->assertCount(2, $fields['projects']);

            $projectsByName = [];
            foreach ($fields['projects'] as $project) {
                $projectsByName[$project['name']] = $project;
            }

            $this->assertSame('4 (75% merged)', $projectsByName['github:owner/repo-a']['patches']);
            $this->assertSame('none', $projectsByName['github:owner/repo-b']['patches']);
            $this->assertSame('https://github.com/owner/repo-a', $projectsByName['github:owner/repo-a']['url']);
            $this->assertSame('https://github.com/owner/repo-b', $projectsByName['github:owner/repo-b']['url']);
        } finally {
            $GLOBALS['github_client_cached'] = $oldClient;
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testDiscoverPageTerminatesWithSearchError()
    {
        $oldClient = $GLOBALS['github_client_cached'] ?? null;
        $GLOBALS['github_client_cached'] = new class {
            public function api($endpoint)
            {
                return new class {
                    public function repositories($query, $sort = null, $order = null)
                    {
                        throw new \Exception('GitHub search failed');
                    }
                };
            }
        };

        try {
            $this->setUpPageRuntime(
                'discover',
                $this->createPageUser('ist9502', 'Student Error', ROLE_STUDENT),
                'GET',
                [
                    'keywords' => 'broken',
                    'language' => 'PHP',
                    'search' => '',
                ]
            );

            try {
                $this->runPage('discover');
                $this->fail('discover.php should terminate on search error');
            } catch (PageTerminated $terminated) {
                $this->assertSame('GitHub search failed', $terminated->errorMessage);
                $this->assertSame('discover.html.twig', $terminated->template);
                $this->assertArrayHasKey('form', $terminated->extraFields);
            }
        } finally {
            $GLOBALS['github_client_cached'] = $oldClient;
        }
    }

    private function mockDiscoverGitHubClient(): mixed
    {
        $oldClient = $GLOBALS['github_client_cached'] ?? null;
        $activity = [
            'all' => array_merge(array_fill(0, 48, 0), [10, 10, 10, 10]),
            'owner' => array_fill(0, 52, 0),
        ];
        $GLOBALS['github_client_cached'] = new MockGitHubClient(
            searchResults: ['items' => [
                [
                    'full_name' => 'owner/repo-a',
                    'description' => 'First mock repository',
                    'html_url' => 'https://github.com/owner/repo-a',
                    'stargazers_count' => 1200,
                    'open_issues' => 42,
                    'language' => 'PHP',
                    'topics' => ['php', 'backend'],
                    'pushed_at' => '2026-04-26T10:00:00+00:00',
                ],
                [
                    'full_name' => 'owner/repo-b',
                    'description' => 'Second mock repository',
                    'html_url' => 'https://github.com/owner/repo-b',
                    'stargazers_count' => 800,
                    'open_issues' => 8,
                    'language' => 'PHP',
                    'topics' => ['cli', 'php'],
                    'pushed_at' => '2026-04-27T11:00:00+00:00',
                ],
            ]],
            languagesByRepository: [
                'owner/repo-a' => ['PHP' => 4_800_000],
                'owner/repo-b' => ['PHP' => 4_800_000],
            ],
            participationByRepository: [
                'owner/repo-a' => $activity,
                'owner/repo-b' => $activity,
            ]
        );

        return $oldClient;
    }

    private function mockDiscoverEntityManager(array $patchStatsByRepo): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($patchStatsByRepo) {
            public function __construct(private array $patchStatsByRepo) {}

            public function createQueryBuilder(): object
            {
                return new class($this->patchStatsByRepo) {
                    private ?string $repo = null;

                    public function __construct(private array $patchStatsByRepo) {}
                    public function from($entity, $alias): self { return $this; }
                    public function select($select): self { return $this; }
                    public function where($where): self { return $this; }
                    public function join($join, $alias): self { return $this; }
                    public function groupBy($groupBy): self { return $this; }
                    public function setParameter($name, $value): self
                    {
                        if ($name === 'repo') {
                            $this->repo = $value;
                        }
                        return $this;
                    }
                    public function getQuery(): self { return $this; }
                    public function getArrayResult(): array
                    {
                        return $this->patchStatsByRepo[$this->repo] ?? [];
                    }
                };
            }
        };

        return $oldEntityManager;
    }
}
