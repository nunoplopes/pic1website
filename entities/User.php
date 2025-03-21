<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
  #[ORM\Id]
  #[ORM\Column(length: 16)]
  public string $id;

  #[ORM\Column]
  public string $name;

  #[ORM\Column]
  public string $email;

  #[ORM\Column(type: 'text')]
  public string $photo;

  #[ORM\Column]
  // TODO: switch to enum with PHP 8
  public int $role;

  #[ORM\ManyToMany(mappedBy: 'students', targetEntity: 'ProjGroup', cascade: ['persist'])]
  #[ORM\OrderBy(['year' => 'DESC'])]
  public $groups;

  #[ORM\Column]
  public string $repository_user = '';

  #[ORM\Column]
  public string $repository_etag = '';

  #[ORM\Column(type: 'bigint')]
  public string $repository_last_processed_id = '0';

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
    if (!$this->groups->isEmpty())
      return $this->groups[0];
    return null;
  }

  public function getYear() : ?int {
    $group = $this->getGroup();
    return $group ? $group->year : null;
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
          ($repo = $group->getValidRepository()) &&
          $this->getRepoUser()->platform() != $repo->platform())
        throw new ValidationException("User's and group's platforms don't match");
    } catch (ValidationException $ex) {
      $this->repository_user = '';
      throw $ex;
    }
  }
}
