<?php

namespace Tests\Integration\Pages;




class ProfilePageTest extends PageTestCase
{
    public function testProfilePageBuildsRepositoryUserForm()
    {
        $user = $this->createPageUser();
        $this->setUpPageRuntime('profile', $user);

        $this->runPage('profile');

        $this->assertNotNull($GLOBALS['form']);
        $this->assertTrue($GLOBALS['form']->has('repository_user'));
        $this->assertTrue($GLOBALS['form']->has('submit'));
        $this->assertNull($GLOBALS['info_box']);
    }

    public function testProfilePageDisplaysValidRepositoryUserInfo()
    {
        $oldClient = $GLOBALS['github_client'] ?? null;
        $GLOBALS['github_client'] = $this->mockGitHubUserClient();

        $user = $this->createPageUser();
        $user->repository_user = 'github:johnsmith';
        $this->setUpPageRuntime('profile', $user);

        try {
            $this->runPage('profile');

            $this->assertSame('User data', $GLOBALS['info_box']['title']);
            $this->assertSame('github', $GLOBALS['info_box']['rows']['Platform']);
            $this->assertSame('Johnsmith User', $GLOBALS['info_box']['rows']['Name']);
            $this->assertSame('johnsmith@example.com', $GLOBALS['info_box']['rows']['Email']);
            $this->assertSame('https://github.com/johnsmith', $GLOBALS['info_box']['rows']['Username']['url']);
        } finally {
            $GLOBALS['github_client'] = $oldClient;
        }
    }

    public function testProfilePageTerminatesForInvalidRepositoryUser()
    {
        $oldClient = $GLOBALS['github_client'] ?? null;
        $GLOBALS['github_client'] = new class {
            public function api($endpoint) {
                return new class {
                    public function show($username) {
                        throw new \Github\Exception\RuntimeException('Not Found');
                    }
                };
            }
        };

        $user = $this->createPageUser();
        $user->repository_user = 'github:missing';
        $this->setUpPageRuntime('profile', $user);

        try {
            $this->expectException(PageTerminated::class);
            $this->expectExceptionMessage('User not found');
            $this->runPage('profile');
        } finally {
            $GLOBALS['github_client'] = $oldClient;
        }
    }

    private function mockGitHubUserClient(): object
    {
        return new class {
            public function api($endpoint) {
                return new class {
                    public function show($username) {
                        return [
                            'login' => $username,
                            'name' => ucfirst($username) . ' User',
                            'email' => $username . '@example.com',
                            'company' => 'Tech Company',
                            'location' => 'Lisbon, Portugal',
                        ];
                    }
                };
            }
        };
    }
}
