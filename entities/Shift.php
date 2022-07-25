<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

/** @Entity */
class Shift
{
  /** @Id @Column(type="integer") @GeneratedValue */
  public $id;

  /** @Column */
  public $name;

  /** @Column(type="integer") */
  public $year;

  /** @Column(nullable="yes") @ManyToOne(targetEntity="User") */
  public $prof;

  /** @OneToMany(targetEntity="ProjGroup", mappedBy="shift") */
  public $groups;

  public function __construct($name, $year) {
    $this->name   = $name;
    $this->year   = $year;
    $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
  }

  public function addGroup($group) {
    $this->groups->add($group);
  }

  public function __toString() {
    return $this->name;
  }
}
