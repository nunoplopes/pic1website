<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

/** @Entity */
class Session
{
  /** @Id @Column(length=32) */
  public string $id;

  /** @ManyToOne */
  public User $user;

  /** @Column */
  public DateTimeImmutable $expires;

  public function __construct($user) {
    $this->id      = substr(sha1(random_bytes(64)), 0, 32);
    $this->user    = $user;
    $this->expires = (new DateTimeImmutable())->add(new DateInterval("P90D"));
  }

  public function isFresh() {
    return $this->expires >= new DateTimeImmutable();
  }
}
