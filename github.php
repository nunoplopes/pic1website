<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

// API doc: https://docs.github.com/en/rest

namespace GitHub;

require_once 'include.php';

function parse_date($date) {
  return DateTimeImmutable::createFromFormat(DateTimeImmutable::ISO8601, $date);
}

function get($path, $etag_in = null) {
  $curl = curl_init("https://api.github.com/$path");
  curl_setopt($curl, CURLOPT_USERPWD, GH_TOKEN);
  curl_setopt($curl, CURLOPT_USERAGENT, USERAGENT);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HEADER, true);
  if ($etag_in)
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['If-None-Match: "'.$etag_in.'"']);

  $data = curl_exec($curl);
  if (!$data)
    die("Couldn't fetch $path\n");

  $data    = explode("\r\n\r\n", $data, 2);
  $headers = $data[0];
  $json    = $data[1];

  if (preg_match('/etag: "([^"]*)"/S', $headers, $etag))
    $etag = $etag[1];

  return [json_decode($json), $etag];
}

function pr_status($repo, $number) {
  $pr = get("repos/$repo/pulls/$number")[0];
  return [
    'closed'    => $pr->state == 'closed',
    'merged'    => $pr->merged,
    'merged_by' => $pr->merged ? $pr->merged_by->login : null,
    'merged_at' => parse_date($pr->merged_at),
    'added'     => $pr->additions,
    'deleted'   => $pr->deletions,
    'numMfiles' => $pr->changed_files,
  ];
}

// returns (opened PRs, opened issues, etag)
function process_user_events($username, $etag = null) {
  [$events, $new_etag] = get("users/$username/events?per_page=100", $etag);

  $opened_prs = [];
  $opened_issues = [];

  foreach ($events as $event) {
    $date = parse_date($event->created_at);
    if ($event->type == 'PullRequestEvent') {
      if ($event->payload->action == 'opened')
        $opened_prs[] = [$event->repo->name, $event->payload->number, $date];
    } else if ($event->type == 'IssuesEvent') {
      if ($event->payload->action == 'opened')
        $opened_issues[] = [$event->repo->name, $event->payload->issue->number,
                            $date];
    }
  }
  return [$opened_prs, $opened_issues, $new_etag];
}

function get_repo_weekly_commits($repo) {
  return get("repos/$repo/stats/participation")[0]->all;
}

function get_repo_stats($repo) {
  $data = get("repos/$repo")[0];
  return [
    'main_branch' => $data->default_branch,
    'parent'      => $data->parent ? $data->parent->full_name : $repo,
    'language'    => $data->language,
    'license'     => $data->license->spdx_id,
    'stars'       => $data->stargazers_count,
    'topics'      => $data->topics,
  ];
}

function parse_repo_url($url) {
  if (preg_match('@^https://github.com/([^/]+/[^/]+)/?$@', $url, $m))
    return $m[1];
  return null;
}

// Returns: main repo, main branch, patch repo, patch branch
function parse_patch_url($url) {
  if (preg_match('@^https://github.com/([^/]+/[^/]+)/compare/([^.]+)...([^:]+:[^:]+):([^:]+)$@', $url, $m))
    return [$m[1], $m[2], $m[3], $m[4]];

  if (preg_match('@^https://github.com/([^/]+/[^/]+)/tree/([^/]+)$@', $url, $m)) {
    $stats = get_repo_stats($m[1]);
    return [$stats['parent'], $stats['main_branch'], $m[1], $m[2]];
  }

  return null;
}

function get_patch_url($repo, $main_branch, $patch_repo, $patch_branch) {
  $patch_repo = strtr($patch_repo, '/', ':') . ':' . $patch_branch;
  return "https://github.com/$repo/compare/$main_branch...$patch_repo";
}

function get_patch_stats($repo, $main_branch, $patch_repo, $patch_branch) {
  $patch_repo = strtr($patch_repo, '/', ':') . ':' . $patch_branch;
  $patch = get("repos/$repo/compare/$main_branch...$patch_repo")[0];
  $add = 0; $del = 0; $files = 0;
  foreach ($patch->files as $f) {
    $add += $f->additions;
    $del += $f->deletions;
    ++$files;
  }
  return [
    'added'     => $add,
    'deleted'   => $del,
    'numMfiles' => $files,
  ];
}
