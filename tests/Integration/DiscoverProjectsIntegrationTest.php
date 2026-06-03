<?php

namespace Tests\Integration;

use Tests\Unit\Base\UnitTestCase;
use Tests\Mocks\MockGitHubClient;

/**
 * Integration test suite for DiscoverProjects
 * 
 * Tests the actual GitHub API integration for project discovery
 */
class DiscoverProjectsIntegrationTest extends UnitTestCase
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
     * Test searchByKeyword executes delegation to GitHub API
     */
    public function testSearchByKeywordExecutesDelegation()
    {
        $result = \DiscoverProjects::searchByKeyword('api', 'php', false);

        $this->assertCount(1, $result);
        $this->assertEquals('github:large/repo', $result[0]['name']);
        $this->assertStringContainsString('api in:name,description,topics', $GLOBALS['github_client_cached']->queries[0]);
    }

    /**
     * Test searchByKeyword with empty keywords
     */
    public function testSearchByKeywordWithEmptyKeywords()
    {
        $result = \DiscoverProjects::searchByKeyword('', 'php', false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('in:name,description,topics', $GLOBALS['github_client_cached']->queries[0]);
    }

    /**
     * Test searchByKeyword with different languages
     */
    public function testSearchByKeywordDifferentLanguages()
    {
        $languages = ['php', 'javascript', 'python', 'java'];
        foreach ($languages as $lang) {
            $result = \DiscoverProjects::searchByKeyword('test', $lang, false);
            $this->assertCount(1, $result);
        }

        $this->assertStringContainsString('language:php', $GLOBALS['github_client_cached']->queries[0]);
        $this->assertStringContainsString('language:java', $GLOBALS['github_client_cached']->queries[3]);
    }

    /**
     * Test searchByKeyword with newbie issues flag
     */
    public function testSearchByKeywordNewbieIssuesFlag()
    {
        $resultWithNewbie = \DiscoverProjects::searchByKeyword('help', 'php', true);
        $resultWithoutNewbie = \DiscoverProjects::searchByKeyword('help', 'php', false);

        $this->assertCount(1, $resultWithNewbie);
        $this->assertCount(1, $resultWithoutNewbie);
        $this->assertStringContainsString('good-first-issues:>30', $GLOBALS['github_client_cached']->queries[0]);
        $this->assertStringNotContainsString('good-first-issues:>30', $GLOBALS['github_client_cached']->queries[1]);
    }

    private function mockGitHubClient(): MockGitHubClient
    {
        return new MockGitHubClient(
            searchResults: ['items' => [[
                'full_name' => 'large/repo',
                'description' => 'Large mock repository',
                'html_url' => 'https://github.com/large/repo',
                'stargazers_count' => 1200,
                'open_issues' => 42,
                'language' => 'PHP',
                'topics' => ['api'],
                'pushed_at' => '2026-04-26T10:00:00+00:00',
            ]]],
            languagesByRepository: ['large/repo' => ['PHP' => 4_800_000]],
            participationByRepository: ['large/repo' => [
                'all' => array_merge(array_fill(0, 48, 0), [10, 10, 10, 10]),
                'owner' => array_fill(0, 52, 0),
            ]]
        );
    }
}
