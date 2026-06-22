<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;
use Tests\Mocks\MockGitHubClient;
class DiscoverProjectsTest extends UnitTestCase
{
    public function testSearchByKeywordDelegatesToGitHubDiscovery()
    {
        $this->replaceCachedGitHubClient(new MockGitHubClient(
            searchResults: ['items' => [[
                'full_name' => 'owner/repo',
                'description' => 'Mock repository',
                'html_url' => 'https://github.com/owner/repo',
                'stargazers_count' => 200,
                'open_issues' => 10,
                'language' => 'PHP',
                'topics' => ['api'],
                'pushed_at' => '2026-04-26T10:00:00+00:00',
            ]]],
            languagesByRepository: ['owner/repo' => ['PHP' => 4_800_000]],
            participationByRepository: ['owner/repo' => [
                'all' => array_merge(array_fill(0, 48, 0), [10, 10, 10, 10]),
                'owner' => array_fill(0, 52, 0),
            ]]
        ));

        $result = \DiscoverProjects::searchByKeyword('api', 'PHP', false);

        $this->assertCount(1, $result);
        $this->assertEquals('github:owner/repo', $result[0]['name']);
    }
}
