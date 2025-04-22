<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

class GitHubUser implements \RepositoryUserInterface
{
  static function stats(\RepositoryUser $user) {
    try {
      return $GLOBALS['github_client']->api('user')->show($user->username());
    } catch (\Github\Exception\RuntimeException $e) {
      return [];
    }
  }

  static function isValid(\RepositoryUser $user) : bool {
    try {
      $GLOBALS['github_client']->api('user')->show($user->username());
      return true;
    } catch (\Github\Exception\RuntimeException $e) {
      return $e->getMessage() == 'Not Found' ? false : true;
    }
  }

  static function profileURL(\RepositoryUser $user) : string {
    return 'https://github.com/' . $user->username();
  }

  static function name(\RepositoryUser $user) : ?string {
    return self::stats($user)['name'] ?? null;
  }

  static function email(\RepositoryUser $user) : ?string {
    return self::stats($user)['email'] ?? null;
  }

  static function company(\RepositoryUser $user) : ?string {
    return self::stats($user)['company'] ?? null;
  }

  static function location(\RepositoryUser $user) : ?string {
    return self::stats($user)['location'] ?? null;
  }

  static function processEvents(&$events, $user, $data) {
    foreach ($data as $event) {
      $id = (int)$event['id'];
      if ($id <= $user->repository_last_processed_id)
        return false;

      $date = github_parse_date($event['created_at']);

      if ($event['type'] == 'PullRequestEvent') {
        if ($event['payload']['action'] == 'opened' ||
            $event['payload']['action'] == 'reopened') {
          $repo = new \Repository('github:' . $event['repo']['name']);
          $pr   = new GitHubPullRequest($repo, $event['payload']['number']);
          $events[] = new \PROpenedEvent($pr, $date);
        }
      } else if ($event['type'] == 'IssuesEvent') {
        /*
        if ($event['payload']['action'] == 'opened')
          $events[] = new IssueOpenedEvent($event['repo']['name'],
                                           $event['payload']['issue']['number'],
                                           $date);
        */
      }
    }
    return true;
  }

  static function getUnprocessedEvents(\RepositoryUser $r) : array {
    global $github_client;

    // ask github for events we haven't seen yet
    $user = $r->user;
    github_set_etag($user->repository_etag);

    try {
      $api       = $github_client->user('user');
      $paginator = new \Github\ResultPager($github_client);
      $data      = $paginator->fetch($api, 'events', [$r->username()]);

      $response = $github_client->getLastResponse();
      $user->repository_etag = $response->getHeader('etag')[0];
      $last_id
        = $data ? (int)$data[0]['id'] : $user->repository_last_processed_id;

      $events = [];
      while (self::processEvents($events, $user, $data) &&
             $paginator->hasNext()) {
        $data = $paginator->fetchNext();
      }

      github_remove_etag();
      $user->repository_last_processed_id = $last_id;
      // returns events in cronological order
      return array_reverse($events);

    } catch (\Github\Exception\RuntimeException $e) {
      return [];
    }
  }
}
