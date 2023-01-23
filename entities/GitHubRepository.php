<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class GitHubRepository extends Repository
{
  /** @Id @Column(type="string", length=255) */
  public $name;

  static function construct($url) {
    if (!preg_match('@^https://github.com/([^/]+/[^/]+)/?$@', $url, $m))
      return null;
    if ($r = db_fetch_github($m[1]))
      return $r;

    $r = new GitHubRepository();
    $r->name = $m[1];
    // check if repo exists
    try {
      $r->defaultBranch();
    } catch (Exception $ex) {
      return null;
    }
    db_save($r);
    return $r;
  }

  private function getRepo() {
    return explode('/', $this->name);
  }

  private function stats() {
    [$org, $repo] = $this->getRepo();
    return $GLOBALS['github_client']->api('repo')->show($org, $repo);
  }

  public function defaultBranch() : string {
    return $this->stats()['default_branch'];
  }

  public function parent() : ?string {
    $data = $this->stats();
    return isset($data['parent']) ? $data['parent']['full_name'] : null;
  }

  public function language() : ProgLanguage {
    return db_fetch_prog_language($this->stats()['language']);
  }

  public function license() : ?License {
    return db_fetch_license($this->stats()['license']['spdx_id']);
  }

  public function stars() : int {
    return $this->stats()['stargazers_count'];
  }

  public function topics() : array {
    return $this->stats()['topics'];
  }

  public function commitsLastMonth() : int {
    [$org, $repo] = $this->getRepo();
    $data = $GLOBALS['github_client']->api('repo')->participation($org, $repo);
    return array_sum(array_slice($data['all'], -4));
  }

  public function __toString() : string {
    return 'https://github.com/' . $this->name;
  }
}
