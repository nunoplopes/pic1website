<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'entities/Patch.php';
require_once 'email.php';

html_header("Patches");

$user = get_user();
$group = $user->getGroup();
$deadline = db_fetch_deadline($group ? $group->year : get_current_year());

mk_box_left_begin();

$patch_accepted = false;

if (isset($_POST['url'])) {
  if ($user->role == ROLE_STUDENT &&
      !$deadline->isPatchSubmissionActive())
    die('Deadline expired');

  if (!$group)
    die("Student's group not found");

  try {
    $p = Patch::factory($group, $_POST['url'], $_POST['type'],
                        $_POST['issue_url'], $_POST['description'], $user);
    $group->patches->add($p);
    db_save($p);

    $patch_accepted = true;
    $name = $user->shortName();
    email_ta($group, 'PIC1: New patch',
             "$name ($user) of group $group submitted a new patch\n\n" .
             link_patch($p));
  } catch (ValidationException $ex) {
    echo "<p style=\"color: red\">Failed to validate all fields: ",
         nl2br(htmlspecialchars($ex->getMessage())), "</p>\n";
  }
}


if (auth_at_least(ROLE_TA)) {
  do_start_form('patches');
  $selected_year     = do_year_selector();
  $only_needs_review = do_bool_selector('Show only patches that need review',
                                        'needs_review');
  $only_open_patches = do_bool_selector('Show only non-merged patches',
                                        'open_patches');
  $own_shifts_only   = do_bool_selector('Show only own shifts', 'own_shifts');
  $selected_shift    = do_shift_selector($selected_year, $own_shifts_only);
  $selected_repo     = do_repo_selector($selected_year);
  $groups            = do_group_selector($selected_year, $selected_shift,
                                         $own_shifts_only, $selected_repo);
  echo "</form><p>&nbsp;</p>\n";
} else {
  $groups = $user->groups;
}

$table = [];
foreach ($groups as $group) {
  foreach ($group->patches as $patch) {
    if (auth_at_least(ROLE_TA)) {
      if ($only_needs_review && $patch->status != PATCH_WAITING_REVIEW)
        continue;

      if ($only_open_patches && $patch->status >= PATCH_MERGED)
        continue;
    }

    $authors = [];
    foreach ($patch->students as $author) {
      $authors[] = htmlspecialchars($author->shortName());
    }

    $pr = $patch->getPRURL();

    $table[] = [
      'id'        => dolink('editpatch', $patch->id, ['id' => $patch->id]),
      'Group'     => dolink('listproject', $group->group_number,
                            ['id' => $group->id]),
      'Status'    => $patch->getStatus(),
      'Type'      => $patch->getType(),
      'Patch'     => '<a href="'. $patch->getPatchURL() . '">link</a>',
      'PR'        => $pr ? '<a href="'. $pr . '">link</a>' : '',
      '+'         => $patch->lines_added,
      '-'         => $patch->lines_deleted,
      'Files'     => $patch->files_modified,
      'Submitter' => htmlspecialchars($patch->getSubmitterName()),
      'Authors'   => implode(', ', $authors),
    ];
  }
}

print_table($table);


if ($user->role == ROLE_STUDENT && $deadline->isPatchSubmissionActive()) {
  $bugfix = PATCH_BUGFIX;
  $feature = PATCH_FEATURE;

  if ($patch_accepted) {
    $url = $issue_url = 'https://...';
    $description = $select_bugfix = $select_feature = '';
  } else {
    $url            = htmlspecialchars($_POST['url'] ?? 'https://...');
    $issue_url      = htmlspecialchars($_POST['issue_url'] ?? 'https://...');
    $description    = htmlspecialchars($_POST['description'] ?? '');
    $type           = (int)($_POST['type'] ?? -1);
    $select_bugfix  = $type == $bugfix  ? ' selected' : '';
    $select_feature = $type == $feature ? ' selected' : '';
  }

  echo <<<EOF
<p>&nbsp;</p>
<p>Submit new patch:</p>
<form action="index.php?page=patches" method="post">

<label for="url">URL:</label>
<input type="text" id="url" name="url" value="$url" size="50">

<br>
<label for="type">Type:</label>
<select name="type" id="type">
<option value="$bugfix"$select_bugfix>Bug fix</option>
<option value="$feature"$select_feature>Feature</option>
</select>

<br>
<label for="issue_url">Issue URL:</label>
<input type="text" id="issue_url" name="issue_url" value="$issue_url" size="50">

<br>
<label for="description">Description:</label>
<textarea id="description" name="description" rows="5" cols="60">$description</textarea>

<p><input type="submit"></p>
</form>

EOF;
}
mk_box_end();

mk_deadline_box($deadline->patch_submission);
mk_box_end();
