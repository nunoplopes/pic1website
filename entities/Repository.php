<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class Repository
{
  /** @Id @Column(length=255) */
  public string $id;

  public function name() {
    return substr($this->id, strpos($this->id, ':')+1);
  }

  public function platform() {
    return substr($this->id, 0, strpos($this->id, ':'));
  }

  private function get($fn) {
    $n = $this->name();
    switch ($this->platform()) {
      case 'github': return GitHub\GitHubRepository::$fn($n);
    }
    assert(false);
  }

  public function defaultBranch() : string {
    return $this->get('defaultBranch');
  }

  public function parent() : ?string {
    return $this->get('parent');
  }

  public function language() : ProgLanguage {
    return $this->get('language');
  }

  public function license() : ?License {
    return $this->get('license');
  }

  public function stars() : int {
    return $this->get('stars');
  }

  public function topics() : array {
    return $this->get('topics');
  }

  public function commitsLastMonth() : int {
    return $this->get('commitsLastMonth');
  }

  public function __toString() : string {
    return $this->get('toString');
  }

  static function factory($url) : ?Repository {
    if ($name = GitHub\GitHubRepository::parse($url)) {
      $name = "github:$name";
    } else {
      return null;
    }

    if ($r = db_fetch_repo($name))
      return $r;

    $r = new Repository();
    $r->id = $name;

    // check if repo exists
    try {
      $r->defaultBranch();
    } catch (\Exception $ex) {
      return null;
    }
    db_save($r);
    return $r;
  }

  static function userCanCreate() {
    return true;
  }
}
