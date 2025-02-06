<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

/** @Entity */
class PatchComment
{
  /** @Id @Column @GeneratedValue */
  public int $id;

  /** @ManyToOne
   *  @JoinColumn(nullable=false)
   */
  public Patch $patch;

  /** @Column(length=4096) */
  public string $text;

  /** @ManyToOne */
  public ?User $user;

  /** @Column */
  public DateTimeImmutable $time;

  public function __construct(Patch $patch, string $text, ?User $user = null) {
    $this->patch = $patch;
    $this->text  = $text;
    $this->user  = $user;
    $this->time  = new DateTimeImmutable();
  }
}
