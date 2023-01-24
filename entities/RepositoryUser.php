<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

class PROpenedEvent {
  public PullRequest $pr;
  public \DateTimeImmutable $date;

  public function __construct($pr, $date) {
    $this->pr   = $pr;
    $this->date = $date;
  }
}


/** @Entity */
abstract class RepositoryUser
{
  /** @Id @Column(length=255) */
  public string $username;

  abstract public function platform() : string;
  abstract public function name() : ?string;
  abstract public function email() : ?string;
  abstract public function company() : ?string;
  abstract public function location() : ?string;
  abstract public function getUnprocessedEvents() : array;

  public function __toString() {
    return $this->username;
  }

  public function description() {
    $data = [
      "name"     => $this->name(),
      "email"    => $this->email(),
      "company"  => $this->company(),
      "location" => $this->location(),
    ];
    $extra = '';
    foreach ($data as $k => $v) {
      if ($v)
        $extra .= " [$k: $v]";
    }
    return $this->platform() . ": " . $this->username . $extra;
  }
}
