<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Project Detail');

if (empty($_GET['id']))
  die('Missing id');

$group = db_fetch_group_id($_GET['id']);
if (!$group || !has_group_permissions($group))
  die('Permission error');

echo '<table style="text-align:center"><tr>';
foreach ($group->students as $s) {
  echo '<td><img src="', $s->getPhoto(), '"><br>';

  $name = $s->name;
  if ($s->email)
    $name = "<a href=\"mailto:$s->email\">$name</a>";

  echo "$name ($s->id)";
  if ($repo = $s->getRepoUser())
    echo "<br>\n", $repo->description();
  echo "</td>";
}
echo "</tr></table>\n";

if ($prof = $group->shift->prof) {
  echo "<p>Professor: <a href=\"mailto:$prof->email\">",
       $prof->shortName(), "</a></p>\n";
}

$readonly = ['group_number', 'year', 'shift'];
$hidden   = ['id', 'students', 'patches'];
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
  echo "<p>NOTE: You can submit this form multiple times until the deadline. ",
       "Only the last submission will be considered.</p>\n";
}

echo "<p>&nbsp;</p>\n";
mk_box_left_begin();
handle_form($group, $hidden, $readonly);
mk_box_end();

mk_deadline_box($deadline);

if ($repo = $group->getRepository()) {
  mk_box_right_begin();
  if ($repo->isValid()) {
    $commits = $repo->commitsLastMonth();
    $stars   = $repo->stars();
    echo "<p>Repository data:</p><ul>";
    echo "<li><b>Main language:</b> ",
         htmlspecialchars($repo->language() ?? 'Unknown'), "</li>\n";
    echo "<li><b>Num of commits in the past month:</b> ",
         check_min(number_format($commits), $commits, 50), "</li>\n";
    echo "<li><b>Stars:</b> ",
         check_min(number_format($stars), $stars, 200), "</li>\n";
    echo "<li><b>License:</b> ",
         htmlspecialchars($repo->license() ?? 'Unknown'), "</li>\n";
    $topics = array_map('htmlspecialchars', $repo->topics());
    if ($topics)
      echo "<li><b>Topics:</b> ", implode(', ', $topics), "</li>\n";
    echo '</ul>';
  } else {
    echo '<p>The repository is no longer available!</p>';
  }
  mk_box_end();
}
mk_box_end();

echo "<p>", dolink('patches', 'Submitted patches', ['group' => $group->id]),
     "</p><p>&nbsp;</p>\n";


function check_min($txt, $val, $min) {
  if ($val >= $min)
    return $txt;
  return '<b><span style="color:red">' . $txt . '</span></b>';
}
