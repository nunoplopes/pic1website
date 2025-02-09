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
class PatchCIError
{
  /** @Id @Column @GeneratedValue */
  public int $id;

  /** @ManyToOne(inversedBy="ci_failures")
   *  @JoinColumn(nullable=false)
   */
  public Patch $patch;

  /** @Column(length=64) */
  public string $hash;

  /** @Column(length=128) */
  public string $name;

  public function __construct(Patch $patch, string $hash, string $name) {
    $this->patch = $patch;
    $this->hash  = $hash;
    $this->name  = $name;
  }
}
