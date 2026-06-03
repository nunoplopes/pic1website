<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for Repository entity
 * 
 * Tests repository parsing and property extraction
 */
class RepositoryTest extends UnitTestCase
{
    /**
     * Test that repository can be created with valid ID
     */
    public function testRepositoryCanBeCreated()
    {
        $repo = new \Repository('github:owner/repo-name');
        
        $this->assertInstanceOf(\Repository::class, $repo);
        $this->assertEquals('github:owner/repo-name', $repo->id);
    }

    /**
     * Test that name() method extracts repository name
     */
    public function testNameExtractsRepositoryName()
    {
        $repo = new \Repository('github:owner/repo-name');
        
        $this->assertEquals('owner/repo-name', $repo->name());
    }

    /**
     * Test name() with different repository names
     */
    public function testNameWithDifferentRepositories()
    {
        $testCases = [
            'github:laravel/framework' => 'laravel/framework',
            'github:facebook/react' => 'facebook/react',
            'github:torvalds/linux' => 'torvalds/linux',
        ];

        foreach ($testCases as $id => $expectedName) {
            $repo = new \Repository($id);
            $this->assertEquals($expectedName, $repo->name());
        }
    }

    /**
     * Test that platform() method extracts platform
     */
    public function testPlatformReturnsPlatform()
    {
        $repo = new \Repository('github:owner/repo');
        
        $this->assertEquals('github', $repo->platform());
    }

    /**
     * Test platform() with various platforms
     */
    public function testPlatformWithVariousPlatforms()
    {
        $platforms = ['github', 'gitrepo', 'custom'];

        foreach ($platforms as $platform) {
            $repo = new \Repository("$platform:owner/name");
            $this->assertEquals($platform, $repo->platform());
        }
    }

    /**
     * Test __toString() method
     */
    public function testToStringReturnsStringRepresentation()
    {
        // This test assumes __toString returns get('toString')
        // which would depend on platform implementation
        $repo = new \Repository('github:owner/repo-name');
        
        // Just verify it can be converted to string
        $this->assertIsString((string)$repo);
    }

    /**
     * Test repository ID with hyphenated names
     */
    public function testRepositoryNameWithHyphens()
    {
        $repo = new \Repository('github:some-org/some-repo-name');
        
        $this->assertEquals('some-org/some-repo-name', $repo->name());
        $this->assertEquals('github', $repo->platform());
    }

    /**
     * Test repository ID with underscores
     */
    public function testRepositoryNameWithUnderscores()
    {
        $repo = new \Repository('github:_owner/_repo_name');
        
        $this->assertEquals('_owner/_repo_name', $repo->name());
    }

    /**
     * Test repository name with numbers
     */
    public function testRepositoryNameWithNumbers()
    {
        $repo = new \Repository('github:owner123/repo456');
        
        $this->assertEquals('owner123/repo456', $repo->name());
        $this->assertEquals('github', $repo->platform());
    }

    /**
     * Test that different repositories are different instances
     */
    public function testDifferentRepositoriesAreDifferentInstances()
    {
        $repo1 = new \Repository('github:owner1/repo1');
        $repo2 = new \Repository('github:owner2/repo2');
        
        $this->assertNotSame($repo1, $repo2);
        $this->assertNotEquals($repo1->id, $repo2->id);
    }

    /**
     * Test repository platform delimiter is colon
     */
    public function testPlatformNameDelimiterIsColon()
    {
        $repo = new \Repository('github:owner/repo');
        
        // Verify the delimiter works correctly
        $this->assertStringContainsString(':', $repo->id);
        
        $parts = explode(':', $repo->id);
        $this->assertEquals('github', $parts[0]);
        $this->assertEquals('owner/repo', $parts[1]);
    }

    /**
     * Test name with slashes (owner/repo structure)
     */
    public function testNamePreservesSlashes()
    {
        $repo = new \Repository('github:owner/repo');
        $name = $repo->name();
        
        $this->assertStringContainsString('/', $name);
        
        $parts = explode('/', $name);
        $this->assertCount(2, $parts);
    }

    /**
     * Test repository ID is stored unchanged
     */
    public function testRepositoryIdIsStoredUnchanged()
    {
        $id = 'github:some-owner/some-repo-name';
        $repo = new \Repository($id);
        
        $this->assertEquals($id, $repo->id);
    }

    /**
     * Test name extraction with complex repository names
     */
    public function testNameExtractWithComplexNames()
    {
        $repo = new \Repository('github:microsoft/vscode-python');
        
        $platform = $repo->platform();
        $name = $repo->name();
        
        $this->assertEquals('github', $platform);
        $this->assertEquals('microsoft/vscode-python', $name);
        $this->assertStringStartsWith('microsoft', $name);
    }

    public function testGithubBackedRepositoryMethods()
    {
        $this->mockCachedGitHubClient();
        $repo = new \Repository('github:owner/repo');

        $this->assertEquals('1', $repo->isValid());
        $this->assertEquals('main', $repo->defaultBranch());
        $this->assertEquals('upstream/repo', $repo->parent());
        $this->assertEquals('PHP', $repo->language());
        $this->assertEquals('MIT', $repo->license());
        $this->assertEquals(1234, $repo->stars());
        $this->assertEquals(['php', 'testing'], $repo->topics());
        $this->assertEquals(150, $repo->linesOfCode());
        $this->assertEquals(10, $repo->commitsLastMonth());
        $this->assertEquals('https://github.com/owner/repo', (string)$repo);
    }

    public function testFactoryCreatesGithubRepositoryId()
    {
        $this->mockCachedGitHubClient();

        $this->assertEquals(
            'github:owner/repo',
            \Repository::factory('https://github.com/owner/repo')
        );
    }

    public function testFactoryRejectsUnsupportedUrl()
    {
        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage('Unsupported URL');

        \Repository::factory('https://gitlab.com/owner/repo');
    }

    public function testFactoryRejectsUnknownRepository()
    {
        $this->mockCachedGitHubClient(throwOnShow: true);

        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage('Unknown project repository');
        \Repository::factory('https://github.com/owner/missing');
    }

    public function testGithubBackedRepositoryMethodsHandleMissingOptionalData()
    {
        $this->mockCachedGitHubClient(parent: null, license: null, language: null);
        $repo = new \Repository('github:owner/repo');

        $this->assertNull($repo->parent());
        $this->assertEquals('', $repo->language());
        $this->assertNull($repo->license());
    }

    public function testIsValidReturnsFalseForGitHubNotFound()
    {
        $this->replaceCachedGitHubClient(new class {
            public function api($endpoint) {
                return new class {
                    public function show($owner, $repo) {
                        throw new \Github\Exception\RuntimeException('Not Found');
                    }
                };
            }
        });
        $repo = new \Repository('github:owner/missing');

        $this->assertFalse((bool)$repo->isValid());
    }

    public function testUnsupportedRepositoryPlatformTriggersAssertion()
    {
        $repo = new \Repository('gitlab:owner/repo');

        $this->expectException(\AssertionError::class);

        $repo->isValid();
    }

    private function mockCachedGitHubClient(
        bool $throwOnShow = false,
        ?string $parent = 'upstream/repo',
        ?string $license = 'MIT',
        ?string $language = 'PHP'
    ): void {
        $this->replaceCachedGitHubClient(new class($throwOnShow, $parent, $license, $language) {
            public function __construct(
                private bool $throwOnShow,
                private ?string $parent,
                private ?string $license,
                private ?string $language
            ) {}

            public function api($endpoint) {
                return new class($this->throwOnShow, $this->parent, $this->license, $this->language) {
                    public function __construct(
                        private bool $throwOnShow,
                        private ?string $parent,
                        private ?string $license,
                        private ?string $language
                    ) {}

                    public function show($owner, $repo) {
                        if ($this->throwOnShow) {
                            throw new \Exception('missing');
                        }

                        return [
                            'full_name' => "$owner/$repo",
                            'default_branch' => 'main',
                            'parent' => $this->parent === null
                                ? null
                                : ['full_name' => $this->parent],
                            'language' => $this->language,
                            'license' => $this->license === null
                                ? null
                                : ['name' => $this->license],
                            'stargazers_count' => 1234,
                            'topics' => ['php', 'testing'],
                        ];
                    }

                    public function languages($owner, $repo) {
                        return [
                            'PHP' => 4000,
                            'JavaScript' => 2000,
                        ];
                    }

                    public function participation($owner, $repo) {
                        return [
                            'all' => array_merge(array_fill(0, 48, 0), [1, 2, 3, 4]),
                        ];
                    }
                };
            }
        });
    }
}
