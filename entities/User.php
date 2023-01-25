<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\OneToOne;

/** @Entity */
class User
{
  /** @Id @Column(length=16) */
  public string $id;

  /** @Column */
  public string $name;

  /** @Column */
  public string $email;

  /** @Column(type="text") */
  public string $photo;

  /** @Column */
  // TODO: switch to enum with PHP 8
  public int $role;

  /** @ManyToMany(targetEntity="ProjGroup", mappedBy="students", cascade={"persist"}) */
  public $groups;

  /** @OneToOne */
  public ?RepositoryUser $repository_user;

  public function __construct($username, $name, $email, $photo, $role, $dummy) {
    $this->id     = $username;
    $this->name   = $name;
    $this->email  = $email;
    $this->photo  = $photo;
    $this->role   = $role;
    $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
    assert($this->isDummy() == $dummy);
  }

  public function shortName() {
    $names = explode(' ', $this->name);
    return $names[0] . ' ' . end($names);
  }

  public function roleAtLeast($role) {
    return auth_user_at_least($this, $role);
  }

  public function getGroup() : ?ProjGroup {
    foreach ($this->groups as $group) {
      if ($group->year == get_current_year())
        return $group;
    }
    return null;
  }

  public function getPhoto() {
    return $this->photo ? $this->photo
             : "https://fenix.tecnico.ulisboa.pt/user/photo/$this->id";
  }

  public function isDummy() {
    return str_starts_with($this->id, 'ist0000');
  }

  public function __toString() {
    return $this->id;
  }

  public function set_repository_user($txt) {
    $this->repository_user = RepositoryUser::factory($txt);
    if (!$this->repository_user)
      throw new ValidationException('unknown user');
  }
}
