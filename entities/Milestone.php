<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: "unique_milestone", columns: ['year', 'name'])]
class Milestone
{
  #[ORM\Id]
  #[ORM\Column]
  #[ORM\GeneratedValue]
  public int $id;

  #[ORM\Column]
  public int $year;

  #[ORM\Column]
  public string $name;

  #[ORM\Column]
  public string $description = '';

  #[ORM\Column]
  public string $page = '';

  #[ORM\Column]
  public bool $individual = false;

  #[ORM\Column]
  public string $field1 = '';

  #[ORM\Column]
  public int $points1 = 0;

  #[ORM\Column]
  public int $range1 = 0;

  #[ORM\Column]
  public string $field2 = '';

  #[ORM\Column]
  public int $points2 = 0;

  #[ORM\Column]
  public int $range2 = 0;

  #[ORM\Column]
  public string $field3 = '';

  #[ORM\Column]
  public int $points3 = 0;

  #[ORM\Column]
  public int $range3 = 0;

  #[ORM\Column]
  public string $field4 = '';

  #[ORM\Column]
  public int $points4 = 0;

  #[ORM\Column]
  public int $range4 = 0;

  public function __construct(int $year, string $name) {
    $this->year = $year;
    $this->name = $name;
  }
}
