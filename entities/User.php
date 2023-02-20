<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;

/** @Entity */
class User
{
  /** @Id @Column(length=16) */
  public $id;

  /** @Column */
  public $name;

  /** @Column */
  public $email;

  /** @Column(type="text") */
  public $photo;

  /** @Column(type="integer") */
  // TODO: switch to enum with PHP 8
  public $role;

  /** @ManyToMany(targetEntity="ProjGroup", mappedBy="students", cascade={"persist"}) */
  public $groups;

  /** @Column */
  public $repository_user = '';

  /** @Column */
  public $repository_etag = '';

  /** @Column(type="integer") */
  public $repository_last_processed_id = 0;

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

  function getRole() {
    return get_all_roles(true)[$this->role];
  }

  public function getPhoto() {
    return $this->photo ? $this->photo
             : "https://fenix.tecnico.ulisboa.pt/user/photo/$this->id";
  }

  public function getRepoUser() {
    return $this->repository_user ? new RepositoryUser($this) : null;
  }

  public function isDummy() {
    return str_starts_with($this->id, 'ist0000');
  }

  public function __toString() {
    return $this->id;
  }

  public function set_repository_user($id) {
    $this->repository_user = $id;
    try {
      RepositoryUser::check($this);

      if (($group = $this->getGroup()) &&
          ($repo = $group->getRepository()) &&
          $this->getRepoUser()->platform() != $repo->platform())
        throw new ValidationException("User's and group's platforms don't match");
    } catch (ValidationException $ex) {
      $this->repository_user = '';
      throw $ex;
    }
  }
}
