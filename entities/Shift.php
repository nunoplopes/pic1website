<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Shift
{
  #[ORM\Id]
  #[ORM\Column]
  #[ORM\GeneratedValue]
  public int $id;

  #[ORM\Column]
  public string $name;

  #[ORM\Column]
  public int $year;

  #[ORM\ManyToOne]
  public ?User $prof;

  #[ORM\OneToMany(targetEntity: "ProjGroup", mappedBy: "shift")]
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
