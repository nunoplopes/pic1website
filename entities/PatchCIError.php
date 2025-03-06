<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

/** @Entity */
class PatchCIError
{
  /** @Id
   *  @ManyToOne(inversedBy="ci_failures")
   *  @JoinColumn(nullable=false)
   */
  public Patch $patch;

  /** @Id @Column(length=64) */
  public string $hash;

  /** @Id @Column */
  public string $name;

  /** @Column */
  public string $url;

  /** @Column */
  public DateTimeImmutable $time;

  public function __construct(Patch $patch, string $hash, string $name,
                              string $url, DateTimeImmutable $time) {
    $this->patch = $patch;
    $this->hash  = $hash;
    $this->name  = $name;
    $this->url   = $url;
    $this->time  = $time;
  }

  public function getCommitURL() : string {
    return $this->patch->getCommitURL($this->hash);
  }
}
