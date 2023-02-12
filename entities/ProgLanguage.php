<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class ProgLanguage
{
  /** @Id @Column(length=16) */
  public string $id;

  public function __construct($name) {
    $this->id = $name;
  }

  public function __toString() {
    return $this->id;
  }
}
