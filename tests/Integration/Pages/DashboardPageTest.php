<?php

namespace Tests\Integration\Pages;




require_once dirname(__DIR__, 3) . '/entities/Patch.php';

class DashboardPageTest extends PageTestCase
{
    public function testDashboardBuildsPlotsAndProjectTable()
    {
        $oldClient = $this->mockCachedGitHubClientForDashboard();
        $oldEntityManager = $this->mockDashboardEntityManager(
            mergedPatchStats: [
                [
                    'year' => 2023,
                    'patches' => 10,
                    'lines_added' => 1_200,
                    'lines_deleted' => 300,
                    'files_modified' => 45,
                ],
                [
                    'year' => 2024,
                    'patches' => 20,
                    'lines_added' => 2_000,
                    'lines_deleted' => 500,
                    'files_modified' => 60,
                ],
            ],
            patchStats: [
                [
                    'year' => 2023,
                    'status' => \PatchStatus::Merged,
                    'type' => \PatchType::BugFix,
                    'count' => 3,
                ],
                [
                    'year' => 2023,
                    'status' => \PatchStatus::PROpen,
                    'type' => \PatchType::BugFix,
                    'count' => 1,
                ],
                [
                    'year' => 2024,
                    'status' => \PatchStatus::MergedIllegal,
                    'type' => \PatchType::Feature,
                    'count' => 2,
                ],
                [
                    'year' => 2024,
                    'status' => \PatchStatus::NotMerged,
                    'type' => \PatchType::Feature,
                    'count' => 2,
                ],
                [
                    'year' => 2024,
                    'status' => \PatchStatus::WaitingReview,
                    'type' => \PatchType::BugFix,
                    'count' => 99,
                ],
            ],
            groups: $this->createDashboardGroups(),
            prStats: [
                [
                    'status' => \PatchStatus::Merged,
                    'type' => \PatchType::BugFix,
                    'repository' => 'github:owner/repo-a',
                    'count' => 3,
                ],
                [
                    'status' => \PatchStatus::PROpen,
                    'type' => \PatchType::BugFix,
                    'repository' => 'github:owner/repo-a',
                    'count' => 1,
                ],
                [
                    'status' => \PatchStatus::MergedIllegal,
                    'type' => \PatchType::Feature,
                    'repository' => 'github:owner/repo-a',
                    'count' => 2,
                ],
                [
                    'status' => \PatchStatus::NotMerged,
                    'type' => \PatchType::Feature,
                    'repository' => 'github:owner/repo-a',
                    'count' => 2,
                ],
                [
                    'status' => \PatchStatus::Merged,
                    'type' => \PatchType::BugFix,
                    'repository' => 'github:owner/repo-b',
                    'count' => 1,
                ],
            ]
        );

        $GLOBALS['__page_test_filter_result'] = 2024;
        $this->setUpPageRuntime(
            'dashboard',
            $this->createPageUser('ist8000', 'Professor Dashboard', ROLE_PROF)
        );

        try {
            $this->runPage('dashboard');
            $this->fail('dashboard.php should terminate with rendered fields');
        } catch (PageTerminated $terminated) {
            $this->assertSame('dashboard.html.twig', $terminated->template);
            $fields = $terminated->extraFields;

            $this->assertSame(['2023/2024', '2024/2025'], $fields['merged_prs_years']);
            $this->assertSame([10, 20], $fields['merged_prs_patches']);
            $this->assertSame([1200, 2000], $fields['merged_prs_lines_added']);
            $this->assertSame([300, 500], $fields['merged_prs_lines_deleted']);
            $this->assertSame([45, 60], $fields['merged_prs_files_modified']);
            $this->assertSame(22.0, $fields['max_y']);
            $this->assertSame(2200.0, $fields['max_y2']);
            $this->assertSame("3\u{202F}k", $fields['total_lines_code']);

            $this->assertSame('5', (string)$fields['total_merged_prs']);
            $this->assertSame(['2023/2024', '2024/2025'], $fields['pcmerged_x']);
            $this->assertSame([0.75], $fields['pcmerged_bug']);
            $this->assertSame([0.5], $fields['pcmerged_feat']);

            $this->assertSame(['PHP', 'Rust'], $fields['lang_x']);
            $this->assertSame([1, 1], array_values($fields['lang_y']));
            $this->assertSame(['owner/repo-a', 'owner/repo-b'], $fields['proj_x']);
            $this->assertSame([1, 1], array_values($fields['proj_y']));

            $this->assertCount(2, $fields['all_projects']);
            $this->assertSame(
                [
                    'id' => 0,
                    'name' => 'owner/repo-a',
                    'total_bugs' => 4,
                    'total_feat' => 4,
                    'bugs' => 3,
                    'feat' => 2,
                    'bugs_pc' => 75.0,
                    'feat_pc' => 50.0,
                    'url' => 'https://github.com/owner/repo-a',
                ],
                $fields['all_projects'][0]
            );
            $this->assertSame(
                [
                    'id' => 1,
                    'name' => 'owner/repo-b',
                    'total_bugs' => 1,
                    'total_feat' => 0,
                    'bugs' => 1,
                    'feat' => 0,
                    'bugs_pc' => 100.0,
                    'feat_pc' => 0,
                    'url' => 'https://github.com/owner/repo-b',
                ],
                $fields['all_projects'][1]
            );
        } finally {
            $GLOBALS['github_client_cached'] = $oldClient;
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function createDashboardGroups(): array
    {
        $shift = new \Shift('T1', 2024);

        $groupA = new \ProjGroup(1, 2024, $shift);
        $groupA->id = 100;
        $groupA->repository = 'github:owner/repo-a';

        $groupB = new \ProjGroup(2, 2024, $shift);
        $groupB->id = 200;
        $groupB->repository = 'github:owner/repo-b';

        return [$groupA, $groupB];
    }

    private function mockDashboardEntityManager(
        array $mergedPatchStats,
        array $patchStats,
        array $groups,
        array $prStats
    ): mixed {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($mergedPatchStats, $patchStats, $groups, $prStats) {
            public function __construct(
                private array $mergedPatchStats,
                private array $patchStats,
                private array $groups,
                private array $prStats
            ) {}

            public function createQueryBuilder(): object
            {
                static $call = 0;
                $datasets = [
                    $this->mergedPatchStats,
                    $this->patchStats,
                    $this->prStats,
                ];

                return new class($datasets[$call++] ?? []) {
                    public function __construct(private array $result) {}
                    public function from($entity, $alias): self { return $this; }
                    public function where($where): self { return $this; }
                    public function select($select): self { return $this; }
                    public function join($join, $alias): self { return $this; }
                    public function groupBy($groupBy): self { return $this; }
                    public function orderBy($orderBy, $direction = null): self { return $this; }
                    public function setParameter($name, $value): self { return $this; }
                    public function getQuery(): self { return $this; }
                    public function getArrayResult(): array { return $this->result; }
                };
            }

            public function getRepository(string $entity): object
            {
                if ($entity === 'ProjGroup') {
                    return new class($this->groups) {
                        public function __construct(private array $groups) {}
                        public function findByYear($year, $order): array
                        {
                            return $this->groups;
                        }
                    };
                }

                throw new \RuntimeException("Unexpected repository lookup: $entity");
            }
        };

        return $oldEntityManager;
    }

    private function mockCachedGitHubClientForDashboard(): mixed
    {
        $oldClient = $GLOBALS['github_client_cached'] ?? null;
        $GLOBALS['github_client_cached'] = new class {
            public function api($endpoint)
            {
                return new class {
                    public function show($owner, $repo)
                    {
                        $language = $repo === 'repo-a' ? 'PHP' : 'Rust';
                        return [
                            'full_name' => "$owner/$repo",
                            'default_branch' => 'main',
                            'language' => $language,
                            'license' => ['name' => 'MIT'],
                            'stargazers_count' => 123,
                            'topics' => ['testing'],
                        ];
                    }

                    public function languages($owner, $repo)
                    {
                        return ['PHP' => 4_000];
                    }

                    public function participation($owner, $repo)
                    {
                        return ['all' => array_fill(0, 52, 1)];
                    }
                };
            }
        };

        return $oldClient;
    }
}
