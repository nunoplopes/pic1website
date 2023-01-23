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
  echo "$s->name ($s->id)</td>";
}
echo "</tr></table>\n";

$readonly = ['group_number', 'year', 'shift'];
if (!db_fetch_deadline(get_current_year())->isProjProposalActive()) {
  $readonly = array_keys(get_object_vars($group));
}

echo "<p>&nbsp;</p>\n";
echo '<div style="display: inline-block"><div style="float: left">';
handle_form($group,
            /* hidden= */['id', 'students', 'patches'],
            $readonly);
echo '</div>';

if ($repo = $group->repository) {
  echo '<div style="float: right; width: 300px; padding: 10px; margin: 10px; ',
       'background: blue; color: white">';
  echo "<p>Repository data:</p><ul>";
  echo "<li><b>Main language:</b> ",        htmlspecialchars($repo->language()),
       "</li>\n";
  echo "<li><b>Num of commits in the past month:</b> ",
       number_format($repo->commitsLastMonth()), "</li>\n";
  echo "<li><b>Stars:</b> ", number_format($repo->stars()), "</li>\n";
  echo "<li><b>License:</b> ", ($repo->license() ?? 'Unknown'), "</li>\n";
  $topics = array_map('htmlspecialchars', $repo->topics());
  echo "<li><b>Topics:</b> ", implode(', ', $topics), "</li>\n";
  echo '</ul></div>';
}
echo "</div>\n";
