<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

/** @Entity */
class ProgLanguage
{
  /** @Id @Column(length=16) */
  public $id;

  public function __construct($name) {
    $this->id = $name;
  }

  public function __toString() {
    return $this->id;
  }

  static function orderBy() {
    return 'id';
  }
}
