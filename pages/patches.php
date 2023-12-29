<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'entities/Patch.php';

html_header("Patches");

$user = get_user();
$deadline = db_fetch_deadline(get_current_year());

mk_box_left_begin();

if (isset($_POST['url'])) {
  if ($user->role == ROLE_STUDENT &&
      !$deadline->isPatchSubmissionActive())
    die('Deadline expired');

  $group = $user->getGroup();
  if (!$group)
    die("Student's group not found");

  try {
    $p = Patch::factory($group, $_POST['url'], $_POST['type'],
                        $_POST['description']);
    $group->patches->add($p);
    db_save($p);
  } catch (ValidationException $ex) {
    echo "<p style=\"color: red\">Failed to validate all fields: ",
         htmlspecialchars($ex->getMessage()), "</p>\n";
  }
}


if (isset($_GET['group']) && $_GET['group'] != 'all') {
  $groups = [db_fetch_group_id((int)$_GET['group'])];
} else if ($user->role == ROLE_STUDENT) {
  $groups = $user->groups;
} else {
  $groups = db_fetch_groups(get_current_year());
}

$only_needs_review = !empty($_REQUEST['needs_review']);
$only_open_patches = !empty($_REQUEST['open_patches']);
$own_shifts_only   = !empty($_REQUEST['own_shifts']);
$selected_shift    = @$_REQUEST['shift'] != 'all' ? @$_REQUEST['shift'] : null;

if (auth_at_least(ROLE_TA)) {
  $only_review_checked  = $only_needs_review ? ' checked' : '';
  $open_patches_checked = $only_open_patches ? ' checked' : '';
  $own_shifts_checked   = $own_shifts_only ? ' checked' : '';
  echo <<<HTML
<form action="index.php" method="get">
<input type="hidden" name="page" value="patches">
<label for="needs_review">Show only patches that need review</label>
<input type="checkbox" id="needs_review" name="needs_review" value="1"
onchange='this.form.submit()'$only_review_checked>
<br>
<label for="open_patches">Show only non-merged patches</label>
<input type="checkbox" id="open_patches" name="open_patches" value="1"
onchange='this.form.submit()'$open_patches_checked>
<br>
<label for="own_shifts">Show only own shifts</label>
<input type="checkbox" id="own_shifts" name="own_shifts" value="1"
onchange='this.form.submit()'$own_shifts_checked>
<br>
<label for="shift">Show specific shift:</label>
<select name="shift" id="shift" onchange='this.form.submit()'>
<option value="all">All</option>
HTML;

  foreach (db_fetch_shifts(get_current_year()) as $shift) {
    if (!has_shift_permissions($shift))
      continue;
    if ($own_shifts_only && $shift->prof != get_user())
      continue;
    $select = $shift->id == $selected_shift ? ' selected' : '';
    echo "<option value=\"$shift->id\"$select>", htmlspecialchars($shift->name),
         "</option>\n";
  }

echo <<< HTML
</select>
<br>
<label for="group">Filter by group:</label>
<select name="group" id="group" onchange='this.form.submit()'>
<option value="all">All</option>
HTML;

  foreach (db_fetch_groups(get_current_year()) as $group) {
    if (!has_group_permissions($group))
      continue;
    if ($own_shifts_only && $group->prof() != get_user())
      continue;
    if ($selected_shift && $group->shift->id != $selected_shift)
      continue;

    $selected = @$_GET['group'] == $group->id ? ' selected' : '';
    echo "<option value=\"{$group->id}\"$selected>", $group->group_number,
         "</option>\n";
  }

  echo "</select></form><br>\n";
}

$table = [];
foreach ($groups as $group) {
  if (!has_group_permissions($group))
    continue;

  if ($own_shifts_only && $group->shift->prof != get_user())
    continue;

  foreach ($group->patches as $patch) {
    if ($only_needs_review && $patch->status != PATCH_WAITING_REVIEW)
      continue;

    if ($only_open_patches && $patch->status >= PATCH_MERGED)
      continue;

    if ($selected_shift && $group->shift->id != $selected_shift)
      continue;

    $authors = [];
    foreach ($patch->students as $author) {
      $authors[] = $author->shortName();
    }

    $pr = $patch->getPRURL();

    $table[] = [
      'id'      => dolink('editpatch', $patch->id, ['id' => $patch->id]),
      'Group'   => dolink('listproject', $group->group_number,
                          ['id' => $group->id]),
      'Status'  => $patch->getStatus(),
      'Type'    => $patch->getType(),
      'Patch'   => '<a href="'. $patch->getPatchURL() . '">link</a>',
      'PR'      => $pr ? '<a href="'. $pr . '">link</a>' : '',
      '+'       => $patch->lines_added,
      '-'       => $patch->lines_deleted,
      'Files'   => $patch->files_modified,
      'Authors' => implode(', ', $authors),
    ];
  }
}

print_table($table);


if ($user->role == ROLE_STUDENT && $deadline->isPatchSubmissionActive()) {
  $bugfix = PATCH_BUGFIX;
  $feature = PATCH_FEATURE;

  echo <<<EOF
<p>&nbsp;</p>
<p>Submit new patch:</p>
<form action="index.php?page=patches" method="post">

<label for="url">URL:</label>
<input type="text" id="url" name="url" value="https://..." size="50">

<br>
<label for="type">Type:</label>
<select name="type" id="type">
<option value="$bugfix">Bug fix</option>
<option value="$feature">Feature</option>
</select>

<br>
<label for="description">Description:</label>
<textarea id="description" name="description" rows="5" cols="60"></textarea>

<p><input type="submit"></p>
</form>

EOF;
}
mk_box_end();

mk_deadline_box($deadline->patch_submission);
mk_box_end();
