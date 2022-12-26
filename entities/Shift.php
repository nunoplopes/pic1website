<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

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
