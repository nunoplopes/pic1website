<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

class GitHubPullRequest extends \PullRequest
{
  private int $number;

  public function __construct(\Repository $repository, $number) {
    $this->repository = $repository;
    $this->number     = $number;
  }

  private function stats() {
    [$org, $repo] = GitHubRepository::getRepo($this->repository->name());
    return $GLOBALS['github_client']->api('pr')
                                    ->show($org, $repo, $this->number);
  }

  public function comments() : array {
    [$org, $repo] = GitHubRepository::getRepo($this->repository->name());
    return
      $GLOBALS['github_client']->api('issue')
                               ->comments()->all($org, $repo, $this->number);
  }

  public function labels() : array {
    [$org, $repo] = GitHubRepository::getRepo($this->repository->name());
    return
      $GLOBALS['github_client']->api('issue')
                               ->labels()->all($org, $repo, $this->number);
  }

  public function has_label($label) : bool {
    foreach (self::labels() as $l) {
      if ($l['name'] == $label)
        return true;
    }
    return false;
  }

  private function did_bot_merge($login, $txt) : bool {
    $bots = [
      // (bot username, comment string, label)
      ['gopherbot',       ' has been merged.', null],
      ['pytorchmergebot', '### Merge started', 'Merged'],
    ];
    foreach ($bots as $data) {
      [$bot, $msg, $label] = $data;
      if ($login == $bot &&
          strpos($txt, $msg) !== false &&
          (!$label || $this->has_label($label)))
        return true;
    }
    return false;
  }

  public function url() : string {
    //return $this->stats()['html_url'];
    return 'https://github.com/' . $this->repository->name() . '/pull/' .
           $this->number;
  }

  public function branchURL() : string {
    $data = $this->stats();
    return 'https://github.com/' . $data['head']['repo']['full_name'] .
           '/tree/' . $data['head']['ref'];
  }

  public function origin() : string {
    $data = $this->stats();
    return strtr($data['head']['repo']['full_name'], '/', ':') . ':' .
           $data['head']['ref'];
  }

  public function isClosed() : bool {
    return $this->stats()['state'] == 'closed';
  }

  public function wasMerged() : bool {
    if ($this->stats()['merged'])
      return true;

    if ($this->isClosed()) {
      foreach ($this->comments() as $c) {
        if ($this->did_bot_merge($c['user']['login'], $c['body']))
          return true;
      }
    }
    return false;
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

  public function linesDeleted() : int {
    return $this->stats()['deletions'];
  }

  public function filesModified() : int {
    return $this->stats()['changed_files'];
  }

  public function failedCIjobs(string $hash) : array {
    [$org, $repo] = GitHubRepository::getRepo($this->repository->name());
    $status  = $GLOBALS['github_client']->api('repo')->statuses();
    $actions = $GLOBALS['github_client']->api('repo')->workflowRuns();
    $jobs    = $GLOBALS['github_client']->api('repo')->workflowJobs();

    $failed = [];

    // get status of 3rd-party checks
    foreach ($status->combined($org, $repo, $hash)['statuses'] as $check) {
      if ($check['state'] == 'failure') {
        $failed[] = [
          'name' => $check['context'],
          'time' => github_parse_date($check['updated_at'])
        ];
      }
    }

    // get status of GitHub Actions checks
    $runs = $actions->all($org, $repo, ['head_sha' => $hash]);
    foreach ($runs['workflow_runs'] as $check) {
      if ($check['conclusion'] == 'failure') {
        // Each action can have multiple jobs
        // Fetch the names of the specific jobs that failed
        foreach ($jobs->all($org, $repo, $check['id'])['jobs'] as $job) {
          if ($job['conclusion'] == 'failure') {
            $failed[] = [
              'name' => $job['name'],
              'time' => github_parse_date($job['completed_at'])
            ];
          }
        }
      }
    }
    return $failed;
  }

  public function getNumber() {
    return $this->number;
  }

  public function __toString() {
    return 'GitHub PR ' . $this->repository->name() . '#' . $this->number;
  }
}
