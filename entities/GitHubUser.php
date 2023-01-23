<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/** @Entity */
class GitHubUser extends RepositoryUser
{
  /** @Column */
  public $etag = '';

  /** @Column(type="integer") */
  public $last_processed_id = 0;

  static function construct($username) {
    $r = new GitHubUser();
    $r->username = $username;
    // check if user exists
    try {
      $r->name();
    } catch (Exception $ex) {
      return null;
    }
    db_save($r);
    return $r;
  }

  private function stats() {
    return $GLOBALS['github_client']->api('user')->show($this->username);
  }

  public function platform() : string {
    return 'github';
  }

  public function name() : ?string {
    return $this->stats()['name'];
  }

  public function email() : ?string {
    return $this->stats()['email'];
  }

  public function company() : ?string {
    return $this->stats()['company'];
  }

  public function location() : ?string {
    return $this->stats()['location'];
  }

  private function processEvents($events, $data) {
    foreach ($data as $d) {
      $date = parse_date($event->created_at);
      if ($event->type == 'PullRequestEvent') {
        if ($event->payload->action == 'opened')
          $opened_prs[] = [$event->repo->name, $event->payload->number, $date];
      } else if ($event->type == 'IssuesEvent') {
        if ($event->payload->action == 'opened')
          $opened_issues[] = [$event->repo->name, $event->payload->issue->number,
                              $date];
      }
    }
    exit();
  }

  public function getUnprocessedEvents() : array {
    global $github_client;

    // ask github for events we haven't seen yet
    github_set_etag($this->etag);

    $api       = $github_client->user('user');
    $paginator = new Github\ResultPager($github_client);
    $result    = $paginator->fetch($api, 'events', [$this->username]);

    $response = $github_client->getLastResponse();
    if ($response->getStatusCode() == 304) {
      // no new events
      return [];
    }

    $this->etag = $response->getHeader('etag')[0];

    $events = [];
    $this->processEvents($events, $result);

    while ($paginator->hasNext()) {
      $this->processEvents($events, $paginator->fetchNext());
    }
    return $events;
  }
}
