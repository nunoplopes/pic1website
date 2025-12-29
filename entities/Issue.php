<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

abstract class Issue {
  static public function factory($url) {
  if ($gh = GitHub\GitHubIssue::factory($url)) {
      return $gh;
    }
    return null;
  }

  public abstract function getTitle() : string;
  public abstract function getDescription() : string;
}
