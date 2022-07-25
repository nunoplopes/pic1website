<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Project Detail');

if (empty($_GET['id']))
  die('Missing id');

$group = db_fetch_group_id($_GET['id']);
if (!$group || !has_group_permissions($group))
  die('Permission error');

echo '<table style="text-align:center"><tr>';
foreach ($group->students as $s) {
  echo '<td><img src="', get_photo($s), '"><br>';
  echo $s->getShortName(), " ($s->id)</td>";
}
echo "</tr></table>\n";

echo "<p>&nbsp;</p>\n";
handle_form($group,
            /* hidden= */['id', 'students'],
            /* readonly= */['group_number', 'year', 'students', 'shift']);
