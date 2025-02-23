<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

/** @Entity */
class Grade
{
  /** @Id @ManyToOne */
  public User $user;

  /** @Id @ManyToOne */
  public Milestone $milestone;

  /** @Column(nullable=true) */
  public ?int $field1;

  /** @Column(nullable=true) */
  public ?int $field2;

  /** @Column(nullable=true) */
  public ?int $field3;

  /** @Column(nullable=true) */
  public ?int $field4;

  /** @Column */
  public int $late_days = 0;
}
