<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

$custom_header = 'Project Detail';

if (empty($_GET['id']))
  die('Missing id');

$group = db_fetch_group_id($_GET['id']);
if (!$group || !has_group_permissions($group))
  die('Permission error');

$top_box['title'] = 'Students';
foreach ($group->students as $s) {
  $data = [
    ['type' => 'photo', 'data' => $s->getPhoto()],
    "$s->name ($s->id)",
  ];
  if ($s->email) {
    $data[] = ['type' => 'email', 'data' => $s->email];
  }
  if ($repou = $s->getRepoUser()) {
    $data[] = dolink_ext($repou->profileURL(),
                         $repou->platform().':'.$repou->username());
    $data[] = $repou->description();
  }
  $top_box['rows'][] = $data;
}

if ($prof = $group->shift->prof) {
  echo "<p>Professor: <a href=\"mailto:$prof->email\">",
       $prof->shortName(), "</a></p>\n";
}

$readonly = ['group_number', 'year', 'shift'];
$hidden   = ['id', 'students', 'patches', 'hash_proposal_file', 'url_proposal'];
if (get_user()->role == ROLE_STUDENT) {
  $hidden[] = 'allow_modifications_date';
}

$deadline = db_fetch_deadline($group->year);
$deadline = $deadline->proj_proposal > $group->allow_modifications_date
              ? $deadline->proj_proposal : $group->allow_modifications_date;

if (!is_deadline_current($deadline) && get_user()->role == ROLE_STUDENT) {
  $readonly = array_keys(get_object_vars($group));
}

if (is_deadline_current($deadline)) {
  $info_message = "You can submit this form multiple times until the deadline.".
                  " Only the last submission will be considered.";
}

handle_form($group, $hidden, $readonly, null, null, ['repository']);

if ($repo = $group->getRepository()) {
  $info_box['title'] = 'Repository data';
  if ($repo->isValid()) {
    $commits = $repo->commitsLastMonth();
    $stars   = $repo->stars();
    $info_box['rows'] = [
      'Main language' => $repo->language() ?? 'Unknown',
      'Num of commits in the past month' =>
        check_min(number_format($commits), $commits, 50),
      'Stars'         => check_min(number_format($stars), $stars, 200),
      'License'       => $repo->license() ?? 'Unknown',
    ];
    if ($repo->topics())
      $info_box['rows']['Topics'] = implode(', ', $repo->topics());
  } else {
    $info_box['rows']['Error'] = 'The repository is no longer available!';
  }
}

$bottom_links[]
  = dolink('patches', 'Submitted patches', ['group' => $group->id]);

function check_min($txt, $val, $min) {
  if ($val >= $min)
    return $txt;
  return ['data' => $txt, 'warn' => true];
}
