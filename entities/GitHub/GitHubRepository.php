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
    return $GLOBALS['github_client']->api('repo')->show($org, $repo);
  }

  static function defaultBranch($name) : string {
    return self::stats($name)['default_branch'];
  }

  static function parent($name) : ?string {
    $data = self::stats($name);
    return isset($data['parent']) ? $data['parent']['full_name'] : null;
  }

  static function language($name) : ?\ProgLanguage {
    return db_fetch_prog_language(self::stats($name)['language']);
  }

  static function license($name) : ?\License {
    $license = self::stats($name)['license'];
    return $license ? db_fetch_license($license['spdx_id']) : null;
  }

  static function stars($name) : int {
    return self::stats($name)['stargazers_count'];
  }

  static function topics($name) : array {
    return self::stats($name)['topics'];
  }

  static function commitsLastMonth($name) : int {
    [$org, $repo] = self::getRepo($name);
    $data = $GLOBALS['github_client']->api('repo')->participation($org, $repo);
    return array_sum(array_slice($data['all'], -4));
  }

  static function toString($name) : string {
    return "https://github.com/$name";
  }
}
