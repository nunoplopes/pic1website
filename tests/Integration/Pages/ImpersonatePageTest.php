<?php

namespace Tests\Integration\Pages;



class ImpersonatePageTest extends PageTestCase
{
    public function testImpersonatePageBuildsDummyAndUserLists()
    {
        $user = $this->createPageUser('ist9950', 'Real User', ROLE_STUDENT);
        $oldEntityManager = $this->mockImpersonateEntityManager([$user]);

        try {
            $sudo = $this->createPageUser('ist9951', 'Sudo User', ROLE_SUDO);
            $this->setUpPageRuntime('impersonate', $sudo);
            $this->runPage('impersonate');

            $this->assertCount(3, $GLOBALS['lists']['Switch to dummy']);
            $this->assertSame('Professor', $GLOBALS['lists']['Switch to dummy'][0]['label']);
            $this->assertSame('index.php?newrole=1&page=impersonate', $GLOBALS['lists']['Switch to dummy'][0]['url']);
            $this->assertSame('TA', $GLOBALS['lists']['Switch to dummy'][1]['label']);
            $this->assertSame('index.php?newrole=2&page=impersonate', $GLOBALS['lists']['Switch to dummy'][1]['url']);
            $this->assertSame('Student', $GLOBALS['lists']['Switch to dummy'][2]['label']);
            $this->assertSame('index.php?newrole=3&page=impersonate', $GLOBALS['lists']['Switch to dummy'][2]['url']);
            $this->assertCount(1, $GLOBALS['lists']['Impersonate real users']);
            $this->assertSame(
                'ist9950: Real User (Student)',
                $GLOBALS['lists']['Impersonate real users'][0]['label']
            );
            $this->assertSame(
                'index.php?username=ist9950&page=impersonate',
                $GLOBALS['lists']['Impersonate real users'][0]['url']
            );
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testImpersonatePageSwitchesToRequestedUser()
    {
        $target = $this->createPageUser('ist9952', 'Chosen User', ROLE_TA);
        $oldEntityManager = $this->mockImpersonateEntityManager([$target]);

        try {
            $sudo = $this->createPageUser('ist9953', 'Sudo User', ROLE_SUDO);
            $this->setUpPageRuntime('impersonate', $sudo, 'GET', ['username' => $target->id]);
            $this->runPage('impersonate');

            $this->assertSame($target, $GLOBALS['__page_test_user']);
            $this->assertCount(1, $GLOBALS['__page_test_auth_set_user']);
            $this->assertSame($target, $GLOBALS['__page_test_auth_set_user'][0]);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testImpersonatePageSwitchesToRequestedDummyRole()
    {
        $oldEntityManager = $this->mockImpersonateEntityManager([]);
        $_SESSION = [];

        try {
            $sudo = $this->createPageUser('ist9954', 'Sudo User', ROLE_SUDO);
            $this->setUpPageRuntime('impersonate', $sudo, 'GET', ['newrole' => ROLE_TA]);
            $this->runPage('impersonate');

            $saved = $GLOBALS['entityManager']->persisted[0] ?? null;
            $this->assertInstanceOf(\User::class, $saved);
            $this->assertSame('ist00002', $_SESSION['username']);
            $this->assertSame('ist00002', $saved->id);
            $this->assertSame('Dummy 2', $saved->name);
            $this->assertSame(ROLE_TA, $saved->role);
            $this->assertSame('ist00002@example.org', $saved->email);
            $this->assertSame(
                'https://api.dicebear.com/9.x/notionists-neutral/svg?seed=Adrian&lips=variant17',
                $saved->photo
            );
            $this->assertTrue($saved->isDummy());
            $this->assertCount(1, $GLOBALS['entityManager']->persisted);
            $this->assertSame(1, $GLOBALS['entityManager']->flushCount);
            $this->assertCount(1, $GLOBALS['__page_test_auth_set_user']);
            $this->assertSame($saved, $GLOBALS['__page_test_auth_set_user'][0]);
            $this->assertSame($saved, $GLOBALS['__page_test_user']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testImpersonatePageReusesExistingDummyUserWhenRoleAlreadyExists()
    {
        $existing = new \User(
            'ist00001',
            'Dummy 1',
            'ist00001@example.org',
            'https://api.dicebear.com/9.x/notionists-neutral/svg?seed=Ryker&lips=variant17',
            ROLE_PROF,
            true
        );
        $oldEntityManager = $this->mockImpersonateEntityManager([$existing]);
        $_SESSION = [];

        try {
            $sudo = $this->createPageUser('ist9955', 'Sudo User', ROLE_SUDO);
            $this->setUpPageRuntime('impersonate', $sudo, 'GET', ['newrole' => ROLE_PROF]);
            $this->runPage('impersonate');

            $this->assertSame('ist00001', $_SESSION['username']);
            $this->assertSame([], $GLOBALS['entityManager']->persisted);
            $this->assertSame(0, $GLOBALS['entityManager']->flushCount);
            $this->assertCount(1, $GLOBALS['__page_test_auth_set_user']);
            $this->assertSame($existing, $GLOBALS['__page_test_auth_set_user'][0]);
            $this->assertSame($existing, $GLOBALS['__page_test_user']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testImpersonatePageRejectsUnknownUsername()
    {
        $result = $this->runImpersonateScript(['username' => 'missing']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('Unknown user', $result['output']);
    }

    public function testImpersonatePageRejectsInvalidRole()
    {
        $result = $this->runImpersonateScript(['newrole' => 99]);

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('Unknown role', $result['output']);
    }

    private function mockImpersonateEntityManager(array $users): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user->id] = $user;
        }

        $GLOBALS['entityManager'] = new class($users, $usersById) {
            public array $persisted = [];
            public int $flushCount = 0;

            public function __construct(private array $users, private array $usersById) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'User') {
                    return $this->usersById[$id] ?? null;
                }

                return null;
            }

            public function getRepository(string $entity): object
            {
                if ($entity === 'User') {
                    return new class($this->users) {
                        public function __construct(private array $users) {}

                        public function findBy(array $criteria, array $order): array
                        {
                            return $this->users;
                        }
                    };
                }

                throw new \RuntimeException("Unexpected repository lookup: $entity");
            }

            public function persist($obj): void
            {
                $this->persisted[] = $obj;
                if ($obj instanceof \User) {
                    $this->usersById[$obj->id] = $obj;
                    $this->users[] = $obj;
                }
            }

            public function flush(): void
            {
                $this->flushCount++;
            }
        };

        return $oldEntityManager;
    }

    private function runImpersonateScript(array $query): array
    {
        $script = sys_get_temp_dir() . '/pic1_impersonate_' . bin2hex(random_bytes(4)) . '.php';
        $bootstrap = var_export(dirname(__DIR__, 3) . '/tests/bootstrap.php', true);
        $pageTestCase = var_export(dirname(__DIR__) . '/Pages/PageTestCase.php', true);
        $page = var_export(dirname(__DIR__, 3) . '/pages/impersonate.php', true);
        $queryExport = var_export(['page' => 'impersonate'] + $query, true);

        $code = <<<PHP
<?php
require $bootstrap;
require $pageTestCase;
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_GET = $queryExport;
\$_POST = [];
\$_REQUEST = \$_GET;
\$_SESSION = [];
\$GLOBALS['entityManager'] = new class {
    public function find(string \$entity, \$id): mixed
    {
        return null;
    }

    public function getRepository(string \$entity): object
    {
        if (\$entity === 'User') {
            return new class {
                public function findBy(array \$criteria, array \$order): array
                {
                    return [];
                }
            };
        }

        throw new RuntimeException('Unexpected repository lookup');
    }
};
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
