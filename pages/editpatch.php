<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Patch Detail');

if (empty($_GET['id']))
  die('Missing id');

$patch = db_fetch_patch_id($_GET['id']);
if (!$patch || !has_group_permissions($patch->group))
  die('Permission error');

$readonly = ['group', 'lines_added', 'lines_deleted', 'num_files', 'students',
             'patch_url', 'pr_number'];
if (get_user()->role == ROLE_STUDENT)
  $readonly[] = 'review';

if (!db_fetch_deadline(get_current_year())->isPatchSubmissionActive()) {
  $readonly = array_keys(get_object_vars($patch));
}

echo "<p>&nbsp;</p>\n";
handle_form($patch,
            /* hidden= */['id'],
            $readonly);
