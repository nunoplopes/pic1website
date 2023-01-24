<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

class PullRequest extends \PullRequest
{
  private string $repo_name;
  private int $number;

  public function __construct($repo_name, $number) {
    $this->repo_name = $repo_name;
    $this->number    = $number;
  }

  private function getRepo() {
    return explode('/', $this->repo_name);
  }

  private function stats() {
    [$org, $repo] = $this->getRepo();
    return $GLOBALS['github_client']->api('pr')
                                    ->show($org, $repo, $this->number);
  }

  public function origin() : string {
    $data = $this->stats();
    return $data['head']['repo']['full_name'] . ':' . $data['head']['ref'];
  }

  public function isClosed() : bool {
    return $this->stats()['state'] == 'closed';
  }

  public function wasMerged() : bool {
    return $this->stats()['merged'];
  }

  public function mergedBy() : string {
    return $this->stats()['merged_by']['login'];
  }

  public function mergeDate() : \DateTimeImmutable {
    return github_parse_date($this->stats()['merged_at']);
  }

  public function linesAdded() : int {
    return $this->stats()['additions'];
  }

  public function linesRemoved() : int {
    return $this->stats()['deletions'];
  }

  public function filesModified() : int {
    return $this->stats()['changed_files'];
  }

  public function __toString() {
    return 'GitHub PR ' . $this->repo_name . '#' . $this->number;
  }
}
