<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

class GitHubIssue extends \Issue
{
  private string $org;
  private string $repo;
  private int    $number;

  private function __construct(string $org, string $repo, int $number) {
    $this->org    = $org;
    $this->repo   = $repo;
    $this->number = $number;
  }

  public static function factory($url) : ?\Issue {
    if (preg_match('@^https://github.com/([^/]+)/([^/]+)/issues/(\d+)@', $url, $m)) {
      return new GitHubIssue($m[1], $m[2], (int)$m[3]);
    }
    return null;
  }

  private function data() : array {
    global $github_client_cached;
    $issues = $github_client_cached->api('issue');
    return $issues->show($this->org, $this->repo, $this->number);
  }

  public function getTitle() : string {
    return $this->data()['title'];
  }

  public function getDescription() : string {
    return $this->data()['body'];
  }
}
