<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class GitHubPatch extends \Patch
{
  #[ORM\Column]
  public string $repo_branch;

  #[ORM\Column]
  public int $pr_number = 0;

  static function construct($url, \Repository $repository) {
    if (preg_match('@^https://github.com/([^/]+/[^/]+)/compare/(.+)\.\.\.([^:]+):([^:]+):(.+)$@', $url, $m)) {
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
    elseif (preg_match('@^https://github.com/([^/]+/[^/]+)/compare/([^:]+):([^:]+):(.+)$@', $url, $m)) {
      $src_repo   = $m[1]; // user/repo
      $org        = $m[2];
      $repo       = $m[3];
      $branch     = $m[4];

      if ($src_repo != $repository->name())
        throw new \ValidationException("Patch is not for Project's repository");
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

  public function branch() : string {
    return explode(':', $this->repo_branch)[2];
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

  public function commits() : array {
    $commits = [];
    foreach ($this->stats()['commits'] as $commit) {
      $commit['username'] = $commit['author']['login'] ?? '';
      $commit['name']     = $commit['commit']['author']['name'];
      $commit['email']    = $commit['commit']['author']['email'];
      $commit['message']  = $commit['commit']['message'];
      $commit['hash']     = $commit['sha'];

      $authors = [];
      preg_match_all('/^Co-authored-by: ([^<]+) <([^>]+)>$/Sm',
                     $commit['message'], $m, PREG_SET_ORDER);
      foreach ($m as $author) {
        $authors[] = ['', $author[1], $author[2]];
      }
      $commit['co-authored'] = array_unique($authors, SORT_REGULAR);

      $commits[] = $commit;
    }
    return $commits;
  }

  protected function computeBranchHash() : string {
    $hash = '';
    foreach ($this->stats()['commits'] as $commit) {
      $hash = $commit['sha'];
    }
    return $hash;
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

  public function getCommitURL(string $hash) : string {
    $repo = $this->group->getRepository();
    return 'https://github.com/' . $repo->name() . '/commit/' . $hash;
  }

  public function setPR(\PullRequest $pr) {
    $this->pr_number = $pr->getNumber();
  }

  public function findAndSetPR() : bool {
    $origin = explode(':', $this->repo_branch);
    try {
      $prs = $GLOBALS['github_client']->api('repo')->commits()
               ->pulls($origin[0], $origin[1], $this->hash);
    } catch (\Github\Exception\RuntimeException) {
      // ignore temporary errors or deleted commit
      return false;
    }

    if ($this->type == \PatchType::BugFix) {
      $usernames = [$this->getSubmitter()];
    } else {
      assert($this->type == \PatchType::Feature);
      $usernames = $this->group->students;
    }
    $usernames
      = array_map(fn($u) => $u->getRepoUser()?->username(), $usernames);

    $changed = false;
    foreach ($prs as $pr) {
      preg_match('@https://github.com/(.+)/pull/\d+@', $pr['html_url'], $m);
      if ($m[1] === $this->group->getRepository()->name() &&
          in_array($pr['user']['login'], $usernames, true) &&
          $pr['number'] > $this->pr_number) {
        $this->pr_number = $pr['number'];
        $changed = true;
      }
    }
    return $changed;
  }

  public function getPR() : ?\PullRequest {
    if ($this->pr_number == 0)
      return null;
    return new GitHubPullRequest($this->group->getRepository(),
                                 $this->pr_number);
  }
}
