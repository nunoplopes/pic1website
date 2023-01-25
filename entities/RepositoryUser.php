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
  public string $id;

  /** @Column */
  public string $etag = '';

  /** @Column */
  public int $last_processed_id = 0;

  public function username() {
    return substr($this->id, strpos($this->id, ':')+1);
  }

  public function platform() {
    return substr($this->id, 0, strpos($this->id, ':'));
  }

  public function name() : ?string {

  }

  public function email() : ?string {

  }

  public function company() : ?string {

  }

  public function location() : ?string {

  }

  public function getUnprocessedEvents() : array {

  }

  static function factory($id) {
    $ps = explode(':', $id);
    if (count($ps) != 2)
      throw new ValidationException('Allowed syntax is: provider:username '.
                                    '(e.g., github:johnsmith)');

    if (db_fetch_repo_user($id))
      throw new \ValidationException('Username already in use by another user');

    $r = new RepositoryUser();
    $r->id = $id;

    if ($r->platform() != 'github')
      throw new ValidationException('unknown platform');

    // check if user exists
    try {
      $r->name();
    } catch (\Exception $ex) {
      return null;
    }
    db_save($r);
    return $r;
  }

  public function __toString() {
    return $this->id;
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
    return $this->platform() . ": " . $this->username() . $extra;
  }

  static function userCanCreate() { return true; }
}
