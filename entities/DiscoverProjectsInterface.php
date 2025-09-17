<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

interface DiscoverProjectsInterface {
  public static function searchByKeyword($keywords, $language, $have_issues_for_newbies) : array;
  public static function searchByTopics($topics, $language, $have_issues_for_newbies) : array;
}
