<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PatchCIError
{
  #[ORM\Id]
  #[ORM\ManyToOne(inversedBy: "ci_failures")]
  #[ORM\JoinColumn(nullable: false)]
  public Patch $patch;

  #[ORM\Id]
  #[ORM\Column(length: 64)]
  public string $hash;

  #[ORM\Id]
  #[ORM\Column]
  public string $name;

  #[ORM\Column(length: 512)]
  public string $url;

  #[ORM\Column]
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
