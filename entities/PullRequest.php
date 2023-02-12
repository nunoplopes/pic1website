<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

abstract class PullRequest
{
  public $repository;

  abstract public function origin() : string;
  abstract public function isClosed() : bool;
  abstract public function wasMerged() : bool;
  abstract public function mergedBy() : string;
  abstract public function mergeDate() : \DateTimeImmutable;
  abstract public function linesAdded() : int;
  abstract public function linesRemoved() : int;
  abstract public function filesModified() : int;
  abstract public function __toString();
}
