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

  static function construct($url, \Repository $repository) {
    if (preg_match('@^https://github.com/([^/]+/[^/]+)/compare/([^.]+)...([^:]+):([^:]+):(.+)$@', $url, $m)) {
      $src_repo   = $m[1]; // user/repo
      $src_branch = $m[2];
      $org        = $m[3];
      $repo       = $m[4];
      $branch     = $m[5];

      if ($src_repo != $repository->name())
        throw new \ValidationException("Patch is not for Project's repository");

      if ($src_branch != $repository->defaultBranch())
        throw new \ValidationException("Patch is not against default branch");
    }
    elseif (preg_match('@^https://github.com/([^/]+)/([^/]+)/tree/(.+)$@', $url, $m)) {
      $org    = $m[1];
      $repo   = $m[2];
      $branch = $m[3];
    } else {
      throw new \ValidationException('Unknown patch URL format');
    }

    // canonicalize repo data and check if branch exists
    try {
      $github = $GLOBALS['github_client']->api('repository');
      $data = $github->branches(urldecode($org), urldecode($repo),
                                urldecode($branch));
      if (!preg_match('@https://api.github.com/repos/([^/]+)/([^/]+)/@',
                      $data['commit']['url'], $m)) {
        throw new \ValidationException("Couldn't parse github commit URL");
      }
      $p = new GitHubPatch;
      $p->repo_branch = $m[1] . ':' . $m[2] . ':' . $data['name'];
      return $p;
    } catch (\Github\Exception\RuntimeException $ex) {
      throw new \ValidationException("Non-existent patch");
    }
  }

  public function isValid() : bool {
    try {
      $this->stats();
      return true;
    } catch (\Github\Exception\RuntimeException $ex) {
      // may be just a transient failure
      return $ex->getMessage() !== 'Not Found';
    }
  }

  public function origin() : string {
    return $this->repo_branch;
  }

  private function stats() {
    $r = $this->group->getRepository();
    [$org, $repo] = GitHubRepository::getRepo($r->name());
    $c = $GLOBALS['github_client']->api('repo')->commits();
    return $c->compare($org, $repo, $r->defaultBranch(),
                       $this->repo_branch);
  }

  public function computeAuthors() : array {
    $authors = [];
    foreach ($this->stats()['commits'] as $commit) {
      $authors[] = [$commit['author']['login'] ?? '',
                    $commit['commit']['author']['name'],
                    $commit['commit']['author']['email']];

      preg_match_all('/^Co-authored-by: ([^<]+) <([^>]+)>$/Sm',
                     $commit['commit']['message'], $m, PREG_SET_ORDER);
      foreach ($m as $author) {
        $authors[] = ['', $author[1], $author[2]];
      }
    }
    return array_unique($authors, SORT_REGULAR);
  }

  protected function computeLinesAdded() : int {
    $add = 0;
    foreach ($this->stats()['files'] as $f) {
      $add += $f['additions'];
    }
    return $add;
  }

  protected function computeLinesDeleted() : int {
    $del = 0;
    foreach ($this->stats()['files'] as $f) {
      $del += $f['deletions'];
    }
    return $del;
  }

  protected function computeFilesModified() : int {
    return count($this->stats()['files']);
  }

  public function getPatchURL() : string {
    $repo = $this->group->getRepository();
    return "https://github.com/" .
            $repo->name() .
            "/compare/" .
            urlencode($this->repo_branch);
  }

  public function setPR(\PullRequest $pr) {
    $this->pr_number = $pr->getNumber();
  }

  public function getPR() : ?\PullRequest {
    if ($this->pr_number == 0)
      return null;
    return new GitHubPullRequest($this->group->getRepository(),
                                 $this->pr_number);
  }
}
