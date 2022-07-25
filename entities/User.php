<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

/** @Entity */
class User
{
  /** @Id @Column(length=16) */
  public $id;

  /** @Column */
  public $name;

  /** @Column */
  public $email;

  /** @Column(type="integer") */
  // TODO: switch to enum with PHP 8
  public $role;

  /** @ManyToMany(targetEntity="ProjGroup", mappedBy="students", cascade={"persist"}) */
  public $groups;

  /** @Column */
  public $github_username = '';

  /** @Column */
  public $github_etag = '';

  public function __construct($username, $name, $email, $role) {
    $this->id     = $username;
    $this->name   = $name;
    $this->email  = $email;
    $this->role   = $role;
    $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
  }

  public function __toString() {
    return $this->id;
  }

  public function getShortName() {
    $names = explode(' ', $this->name);
    $first = $names[0];
    if ($first == 'Maria' && sizeof($names) > 2) {
      $first .= " $names[1]";
      if ($names[1] == 'de' && sizeof($names) > 3)
        $first .= " $names[2]";
    }
    return $first . ' ' . end($names);
  }
}
