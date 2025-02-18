<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

$selected_year = filter_by(['year']);

foreach (db_get_merged_patch_stats() as $entry) {
  $years[]          = get_term_for($entry['year']);
  $patches[]        = $entry['patches'];
  $lines_added[]    = $entry['lines_added'];
  $lines_deleted[]  = $entry['lines_deleted'];
  $files_modified[] = $entry['files_modified'];
}

$fields['max_y']  = round(max($patches) * 1.1);
$fields['max_y2'] = round(max(max($lines_added),
                              max($lines_deleted),
                              max($files_modified)) * 1.1);

$fields['merged_prs_years']          = $years;
$fields['merged_prs_patches']        = $patches;
$fields['merged_prs_lines_added']    = $lines_added;
$fields['merged_prs_lines_deleted']  = $lines_deleted;
$fields['merged_prs_files_modified'] = $files_modified;


// 2nd plot: % of merged patches
$merged = [];
$total = [];

foreach (db_get_patch_stats() as $data) {
  switch ($data['status']) {
    case PATCH_WAITING_REVIEW:
    case PATCH_REVIEWED:
    case PATCH_APPROVED:
      break;

    case PATCH_MERGED:
    case PATCH_MERGED_ILLEGAL:
      @$merged[$data['year']][$data['type']] += $data['count'];
      // fallthrough

    case PATCH_PR_OPEN:
    case PATCH_PR_OPEN_ILLEGAL:
    case PATCH_NOTMERGED:
    case PATCH_NOTMERGED_ILLEGAL:
      @$total[$data['year']][$data['type']] += $data['count'];
      break;

    default:
     die('invalid patch status');
  }
}
ksort($total);

$pcmerged_y = [];
foreach ($total as $year => $data) {
  $fields['pcmerged_x'][] = get_term_for($year);
  foreach ($data as $type => $n) {
    $pcmerged_y[$type][] = round(@$merged[$year][$type] / $n, 2);
  }
}
$fields['pcmerged_bug']  = @$pcmerged_y[PATCH_BUGFIX];
$fields['pcmerged_feat'] = @$pcmerged_y[PATCH_FEATURE];


// 3rd plot: stats of projects selected this year
$projs = [];
$langs = [];
$prs_per_project = [];

foreach (db_fetch_groups($selected_year) as $group) {
  if ($repo = $group->getValidRepository()) {
    if ($lang = $repo->language()) {
      @++$langs[(string)$lang];
    }
    @++$projs[$repo->name()];

    $prs_per_project[$repo->name()]['url'] = (string)$repo;
  }
}

foreach (db_get_pr_stats($selected_year) as $data) {
  $repo   = (new Repository($data['repository']))->name();
  $merged = $data['status'] == PATCH_MERGED ||
            $data['status'] == PATCH_MERGED_ILLEGAL;
  @$prs_per_project[$repo][$data['type']][$merged] += $data['count'];
}

if (sizeof($projs) > 10) {
  foreach ($projs as $proj => $n) {
    if ($n == 1)
      unset($projs[$proj]);
  }
}

arsort($langs);
arsort($projs);
ksort($prs_per_project, SORT_NATURAL | SORT_FLAG_CASE);

$fields['lang_x'] = array_keys($langs);
$fields['lang_y'] = $langs;

$fields['proj_x'] = array_keys($projs);
$fields['proj_y'] = $projs;


// Now a table with all projects
$id = 0;
foreach ($prs_per_project as $name => $stats) {
  $total_bugs = array_sum($stats[PATCH_BUGFIX] ?? array());
  $total_feat = array_sum($stats[PATCH_FEATURE] ?? array());
  $bugs       = $stats[PATCH_BUGFIX][1] ?? 0;
  $feat       = $stats[PATCH_FEATURE][1] ?? 0;
  $fields['all_projects'][] = [
    'id'         => $id++,
    'name'       => $name,
    'total_bugs' => $total_bugs,
    'total_feat' => $total_feat,
    'bugs'       => $bugs,
    'feat'       => $feat,
    'bugs_pc'    => $total_bugs ? round(($bugs / $total_bugs) * 100.0, 0) : 0,
    'feat_pc'    => $total_feat ? round(($feat / $total_feat) * 100.0, 0) : 0,
    'url'        => $stats['url'],
  ];
}

terminate(null, 'dashboard.html.twig', $fields);
