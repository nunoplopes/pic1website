<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/** @Entity
 *  @Table(name="Milestone",
 *    uniqueConstraints={
 *      @UniqueConstraint(name="unique_milestone", columns={"year", "name"}),
 *    }
 *  )
*/
class Milestone
{
  /** @Id @Column @GeneratedValue */
  public int $id;

  /** @Column */
  public int $year;

  /** @Column */
  public string $name;

  /** @Column */
  public string $page = '';

  /** @Column */
  public string $field1 = '';

  /** @Column */
  public int $points1 = 0;

  /** @Column */
  public int $range1 = 0;

  /** @Column */
  public string $field2 = '';

  /** @Column */
  public int $points2 = 0;

  /** @Column */
  public int $range2 = 0;

  /** @Column */
  public string $field3 = '';

  /** @Column */
  public int $points3 = 0;

  /** @Column */
  public int $range3 = 0;

  /** @Column */
  public string $field4 = '';

  /** @Column */
  public int $points4 = 0;

  /** @Column */
  public int $range4 = 0;

  public function __construct(int $year, string $name) {
    $this->year = $year;
    $this->name = $name;
  }
}
