<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PatchComment
{
  #[ORM\Id]
  #[ORM\Column]
  #[ORM\GeneratedValue]
  public int $id;

  #[ORM\ManyToOne(inversedBy: 'comments')]
  #[ORM\JoinColumn(nullable: false)]
  public Patch $patch;

  #[ORM\Column(type: "text", length: 250000)]
  public string $text;

  #[ORM\ManyToOne]
  public ?User $user;

  #[ORM\Column]
  public DateTimeImmutable $time;

  public function __construct(Patch $patch, string $text, ?User $user = null) {
    $this->patch = $patch;
    $this->text  = $text;
    $this->user  = $user;
    $this->time  = new DateTimeImmutable();
  }
}
