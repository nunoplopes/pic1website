<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



require_once dirname(__DIR__, 2) . '/entities/RepositoryUser.php';

/**
 * Test suite for RepositoryUser entity
 * 
 * Tests repository user creation, parsing, and validation
 */
class RepositoryUserTest extends UnitTestCase
{
    private \User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = new \User(
            username: 'student1',
            name: 'John Student',
            email: 'john@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );
    }

    /**
     * Test repository user can be created with User object
     */
    public function testRepositoryUserCanBeCreated()
    {
        $this->user->repository_user = 'github:johnsmith';
        $repoUser = new \RepositoryUser($this->user);
        
        $this->assertInstanceOf(\RepositoryUser::class, $repoUser);
        $this->assertEquals($this->user, $repoUser->user);
    }

    /**
     * Test id method returns repository user ID
     */
    public function testIdMethodReturnsRepositoryUserId()
    {
        $this->user->repository_user = 'github:johnsmith';
        $repoUser = new \RepositoryUser($this->user);
        
        $this->assertEquals('github:johnsmith', $repoUser->id());
    }

    /**
     * Test username method extracts username from ID
     */
    public function testUsernameMethodExtractsUsername()
    {
        $this->user->repository_user = 'github:johnsmith';
        $repoUser = new \RepositoryUser($this->user);
        
        $this->assertEquals('johnsmith', $repoUser->username());
    }

    /**
     * Test username with different platform
     */
    public function testUsernameWithDifferentPlatform()
    {
        $this->user->repository_user = 'gitlab:jane.doe';
        $repoUser = new \RepositoryUser($this->user);
        
        $this->assertEquals('jane.doe', $repoUser->username());
    }

    /**
     * Test parse method with GitHub URL
     */
    public function testParseMethodWithGitHubUrl()
    {
        $url = 'https://github.com/johnsmith';
        $result = \RepositoryUser::parse($url);
        
        $this->assertStringContainsString('github', $result);
        $this->assertStringContainsString('johnsmith', $result);
    }

    /**
     * Test parse method with plain username
     */
    public function testParseMethodWithPlainUsername()
    {
        $username = 'github:johnsmith';
        $result = \RepositoryUser::parse($username);
        
        $this->assertEquals('github:johnsmith', $result);
    }

    /**
     * Test parse method preserves already canonical input
     */
    public function testParseMethodWithValidFormat()
    {
        // Test that parse accepts valid format
        $result = \RepositoryUser::parse('github:johnsmith');
        $this->assertEquals('github:johnsmith', $result);
    }

    /**
     * Test parse leaves non-URL input for check() to validate
     */
    public function testParseLeavesNonUrlInputForValidation()
    {
        $result = \RepositoryUser::parse('invalidformat');
        $this->assertEquals('invalidformat', $result);
    }

    /**
     * Test repository user with different platforms
     */
    public function testRepositoryUserWithDifferentPlatforms()
    {
        $platforms = [
            'github:user1',
            'gitlab:user2',
            'bitbucket:user3',
        ];
        
        foreach ($platforms as $repoId) {
            $user = new \User('student', 'Name', 'email@example.com', '', ROLE_STUDENT, false);
            $user->repository_user = $repoId;
            $repoUser = new \RepositoryUser($user);
            
            $this->assertEquals($repoId, $repoUser->id());
        }
    }

    /**
     * Test username with special characters
     */
    public function testUsernameWithSpecialCharacters()
    {
        $this->user->repository_user = 'github:user-name_123';
        $repoUser = new \RepositoryUser($this->user);
        
        $this->assertEquals('user-name_123', $repoUser->username());
    }

    /**
     * Test multiple repository users from same user
     */
    public function testMultipleRepositoryUsersFromSameUser()
    {
        $user1 = new \User('student', 'Name', 'email@example.com', '', ROLE_STUDENT, false);
        $user1->repository_user = 'github:johnsmith';
        $repoUser1 = new \RepositoryUser($user1);
        
        // Create new user with different repository
        $user2 = new \User('student2', 'Name2', 'email2@example.com', '', ROLE_STUDENT, false);
        $user2->repository_user = 'github:jane.doe';
        $repoUser2 = new \RepositoryUser($user2);
        
        $this->assertEquals('johnsmith', $repoUser1->username());
        $this->assertEquals('jane.doe', $repoUser2->username());
    }

    /**
     * Test repository user equality
     */
    public function testRepositoryUserEquality()
    {
        $this->user->repository_user = 'github:johnsmith';
        $repoUser1 = new \RepositoryUser($this->user);
        $repoUser2 = new \RepositoryUser($this->user);
        
        $this->assertEquals($repoUser1->id(), $repoUser2->id());
        $this->assertEquals($repoUser1->username(), $repoUser2->username());
    }

    public function testPROpenedEventStoresPullRequestAndDate()
    {
        $pr = new class extends \PullRequest {
            public function url(): string { return 'https://github.com/mockorg/mockrepo/pull/1'; }
            public function branchURL(): string { return 'https://github.com/mockorg/mockrepo/tree/branch'; }
            public function origin(): string { return 'mockorg:mockrepo:branch'; }
            public function isClosed(): bool { return false; }
            public function wasMerged(): bool { return false; }
            public function mergedBy(): string { return ''; }
            public function mergeDate(): \DateTimeImmutable { return new \DateTimeImmutable(); }
            public function linesAdded(): int { return 0; }
            public function linesDeleted(): int { return 0; }
            public function filesModified(): int { return 0; }
            public function failedCIjobs(string $hash): array { return []; }
            public function __toString(): string { return $this->url(); }
        };
        $date = new \DateTimeImmutable('2026-04-26 10:00:00');

        $event = new \PROpenedEvent($pr, $date);

        $this->assertSame($pr, $event->pr);
        $this->assertSame($date, $event->date);
    }

    public function testParseThrowsForUnsupportedUrl()
    {
        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage('Unsupported URL');

        \RepositoryUser::parse('https://gitlab.com/johnsmith');
    }

    public function testCheckAcceptsExistingGithubUser()
    {
        $this->user->repository_user = 'github:johnsmith';

        \RepositoryUser::check($this->user);

        $this->assertEquals('github:johnsmith', $this->user->repository_user);
    }

    public function testCheckThrowsWhenUserDoesNotExist()
    {
        $this->mockMissingGitHubUser();
        $this->user->repository_user = 'github:missinguser';

        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage('user does not exist');
        \RepositoryUser::check($this->user);
    }

    public function testPlatformMethodExtractsPlatform()
    {
        $this->user->repository_user = 'github:johnsmith';
        $repoUser = new \RepositoryUser($this->user);

        $this->assertEquals('github', $repoUser->platform());
    }

    public function testGithubBackedProfileMethods()
    {
        $this->user->repository_user = 'github:johnsmith';
        $repoUser = new \RepositoryUser($this->user);

        $this->assertTrue($repoUser->isValid());
        $this->assertEquals('https://github.com/johnsmith', $repoUser->profileURL());
        $this->assertEquals('Johnsmith User', $repoUser->name());
        $this->assertEquals('johnsmith@example.com', $repoUser->email());
        $this->assertEquals('Tech Company', $repoUser->company());
        $this->assertEquals('Lisbon, Portugal', $repoUser->location());
    }

    public function testUnsupportedRepositoryUserPlatformTriggersAssertion()
    {
        $this->user->repository_user = 'gitlab:johnsmith';
        $repoUser = new \RepositoryUser($this->user);

        $this->expectException(\AssertionError::class);

        $repoUser->isValid();
    }

    public function testDescriptionIncludesAvailableProfileFields()
    {
        $this->user->repository_user = 'github:johnsmith';
        $repoUser = new \RepositoryUser($this->user);

        $description = $repoUser->description();

        $this->assertStringContainsString('[name: Johnsmith User]', $description);
        $this->assertStringContainsString('[email: johnsmith@example.com]', $description);
        $this->assertStringContainsString('[company: Tech Company]', $description);
        $this->assertStringContainsString('[location: Lisbon, Portugal]', $description);
    }

    public function testDescriptionSkipsMissingProfileFields()
    {
        $this->replaceGitHubClient(new class {
            public function api($endpoint) {
                return new class {
                    public function show($username) {
                        return [
                            'login' => $username,
                            'name' => 'Only Name',
                            'email' => null,
                            'company' => '',
                            'location' => null,
                        ];
                    }
                };
            }
        });
        $this->user->repository_user = 'github:johnsmith';
        $repoUser = new \RepositoryUser($this->user);

        $this->assertEquals(' [name: Only Name]', $repoUser->description());
    }

    public function testGetUnprocessedEventsReturnsArrayWhenGitHubErrors()
    {
        if (!function_exists('\\GitHub\\github_set_etag')) {
            eval('namespace GitHub; function github_set_etag($etag) {} function github_remove_etag() {}');
        }
        $this->mockRuntimeExceptionGitHubClient();
        $this->user->repository_user = 'github:johnsmith';
        $repoUser = new \RepositoryUser($this->user);

        $this->assertEquals([], $repoUser->getUnprocessedEvents());
    }

    private function mockMissingGitHubUser(): void
    {
        $this->replaceGitHubClient(new class {
            public function api($endpoint) {
                return new class {
                    public function show($username) {
                        throw new \Exception('not found');
                    }
                };
            }
        });
    }

    private function mockRuntimeExceptionGitHubClient(): void
    {
        $this->replaceGitHubClient(new class {
            public function user($type) {
                throw new \Github\Exception\RuntimeException('Not Found');
            }
        });
    }
}
