<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

/** @Entity */
class Session
{
  /** @Id @Column(length=32) */
  public $id;

  /** @ManyToOne(targetEntity="User") */
  public $user;

  /** @Column(type="datetime") */
  public $expires;

  public function __construct($user) {
    $this->id      = substr(sha1(random_bytes(64)), 0, 32);
    $this->user    = $user;
    $this->expires = (new DateTime())->add(new DateInterval("P90D"));
  }

  public function isFresh() {
    return $this->expires >= new DateTime();
  }
}