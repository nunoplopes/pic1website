<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;
use Tests\Mocks\MockGitHubClient;
use GitHub\GitHubDiscoverProjects;

/**
 * Test suite for GitHubDiscoverProjects
 * 
 * Tests the GitHub API integration for discovering projects
 * by keywords and topics.
 * 
 * Note: These tests are simplified to work with the current
 * global client design. For full integration, consider:
 * 1. Injecting the client as a dependency
 * 2. Using a test API key or mock response
 */
class GitHubDiscoverProjectsTest extends UnitTestCase
{
    public function testSearchByTopicsBuildsQueryAndFiltersSmallRepos()
    {
        $activity = [
            'all' => array_merge(array_fill(0, 48, 0), [10, 10, 10, 10]),
            'owner' => array_fill(0, 52, 0),
        ];
        $client = $this->replaceCachedGitHubClient(new MockGitHubClient(
            searchResults: ['items' => [
                [
                    'full_name' => 'big/repo',
                    'description' => 'Large repository',
                    'html_url' => 'https://github.com/big/repo',
                    'stargazers_count' => 12345,
                    'open_issues' => 456,
                    'language' => 'PHP',
                    'topics' => ['testing'],
                    'pushed_at' => '2026-04-26T10:00:00+00:00',
                ],
                [
                    'full_name' => 'small/repo',
                    'description' => 'Small repository',
                    'html_url' => 'https://github.com/small/repo',
                    'stargazers_count' => 500,
                    'open_issues' => 10,
                    'language' => 'PHP',
                    'topics' => ['testing'],
                    'pushed_at' => '2026-04-26T10:00:00+00:00',
                ],
            ]],
            languagesByRepository: [
                'big/repo' => ['PHP' => 4_800_000],
                'small/repo' => ['PHP' => 40_000],
            ],
            participationByRepository: [
                'big/repo' => $activity,
                'small/repo' => $activity,
            ]
        ));

        $results = GitHubDiscoverProjects::searchByTopics('testing', 'PHP', true);

        $this->assertCount(1, $results);
        $this->assertEquals('github:big/repo', $results[0]['name']);
        $this->assertStringContainsString('in:topics', $client->queries[0]);
        $this->assertStringContainsString('language:PHP', $client->queries[0]);
        $this->assertStringContainsString('good-first-issues:>30', $client->queries[0]);
    }

    public function testSearchByKeywordFiltersInactiveRepos()
    {
        $this->replaceCachedGitHubClient(new MockGitHubClient(
            searchResults: ['items' => [
                [
                    'full_name' => 'active/repo',
                    'description' => 'Active repository',
                    'html_url' => 'https://github.com/active/repo',
                    'stargazers_count' => 12345,
                    'open_issues' => 456,
                    'language' => 'PHP',
                    'topics' => ['testing'],
                    'pushed_at' => '2026-04-26T10:00:00+00:00',
                ],
                [
                    'full_name' => 'inactive/repo',
                    'description' => 'Inactive repository',
                    'html_url' => 'https://github.com/inactive/repo',
                    'stargazers_count' => 12345,
                    'open_issues' => 456,
                    'language' => 'PHP',
                    'topics' => ['testing'],
                    'pushed_at' => '2026-04-26T10:00:00+00:00',
                ],
            ]],
            languagesByRepository: [
                'active/repo' => ['PHP' => 4_800_000],
                'inactive/repo' => ['PHP' => 4_800_000],
            ],
            participationByRepository: [
                'active/repo' => [
                    'all' => array_merge(array_fill(0, 48, 0), [10, 10, 10, 10]),
                    'owner' => array_fill(0, 52, 0),
                ],
                'inactive/repo' => [
                    'all' => array_merge(array_fill(0, 48, 0), [5, 5, 5, 5]),
                    'owner' => array_fill(0, 52, 0),
                ],
            ]
        ));

        $results = GitHubDiscoverProjects::searchByKeyword('testing', 'PHP', false);

        $this->assertCount(1, $results);
        $this->assertEquals('github:active/repo', $results[0]['name']);
    }
}
