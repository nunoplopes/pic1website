<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

/** @Entity */
class License
{
  /** @Id @Column(length=32) */
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
}
