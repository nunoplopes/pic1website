<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/** @Entity */
class GitHubPatch extends \Patch
{
  /** @Column */
  public string $repo_branch;

  /** @Column */
  public int $pr_number = 0;

  static function construct($url, $group) {
    if (preg_match('@^https://github.com/([^/]+/[^/]+)/compare/([^.]+)...([^:]+:[^:]+:[^:]+)$@', $url, $m)) {
      $src_repo   = $m[1]; // user/repo
      $src_branch = $m[2];
      $tgt_repo   = $m[3];

      if ($src_repo != $group->repository->name)
        throw new \ValidationException("Patch is not for Project's repository");

      if ($src_branch != $group->repository->defaultBranch())
        throw new \ValidationException("Patch is not against default branch");
    }
    elseif (preg_match('@^https://github.com/([^/]+/[^/]+)/tree/([^/]+)$@', $url, $m)) {
      $tgt_repo = strtr($m[1], '/', ':') . $m[2];
    } else {
      return null;
    }

    $p = new GitHubPatch;
    $p->repo_branch = $tgt_repo;
    return $p;
  }

  private function stats() {
    [$org, $repo] = $this->group->repository->getRepo();
    $c = $GLOBALS['github_client']->api('repo')->commits();
    return $c->compare($org, $repo, $this->group->repository->defaultBranch(),
                       $this->repo_branch);
  }

  public function authors() : array {
    $authors = [];
    foreach ($this->stats()['commits'] as $commit) {
      $authors[$commit['author']['login']] = true;
    }
    return array_keys($authors);
  }

  public function linesAdded() : int {
    $add = 0;
    foreach ($this->stats()['files'] as $f) {
      $add += $f->additions;
    }
    return $add;
  }

  public function linesRemoved() : int {
    $del = 0;
    foreach ($this->stats()['files'] as $f) {
      $del += $f->deletions;
    }
    return $del;
  }

  public function filesModified() : int {
    return count($this->stats()['files']);
  }

  public function getURL() : string {
    return "https://github.com/" .
            $this->group->repository->name .
            "/compare/" .
            $this->group->repository->defaultBranch() . "..." .
            $this->repo_branch;
  }

  public function setPR(\PullRequest $pr) {
    $this->pr_number = $pr->number;
  }

  public function getPR() : ?\PullRequest {
    if ($this->pr_number == 0)
      return null;
    return new GitHubPullRequest($this->group->repository, $this->pr_number);
  }
}