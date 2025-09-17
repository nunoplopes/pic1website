<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

class DiscoverProjects {
  static function searchByKeyword($keywords, $language, $have_issues_for_newbies) {
    $results = GitHub\GitHubDiscoverProjects::searchByKeyword(
        $keywords, $language, $have_issues_for_newbies);
    return $results;
  }
}
