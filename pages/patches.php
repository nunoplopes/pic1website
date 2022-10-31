<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header("Patches");

$user = get_user();

if (isset($_GET['group'])) {
  $groups = [db_fetch_group_id((int)$_GET['group'])];
} else if ($user->role == ROLE_STUDENT) {
  $groups = $user->groups;
} else {
  $groups = db_fetch_groups(get_current_year());
}

foreach ($groups as $group) {
  if (!has_group_permissions($group))
    continue;
}

// db_fetch_deadline(get_current_year())->isPatchSubmissionActive();
