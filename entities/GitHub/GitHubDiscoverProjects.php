<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

class GitHubDiscoverProjects implements \DiscoverProjectsInterface {

  private static function do_query($query, $language, $have_issues_for_newbies) {
    global $github_client_cached;

    $query .= ' size:>3900'; // min of about 100k LoC (~4 MB)
    $query .= ' stars:>=200';
    $query .= ' pushed:>=' . (new \DateTime('-1 month'))->format('Y-m-d');
    if ($language)
      $query .= ' language:' . $language;
    if ($have_issues_for_newbies)
      $query .= ' good-first-issues:>30';
    $query .= ' mirror:false archived:false template:false';

    $repos = $github_client_cached->api('search')->repositories($query, null, null);

    $res = [];
    foreach ($repos['items'] as $repo) {
      $res[] = [
        'name'        => 'github:' . $repo['full_name'],
        'description' => $repo['description'],
        'url'         => $repo['html_url'],
        'stars'       => format_big_number($repo['stargazers_count']),
        'loc'         => format_big_number($repo['size'] * 25), // rough estimate: 25 LoC per KB
        'open_issues' => format_big_number($repo['open_issues']),
        'language'    => $repo['language'],
        'topics'      => $repo['topics'],
        'last_push'   => github_parse_date($repo['pushed_at']),
      ];
    }

    return $res;
  }

  public static function searchByKeyword($keywords, $language, $have_issues_for_newbies) : array {
    $query  = $keywords;
    $query .= ' in:name,description,topics';
    return Self::do_query($query, $language, $have_issues_for_newbies);
  }

  public static function searchByTopics($topics, $language, $have_issues_for_newbies) : array {
    $query  = $topics;
    $query .= ' in:topics';
    return Self::do_query($query, $language, $have_issues_for_newbies);
  }
}
