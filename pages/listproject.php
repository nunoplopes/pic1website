<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

if (empty($_GET['id']))
  die('Missing id');

$group = db_fetch_group_id($_GET['id']);
if (!$group || !has_group_permissions($group))
  die('Permission error');

foreach ($group->students as $s) {
  $data = [
    ['type' => 'photo', 'data' => $s->getPhoto()],
    "$s->name ($s->id)",
  ];
  if ($s->email) {
    $data[] = ['type' => 'email', 'data' => $s->email];
  }
  if ($repou = $s->getRepoUser()) {
    if ($repou->isValid()) {
      $data[] = dolink_ext($repou->profileURL(),
                         $repou->platform().':'.$repou->username());
      $data[] = $repou->description();
    } else {
      $data[] = 'Invalid Repository User';
    }
  }
  $top_box['Students'][] = $data;
}

if ($prof = $group->shift->prof) {
  $top_box['Professor'][] = [
    ['type' => 'photo', 'data' => $prof->getPhoto()],
    $prof->shortName(),
    ['type' => 'email', 'data' => $prof->email],
  ];
}

$readonly = ['group_number', 'year', 'shift'];
$hidden   = ['id', 'students', 'patches', 'hash_proposal_file', 'url_proposal',
             'hash_final_report'];
if (get_user()->role == ROLE_STUDENT) {
  $hidden[] = 'allow_modifications_date';
}

$deadline = db_fetch_deadline($group->year);
$deadline = max($deadline->proj_proposal, $group->allow_modifications_date);

if (!is_deadline_current($deadline) && get_user()->role == ROLE_STUDENT) {
  $readonly = array_keys(get_object_vars($group));
}

if (is_deadline_current($deadline)) {
  $info_message = "You can submit this form multiple times until the deadline.".
                  " Only the last submission will be considered.";
}

handle_form($group, $hidden, $readonly, null, ['repository']);

if ($repo = $group->getRepository()) {
  $info_box['title'] = 'Repository data';
  if ($repo->isValid()) {
    $lines   = $repo->linesOfCode();
    $commits = $repo->commitsLastMonth();
    $stars   = $repo->stars();
    $info_box['rows'] = [
      'Main language' => $repo->language() ?? 'Unknown',
      'Lines of Code' => check_min(format_big_number($lines), $lines, 100000),
      'Num of commits in the past month' =>
        check_min(format_big_number($commits), $commits, 50),
      'Stars'         => check_min(format_big_number($stars), $stars, 200),
      'License'       => $repo->license() ?? 'Unknown',
    ];
    if ($repo->topics())
      $info_box['rows']['Topics'] = implode(', ', $repo->topics());
  } else {
    $info_box['rows']['Error'] = 'The repository is no longer available!';
  }
}

if (auth_at_least(ROLE_TA)) {
  $data = ['group' => $group->id, 'year' => $group->year, 'all_shifts' => 1];
  $bottom_links = [
    dolink('grades', 'Grades', $data),
    dolink('patches', 'Patches', $data),
    dolink('bugs', 'Bugs', $data),
    dolink('feature', 'Feature', $data),
    dolink('report', 'Final Report', $data),
  ];
}

mk_eval_box($group->year, 'project', null, $group);

function check_min($txt, $val, $min) {
  if ($val >= $min)
    return $txt;
  return ['data' => $txt, 'warn' => true];
}
