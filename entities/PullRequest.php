<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

abstract class PullRequest
{
  public Repository $repository;

  abstract public function url() : string;
  abstract public function branchURL() : string;
  abstract public function origin() : string;
  abstract public function isClosed() : bool;
  abstract public function wasMerged() : bool;
  abstract public function mergedBy() : string;
  abstract public function mergeDate() : \DateTimeImmutable;
  abstract public function linesAdded() : int;
  abstract public function linesDeleted() : int;
  abstract public function filesModified() : int;
  abstract public function __toString();
}
