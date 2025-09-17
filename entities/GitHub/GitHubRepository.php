<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

namespace GitHub;

class GitHubRepository implements \RepositoryInterface
{
  static function parse($url) : ?string {
    if (preg_match('@^https://github.com/([^/]+/[^/]+)/?$@', $url, $m))
      return $m[1];
    return null;
  }

  static function getRepo(string $name) {
    return explode('/', $name);
  }

  static private function stats($name) {
    [$org, $repo] = self::getRepo($name);
    return $GLOBALS['github_client_cached']->api('repo')->show($org, $repo);
  }

  static function isValid($name) : bool {
    try {
      self::stats($name);
      return true;
    } catch (\Github\Exception\RuntimeException $e) {
      return $e->getMessage() == 'Not Found' ? false : true;
    }
  }

  static function defaultBranch($name) : string {
    return self::stats($name)['default_branch'];
  }

  static function parent($name) : ?string {
    $data = self::stats($name);
    return isset($data['parent']) ? $data['parent']['full_name'] : null;
  }

  static function language($name) : string {
    return self::stats($name)['language'];
  }

  static function license($name) : ?string {
    $license = self::stats($name)['license'];
    return $license ? $license['name'] : null;
  }

  static function stars($name) : int {
    return self::stats($name)['stargazers_count'];
  }

  static function topics($name) : array {
    return self::stats($name)['topics'];
  }

  static function linesOfCode($name) : int {
    [$org, $repo] = self::getRepo($name);
    $data = $GLOBALS['github_client_cached']->api('repo')->languages($org, $repo);
    $loc = 0;
    // Data is given in bytes; let's estimate 40 bytes per line of code.
    foreach ($data as $v) {
      $loc += $v;
    }
    return (int)($loc / 40.0);
  }

  static function commitsLastMonth($name) : int {
    [$org, $repo] = self::getRepo($name);
    $data = $GLOBALS['github_client_cached']->api('repo')->participation($org, $repo);
    return array_sum(array_slice($data['all'], -4));
  }

  static function toString($name) : string {
    return "https://github.com/$name";
  }
}
