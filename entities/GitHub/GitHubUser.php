<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/** @Entity */
class GitHubUser extends \RepositoryUser
{
  static function stats($username) {
    return $GLOBALS['github_client']->api('user')->show($username);
  }

  static function name($username) : ?string {
    return self::stats($username)['name'];
  }

  static function email($username) : ?string {
    return self::stats($username)['email'];
  }

  static function company($username) : ?string {
    return self::stats($username)['company'];
  }

  static function location($username) : ?string {
    return self::stats($username)['location'];
  }

  private function processEvents(&$events, $data) {
    foreach ($data as $event) {
      $id = (int)$event['id'];
      if ($id <= $this->last_processed_id)
        return false;

      $date = github_parse_date($event['created_at']);

      if ($event['type'] == 'PullRequestEvent') {
        if ($event['payload']['action'] == 'opened') {
          if ($repo = db_fetch_repo('GitHub', $event['repo']['name'])) {
            $pr = new GitHubPullRequest($repo, $event['payload']['number']);
            $events[] = new \PROpenedEvent($pr, $date);
          }
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

  public function getUnprocessedEvents() : array {
    global $github_client;

    // ask github for events we haven't seen yet
    github_set_etag($this->etag);

    $api       = $github_client->user('user');
    $paginator = new \Github\ResultPager($github_client);
    $data      = $paginator->fetch($api, 'events', [$this->username]);

    $response = $github_client->getLastResponse();
    if ($response->getStatusCode() == 304) {
      // no new events
      return [];
    }

    $this->etag = $response->getHeader('etag')[0];
    $last_id = $data ? (int)$data[0]['id'] : $this->last_processed_id;

    $events = [];
    while ($this->processEvents($events, $data) && $paginator->hasNext()) {
      $data = $paginator->fetchNext();
    }

    $this->last_processed_id = $last_id;
    return $events;
  }
}
