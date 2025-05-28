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

$fields['total_lines_code'] = format_big_number(array_sum($lines_added));


// 2nd plot: % of merged patches
$merged = [];
$total = [];
$total_merged_prs = 0;

foreach (db_get_patch_stats() as $data) {
  switch ($data['status']) {
    case PatchStatus::WaitingReview:
    case PatchStatus::Reviewed:
    case PatchStatus::Approved:
    case PatchStatus::Closed:
      break;

    case PatchStatus::Merged:
    case PatchStatus::MergedIllegal:
      $total_merged_prs += $data['count'];
      @$merged[$data['year']][$data['type']->value] += $data['count'];
      // fallthrough

    case PatchStatus::PROpen:
    case PatchStatus::PROpenIllegal:
    case PatchStatus::NotMerged:
    case PatchStatus::NotMergedIllegal:
      @$total[$data['year']][$data['type']->value] += $data['count'];
      break;

    default:
     die('invalid patch status');
  }
}
ksort($total);

$fields['total_merged_prs'] = format_big_number($total_merged_prs);

$pcmerged_y = [];
foreach ($total as $year => $data) {
  $fields['pcmerged_x'][] = get_term_for($year);
  foreach ($data as $type => $n) {
    $pcmerged_y[$type][] = round(@$merged[$year][$type] / $n, 2);
  }
}
$fields['pcmerged_bug']  = @$pcmerged_y[PatchType::BugFix->value];
$fields['pcmerged_feat'] = @$pcmerged_y[PatchType::Feature->value];


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
  $merged = in_array($data['status'],
                     [PatchStatus::Merged, PatchStatus::MergedIllegal]);
  @$prs_per_project[$repo][$data['type']->value][$merged] += $data['count'];
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
  $total_bugs = array_sum($stats[PatchType::BugFix->value] ?? array());
  $total_feat = array_sum($stats[PatchType::Feature->value] ?? array());
  $bugs       = $stats[PatchType::BugFix->value][1] ?? 0;
  $feat       = $stats[PatchType::Feature->value][1] ?? 0;
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


function format_big_number($n) {
  if ($n < 1000)
    return $n;
  if ($n < 1000000)
    return round($n / 1000) . 'k';
  return round($n / 1000000, 1) . 'M';
}
