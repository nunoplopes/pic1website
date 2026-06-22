<?php

namespace Tests\Integration;

use Tests\Unit\Base\UnitTestCase;
use Tests\Mocks\MockGitHubClient;

/**
 * Integration tests for GitHub Project Discovery
 * 
 * These tests validate the interaction between:
 * - GitHub API client
 * - Repository discovery logic
 * - Data transformation and storage
 * 
 * Note: These require configured GitHub API client to run
 */
class GitHubDiscoverProjectsIntegrationTest extends UnitTestCase
{
    private mixed $oldGitHubClientCached = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oldGitHubClientCached = $GLOBALS['github_client_cached'] ?? null;
        $GLOBALS['github_client_cached'] = $this->mockGitHubClient();
    }

    protected function tearDown(): void
    {
        $GLOBALS['github_client_cached'] = $this->oldGitHubClientCached;
        parent::tearDown();
    }

    /**
     * Test full discovery workflow with keyword search
     * 
     * Workflow:
     * 1. Search repository by keyword
     * 2. Apply language filter
     * 3. Filter by issue availability
     * 4. Return formatted results
     */
    public function testKeywordSearchDiscoveryWorkflow()
    {
        $results = \GitHub\GitHubDiscoverProjects::searchByKeyword('api', 'PHP', true);

        $this->assertCount(1, $results);
        $this->assertEquals('github:large/repo', $results[0]['name']);
        $this->assertEquals('PHP', $results[0]['language']);
        $this->assertStringContainsString('in:name,description,topics', $GLOBALS['github_client_cached']->queries[0]);
        $this->assertStringContainsString('language:PHP', $GLOBALS['github_client_cached']->queries[0]);
        $this->assertStringContainsString('good-first-issues:>30', $GLOBALS['github_client_cached']->queries[0]);
    }

    /**
     * Test discovery with topic-based search
     * 
     * Workflow:
     * 1. Search repositories by topics
     * 2. Filter by project maturity (size, stars)
     * 3. Validate project requirements
     */
    public function testTopicBasedDiscoveryWorkflow()
    {
        $results = \GitHub\GitHubDiscoverProjects::searchByTopics('framework', '', false);

        $this->assertCount(1, $results);
        $this->assertStringContainsString('in:topics', $GLOBALS['github_client_cached']->queries[0]);
        $this->assertStringNotContainsString('language:', $GLOBALS['github_client_cached']->queries[0]);
    }

    /**
     * Test data transformation and persistence
     * 
     * Workflow:
     * 1. Fetch raw GitHub API response
     * 2. Transform to standard format
     * 3. Calculate derived metrics (LOC, activity)
     * 4. Store or cache results
     */
    public function testDataTransformationAndPersistence()
    {
        $results = \GitHub\GitHubDiscoverProjects::searchByKeyword('api', null, false);

        $this->assertEquals('Large mock repository', $results[0]['description']);
        $this->assertEquals('https://github.com/large/repo', $results[0]['url']);
        $this->assertEquals('1' . "\u{202F}" . 'k', $results[0]['stars']);
        $this->assertEquals('120' . "\u{202F}" . 'k', $results[0]['loc']);
        $this->assertEquals(42, $results[0]['open_issues']);
        $this->assertEquals(['api', 'framework'], $results[0]['topics']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $results[0]['last_push']);
    }

    /**
     * Test filter combination effects
     * 
     * Workflow:
     * 1. Apply language filter
     * 2. Apply maturity filter (stars >= 200)
     * 3. Apply size filter (LOC >= 100k)
     * 4. Apply newbie-friendliness filter
     * 5. Validate results meet all criteria
     */
    public function testMultipleFilterInteraction()
    {
        \GitHub\GitHubDiscoverProjects::searchByKeyword('help', 'JavaScript', true);

        $query = $GLOBALS['github_client_cached']->queries[0];
        $this->assertStringContainsString('size:>3900', $query);
        $this->assertStringContainsString('stars:>=200', $query);
        $this->assertStringContainsString('pushed:', $query);
        $this->assertStringContainsString('language:JavaScript', $query);
        $this->assertStringContainsString('good-first-issues:>30', $query);
        $this->assertStringContainsString('mirror:false archived:false template:false', $query);
    }

    /**
     * Integration test with mock GitHub API client
     * 
     * Tests the discovery workflow using a mock client that simulates
     * GitHub API responses. This validates data transformation and
     * project filtering logic without requiring network access.
     * 
     * Workflow:
     * 1. Initialize mocked GitHub API client
     * 2. Execute search request
     * 3. Parse mock API response
     * 4. Calculate line of code metrics
     * 5. Return formatted project list
     */
    public function testMockBackedIntegration()
    {
        $results = \GitHub\GitHubDiscoverProjects::searchByKeyword('api', 'PHP', false);

        $this->assertCount(1, $results);
        $this->assertSame(['api', 'framework'], $results[0]['topics']);
    }

    private function mockGitHubClient(): MockGitHubClient
    {
        $activity = [
            'all' => array_merge(array_fill(0, 48, 0), [10, 10, 10, 10]),
            'owner' => array_fill(0, 52, 0),
        ];

        return new MockGitHubClient(
            searchResults: ['items' => [
                [
                    'full_name' => 'large/repo',
                    'description' => 'Large mock repository',
                    'html_url' => 'https://github.com/large/repo',
                    'stargazers_count' => 1200,
                    'open_issues' => 42,
                    'language' => 'PHP',
                    'topics' => ['api', 'framework'],
                    'pushed_at' => '2026-04-26T10:00:00+00:00',
                ],
                [
                    'full_name' => 'small/repo',
                    'description' => 'Small mock repository',
                    'html_url' => 'https://github.com/small/repo',
                    'stargazers_count' => 300,
                    'open_issues' => 5,
                    'language' => 'PHP',
                    'topics' => ['api'],
                    'pushed_at' => '2026-04-26T10:00:00+00:00',
                ],
            ]],
            languagesByRepository: [
                'large/repo' => ['PHP' => 4_800_000],
                'small/repo' => ['PHP' => 40_000],
            ],
            participationByRepository: [
                'large/repo' => $activity,
                'small/repo' => $activity,
            ]
        );
    }
}
