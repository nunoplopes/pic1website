<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class License
{
  /** @Id @Column(length=48) */
  public $id;

  /** @Column */
  public $name;

  public function __construct($id, $name) {
    $this->id   = $id;
    $this->name = $name;
  }

  public function __toString() {
    return $this->name;
  }

  static function orderBy() {
    return 'name';
  }
}
