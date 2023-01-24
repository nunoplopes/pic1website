<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
abstract class Repository
{
  /** @Id @Column(length=255) */
  public string $name;

  abstract public function defaultBranch() : string;
  abstract public function parent() : ?string;
  abstract public function language() : ProgLanguage;
  abstract public function license() : ?License;
  abstract public function stars() : int;
  abstract public function topics() : array;
  abstract public function commitsLastMonth() : int;
  abstract public function __toString() : string;

  static function factory($url) : ?Repository {
    if ($r = GitHub\GitHubRepository::construct($url))
      return $r;
    return null;
  }

  static function userCanCreate() {
    return true;
  }
}
