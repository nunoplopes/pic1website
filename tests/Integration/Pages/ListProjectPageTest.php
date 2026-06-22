<?php

namespace Tests\Integration\Pages;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;


class ListProjectPageTest extends PageTestCase
{
    public function testListProjectRequiresId()
    {
        $result = $this->runListProjectScript([]);

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('Missing id', $result['output']);
    }

    public function testListProjectRejectsGroupWithoutPermission()
    {
        $result = $this->runListProjectScript(['id' => 400], true);

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('Permission error', $result['output']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStudentListProjectShowsTopBoxAndEditableRepositoryForm()
    {
        $prof = $this->createPageUser('ist5000', 'Professor Example', ROLE_PROF);
        $student = $this->createPageUser('ist5001', 'Alice Student', ROLE_STUDENT);
        $student->repository_user = 'github:alice';

        $group = $this->createGroup(5, 2025, $prof, [$student]);
        $deadline = new \Deadline(2025);
        $deadline->proj_proposal = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockListProjectEntityManager($group, $deadline);

        try {
            $this->setUpPageRuntime('listproject', $student, 'GET', ['id' => $group->id]);
            $this->runPage('listproject');

            $this->assertNotNull($GLOBALS['top_box']);
            $this->assertCount(1, $GLOBALS['top_box']['Students']);
            $this->assertSame(
                'https://fenix.tecnico.ulisboa.pt/user/photo/ist5001',
                $GLOBALS['top_box']['Students'][0][0]['data']
            );
            $this->assertSame('Alice Student (ist5001)', $GLOBALS['top_box']['Students'][0][1]);
            $this->assertSame('ist5001@example.com', $GLOBALS['top_box']['Students'][0][2]['data']);
            $this->assertSame(
                'github:alice',
                $GLOBALS['top_box']['Students'][0][3]['label']
            );
            $this->assertSame(
                'https://github.com/alice',
                $GLOBALS['top_box']['Students'][0][3]['url']
            );
            $this->assertStringContainsString('[name: Alice User]', $GLOBALS['top_box']['Students'][0][4]);
            $this->assertStringContainsString('[email: alice@example.com]', $GLOBALS['top_box']['Students'][0][4]);

            $this->assertCount(1, $GLOBALS['top_box']['Professor']);
            $this->assertSame('Professor Example', $GLOBALS['top_box']['Professor'][0][1]);
            $this->assertSame('ist5000@example.com', $GLOBALS['top_box']['Professor'][0][2]['data']);

            $this->assertSame(
                'You can submit this form multiple times until the deadline. Only the last submission will be considered.',
                $GLOBALS['info_message']
            );
            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->has('repository'));
            $this->assertFalse($GLOBALS['form']->get('repository')->getConfig()->getOption('disabled'));
            $this->assertFalse($GLOBALS['form']->get('submit')->getConfig()->getOption('disabled'));
            $this->assertNull($GLOBALS['bottom_links']);

            $this->assertCount(1, $GLOBALS['__page_test_eval_boxes']);
            $this->assertSame('project', $GLOBALS['__page_test_eval_boxes'][0]['page']);
            $this->assertSame($group, $GLOBALS['__page_test_eval_boxes'][0]['group']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testListProjectShowsInvalidRepositoryUser()
    {
        $oldClient = $GLOBALS['github_client'] ?? null;
        $GLOBALS['github_client'] = new class {
            public function api($endpoint)
            {
                return new class {
                    public function show($username)
                    {
                        throw new \Github\Exception\RuntimeException('Not Found');
                    }
                };
            }
        };

        $student = $this->createPageUser('ist5500', 'Invalid Repo User', ROLE_STUDENT);
        $student->repository_user = 'github:missing';
        $group = $this->createGroup(55, 2025, null, [$student]);
        $deadline = new \Deadline(2025);
        $deadline->proj_proposal = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockListProjectEntityManager($group, $deadline);

        try {
            $this->setUpPageRuntime('listproject', $student, 'GET', ['id' => $group->id]);
            $this->runPage('listproject');

            $this->assertSame(
                'Invalid Repository User',
                $GLOBALS['top_box']['Students'][0][3]
            );
        } finally {
            $GLOBALS['github_client'] = $oldClient;
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTaListProjectAddsBottomLinks()
    {
        $ta = $this->createPageUser('ist6000', 'Taylor Assistant', ROLE_TA);
        $prof = $this->createPageUser('ist6001', 'Professor Example', ROLE_PROF);
        $group = $this->createGroup(6, 2025, $ta, [$this->createPageUser('ist6002', 'Sam Student', ROLE_STUDENT)]);
        $group->shift->prof = $ta;

        $deadline = new \Deadline(2025);
        $deadline->proj_proposal = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockListProjectEntityManager($group, $deadline);

        try {
            $this->setUpPageRuntime('listproject', $ta, 'GET', ['id' => $group->id]);
            $this->runPage('listproject');

            $this->assertCount(5, $GLOBALS['bottom_links']);
            $this->assertSame('Grades', $GLOBALS['bottom_links'][0]['label']);
            $this->assertSame(
                'index.php?group=' . $group->id . '&year=2025&all_shifts=1&page=grades',
                $GLOBALS['bottom_links'][0]['url']
            );
            $this->assertSame('Patches', $GLOBALS['bottom_links'][1]['label']);
            $this->assertSame('Bugs', $GLOBALS['bottom_links'][2]['label']);
            $this->assertSame('Feature', $GLOBALS['bottom_links'][3]['label']);
            $this->assertSame('Final Report', $GLOBALS['bottom_links'][4]['label']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testListProjectShowsRepositoryInfoBoxWithThresholdWarnings()
    {
        $oldClient = $this->mockCachedGitHubClientForListProject(
            linesOfCode: 80_000,
            commitsLastMonth: 60,
            stars: 250,
            topics: ['php', 'education']
        );

        $student = $this->createPageUser('ist6500', 'Repo Student', ROLE_STUDENT);
        $group = $this->createGroup(65, 2025, null, [$student]);
        $group->repository = 'github:owner/repo';

        $deadline = new \Deadline(2025);
        $deadline->proj_proposal = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockListProjectEntityManager($group, $deadline);

        try {
            $this->setUpPageRuntime('listproject', $student, 'GET', ['id' => $group->id]);
            $this->runPage('listproject');

            $this->assertSame('Repository data', $GLOBALS['info_box']['title']);
            $this->assertSame('PHP', $GLOBALS['info_box']['rows']['Main language']);
            $this->assertSame(
                ['data' => "80\u{202F}k", 'warn' => true],
                $GLOBALS['info_box']['rows']['Lines of Code']
            );
            $this->assertSame(60, $GLOBALS['info_box']['rows']['Num of commits in the past month']);
            $this->assertSame('250', (string)$GLOBALS['info_box']['rows']['Stars']);
            $this->assertSame('MIT', $GLOBALS['info_box']['rows']['License']);
            $this->assertSame('php, education', $GLOBALS['info_box']['rows']['Topics']);
        } finally {
            $GLOBALS['github_client_cached'] = $oldClient;
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testListProjectShowsUnavailableRepositoryError()
    {
        $oldClient = $this->mockCachedGitHubClientForListProject(isValid: false);

        $student = $this->createPageUser('ist6600', 'Broken Repo Student', ROLE_STUDENT);
        $group = $this->createGroup(66, 2025, null, [$student]);
        $group->repository = 'github:owner/missing';

        $deadline = new \Deadline(2025);
        $deadline->proj_proposal = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockListProjectEntityManager($group, $deadline);

        try {
            $this->setUpPageRuntime('listproject', $student, 'GET', ['id' => $group->id]);
            $this->runPage('listproject');

            $this->assertSame('Repository data', $GLOBALS['info_box']['title']);
            $this->assertSame(
                'The repository is no longer available!',
                $GLOBALS['info_box']['rows']['Error']
            );
        } finally {
            $GLOBALS['github_client_cached'] = $oldClient;
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStudentListProjectMakesRepositoryReadOnlyAfterDeadline()
    {
        $student = $this->createPageUser('ist7000', 'Late Student', ROLE_STUDENT);
        $group = $this->createGroup(7, 2025, null, [$student]);
        $group->allow_modifications_date = new \DateTimeImmutable('-2 days');

        $deadline = new \Deadline(2025);
        $deadline->proj_proposal = new \DateTimeImmutable('-1 day');

        $oldEntityManager = $this->mockListProjectEntityManager($group, $deadline);

        try {
            $this->setUpPageRuntime('listproject', $student, 'GET', ['id' => $group->id]);
            $this->runPage('listproject');

            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->get('repository')->getConfig()->getOption('disabled'));
            $this->assertTrue($GLOBALS['form']->get('submit')->getConfig()->getOption('disabled'));
            $this->assertNull($GLOBALS['info_message']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function createGroup(int $number, int $year, ?\User $prof, array $students): \ProjGroup
    {
        return $this->createPageGroup(
            $number,
            $year,
            $students,
            prof: $prof,
            groupId: $number * 100,
            shiftId: $number * 10
        );
    }

    private function mockListProjectEntityManager(?\ProjGroup $group, \Deadline $deadline): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($group, $deadline) {
            public function __construct(
                private ?\ProjGroup $group,
                private \Deadline $deadline
            ) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'ProjGroup') {
                    return $this->group;
                }

                if ($entity === 'Deadline') {
                    return $this->deadline;
                }

                return null;
            }
        };

        return $oldEntityManager;
    }

    private function mockCachedGitHubClientForListProject(
        bool $isValid = true,
        int $linesOfCode = 150_000,
        int $commitsLastMonth = 10,
        int $stars = 1234,
        array $topics = ['php', 'testing']
    ): mixed {
        $oldClient = $GLOBALS['github_client_cached'] ?? null;
        $GLOBALS['github_client_cached'] = new class($isValid, $linesOfCode, $commitsLastMonth, $stars, $topics) {
            public function __construct(
                private bool $isValid,
                private int $linesOfCode,
                private int $commitsLastMonth,
                private int $stars,
                private array $topics
            ) {}

            public function api($endpoint)
            {
                return new class(
                    $this->isValid,
                    $this->linesOfCode,
                    $this->commitsLastMonth,
                    $this->stars,
                    $this->topics
                ) {
                    public function __construct(
                        private bool $isValid,
                        private int $linesOfCode,
                        private int $commitsLastMonth,
                        private int $stars,
                        private array $topics
                    ) {}

                    public function show($owner, $repo)
                    {
                        if (!$this->isValid) {
                            throw new \Github\Exception\RuntimeException('Not Found');
                        }

                        return [
                            'full_name' => "$owner/$repo",
                            'language' => 'PHP',
                            'license' => ['name' => 'MIT'],
                            'stargazers_count' => $this->stars,
                            'topics' => $this->topics,
                        ];
                    }

                    public function languages($owner, $repo)
                    {
                        return ['PHP' => $this->linesOfCode * 40];
                    }

                    public function participation($owner, $repo)
                    {
                        return [
                            'all' => array_merge(
                                array_fill(0, 48, 0),
                                [0, 0, 0, $this->commitsLastMonth]
                            ),
                        ];
                    }
                };
            }
        };

        return $oldClient;
    }

    private function runListProjectScript(array $query, bool $returnNullGroup = false): array
    {
        $script = sys_get_temp_dir() . '/pic1_listproject_' . bin2hex(random_bytes(4)) . '.php';
        $queryExport = var_export(['page' => 'listproject'] + $query, true);
        $bootstrap = var_export(dirname(__DIR__, 3) . '/tests/bootstrap.php', true);
        $page = var_export(dirname(__DIR__, 3) . '/pages/listproject.php', true);
        $entityManager = $returnNullGroup
            ? <<<'PHP'
$GLOBALS['entityManager'] = new class {
    public function find(string $entity, $id): mixed
    {
        return null;
    }
};
PHP
            : '';

        $code = <<<PHP
<?php
require $bootstrap;
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_GET = $queryExport;
\$_POST = [];
\$_REQUEST = \$_GET;
$entityManager
require $page;
PHP;

        file_put_contents($script, $code);

        try {
            $output = [];
            $exitCode = 0;
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $output, $exitCode);

            return [
                'output' => implode("\n", $output),
                'exit_code' => $exitCode,
            ];
        } finally {
            @unlink($script);
        }
    }
}
