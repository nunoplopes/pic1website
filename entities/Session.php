<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Session
{
  #[ORM\Id]
  #[ORM\Column(length: 32)]
  public string $id;

  #[ORM\ManyToOne]
  public User $user;

  #[ORM\Column]
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
