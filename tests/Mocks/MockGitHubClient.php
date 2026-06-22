<?php

namespace Tests\Mocks;

/**
 * Mock GitHub API Client for testing
 * 
 * Returns realistic fake data without making real API calls
 * or exposing credentials
 */
class MockGitHubClient {
    public array $queries = [];

    private ?array $searchResults;
    private array $languagesByRepository;
    private array $participationByRepository;

    public function __construct(
        ?array $searchResults = null,
        array $languagesByRepository = [],
        array $participationByRepository = []
    ) {
        $this->searchResults = $searchResults;
        $this->languagesByRepository = $languagesByRepository;
        $this->participationByRepository = $participationByRepository;
    }

    public function api($endpoint) {
        if ($endpoint === 'search') {
            return new MockSearchAPI($this, $this->searchResults);
        }
        if ($endpoint === 'issue') {
            return new MockIssueAPI();
        }
        if ($endpoint === 'repo') {
            return new MockRepoAPI($this->languagesByRepository, $this->participationByRepository);
        }
        if ($endpoint === 'repository') {
            return new MockRepositoryAPI();
        }
        if ($endpoint === 'pr') {
            return new MockPullRequestAPI();
        }
        if ($endpoint === 'user') {
            return new MockUserAPI();
        }
        throw new \Exception("Mock API endpoint not implemented: $endpoint");
    }

    public function authenticate($token, $login, $method) {
        // No-op
    }

    public function setSearchResults(array $results) {
        $this->searchResults = $results;
    }

    public function recordQuery(string $query): void {
        $this->queries[] = $query;
    }
}

/**
 * Mock for repository->branches
 */
class MockRepositoryAPI {
    public function branches($org, $repo, $branch) {
        // Return mock branch data as expected by GitHubPatch::construct
        return [
            'name' => $branch,
            'commit' => [
                'url' => "https://api.github.com/repos/$org/$repo/commits/mocksha123",
                'sha' => 'mocksha123',
            ],
        ];
    }
}

/**
 * Mock for repo->commits->compare
 */
class MockRepoAPI {
    public function __construct(
        private array $languagesByRepository = [],
        private array $participationByRepository = []
    ) {}

    public function show($owner, $repo) {
        return [
            'id' => 1296269,
            'name' => $repo,
            'full_name' => "$owner/$repo",
            'description' => 'Mock repository for testing',
            'language' => 'PHP',
            'stargazers_count' => 1000,
            'open_issues' => 50,
            'default_branch' => 'main',
            'topics' => ['php', 'testing'],
        ];
    }

    public function languages($owner, $repo) {
        $repository = "$owner/$repo";
        if (array_key_exists($repository, $this->languagesByRepository)) {
            return $this->languagesByRepository[$repository];
        }

        return [
            'PHP' => 50000,
            'JavaScript' => 30000,
            'HTML' => 20000,
        ];
    }

    public function participation($owner, $repo) {
        $repository = "$owner/$repo";
        if (array_key_exists($repository, $this->participationByRepository)) {
            return $this->participationByRepository[$repository];
        }

        return [
            'all' => array_fill(0, 52, 100),
            'owner' => array_fill(0, 52, 50),
        ];
    }

    public function commits() {
        return new MockCommitsAPI();
    }
}

class MockCommitsAPI {
    public function compare($org, $repo, $srcBranch, $repoBranch, $accept = null) {
        // If patch accept header, return a string
        if ($accept === 'application/vnd.github.patch') {
            return "diff --git a/file1.php b/file1.php\n+Added line\n-Removed line\n";
        }
        // Otherwise, return the array structure
        return [
            'commits' => [
                [
                    'sha' => 'mocksha123',
                    'author' => [ 'login' => 'mockuser' ],
                    'commit' => [
                        'author' => [
                            'name' => 'Mock User',
                            'email' => 'ist12345@tecnico.ulisboa.pt',
                        ],
                        'message' => 'Mock commit message for testing.',
                    ],
                ],
            ],
            'files' => [
                [
                    'filename' => 'file1.php',
                    'patch' => "+Added line\n-Removed line\n",
                    'additions' => 1,
                    'deletions' => 1,
                ],
            ],
        ];
    }
}

/**
 * Mock search API
 */
class MockSearchAPI {
    private ?array $customResults;

    public function __construct(
        private MockGitHubClient $client,
        ?array $customResults = null
    ) {
        $this->customResults = $customResults;
    }

    /**
     * Mock repositories() search
     * Returns realistic GitHub repository search results
     */
    public function repositories($query, $sort = null, $order = null) {
        $this->client->recordQuery($query);

        // If custom results were provided, use them even when intentionally empty.
        if ($this->customResults !== null) {
            return $this->customResults;
        }

        // Return default realistic mock data for various keywords
        if (strpos($query, 'api') !== false) {
            return $this->getMockApiRepositories();
        }
        if (strpos($query, 'test') !== false) {
            return $this->getMockTestRepositories();
        }
        if (strpos($query, 'help') !== false) {
            return $this->getMockHelpRepositories();
        }

        // Generic default response
        return ['items' => []];
    }

    /**
     * Mock repositories with "api" keyword
     */
    private function getMockApiRepositories() {
        return [
            'items' => [
                [
                    'full_name' => 'axios/axios',
                    'description' => 'Promise based HTTP client for the browser and node.js',
                    'html_url' => 'https://github.com/axios/axios',
                    'stargazers_count' => 105000,
                    'open_issues' => 285,
                    'language' => 'JavaScript',
                    'topics' => ['api', 'http', 'client', 'rest', 'xhr'],
                    'pushed_at' => date('c', strtotime('-5 days')),
                ],
                [
                    'full_name' => 'expressjs/express',
                    'description' => 'Fast, unopinionated, minimalist web framework for node.',
                    'html_url' => 'https://github.com/expressjs/express',
                    'stargazers_count' => 65000,
                    'open_issues' => 150,
                    'language' => 'JavaScript',
                    'topics' => ['api', 'web', 'framework', 'rest'],
                    'pushed_at' => date('c', strtotime('-3 days')),
                ],
                [
                    'full_name' => 'requests/requests',
                    'description' => 'A simple, yet elegant HTTP library for Python',
                    'html_url' => 'https://github.com/requests/requests',
                    'stargazers_count' => 52000,
                    'open_issues' => 220,
                    'language' => 'Python',
                    'topics' => ['api', 'http', 'client', 'requests'],
                    'pushed_at' => date('c', strtotime('-7 days')),
                ],
            ]
        ];
    }

    /**
     * Mock repositories with "test" keyword
     */
    private function getMockTestRepositories() {
        return [
            'items' => [
                [
                    'full_name' => 'jestjs/jest',
                    'description' => 'Delightful JavaScript Testing Framework',
                    'html_url' => 'https://github.com/jestjs/jest',
                    'stargazers_count' => 43000,
                    'open_issues' => 340,
                    'language' => 'JavaScript',
                    'topics' => ['test', 'testing', 'jest', 'javascript'],
                    'pushed_at' => date('c', strtotime('-2 days')),
                ],
                [
                    'full_name' => 'phpunit/phpunit',
                    'description' => 'The PHP Unit Testing framework',
                    'html_url' => 'https://github.com/phpunit/phpunit',
                    'stargazers_count' => 19000,
                    'open_issues' => 120,
                    'language' => 'PHP',
                    'topics' => ['test', 'testing', 'phpunit', 'framework'],
                    'pushed_at' => date('c', strtotime('-1 day')),
                ],
            ]
        ];
    }

    /**
     * Mock repositories with "help" keyword
     */
    private function getMockHelpRepositories() {
        return [
            'items' => [
                [
                    'full_name' => 'github/docs',
                    'description' => 'The open-source repo for github.com/docs',
                    'html_url' => 'https://github.com/github/docs',
                    'stargazers_count' => 16000,
                    'open_issues' => 45,
                    'language' => 'Markdown',
                    'topics' => ['help', 'documentation', 'github'],
                    'pushed_at' => date('c', strtotime('-1 day')),
                ],
            ]
        ];
    }
}

/**
 * Mock issue API
 */
class MockIssueAPI {
    public function show($owner, $repo, $number) {
        return [
            'number' => $number,
            'title' => 'Mock Issue #' . $number,
            'body' => 'This is a mock issue description for testing purposes.',
            'state' => 'open',
            'labels' => [['name' => 'bug']],
            'user' => ['login' => 'testuser'],
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
    }

    public function all($owner, $repo, $params = []) {
        return [
            [
                'number' => 1,
                'title' => 'First issue',
                'state' => 'open',
                'labels' => [['name' => 'good first issue']],
            ]
        ];
    }
}

/**
 * Mock user API
 */
class MockUserAPI {
    public function show($username) {
        return [
            'login' => $username,
            'id' => 123456,
            'name' => ucfirst($username) . ' User',
            'email' => $username . '@example.com',
            'company' => 'Tech Company',
            'location' => 'Lisbon, Portugal',
            'public_repos' => 15,
            'followers' => 100,
            'following' => 50,
        ];
    }
}

/**
 * Mock pull request API
 */
class MockPullRequestAPI {
    public function all($owner, $repo, $params = []) {
        return [
            [
                'number' => 1,
                'title' => 'Mock pull request',
                'state' => 'open',
                'merged_at' => null,
            ]
        ];
    }
}
