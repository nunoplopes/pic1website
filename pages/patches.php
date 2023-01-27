<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'entities/Patch.php';

html_header("Patches");

$user = get_user();

if (isset($_POST['url'])) {
  if ($user->role == ROLE_STUDENT &&
      !db_fetch_deadline(get_current_year())->isPatchSubmissionActive())
    die('Deadline expired');

  $group = $user->getGroup();
  if (!$group)
    die("Student's group not found");

  try {
    db_save(Patch::factory($group, $_POST['url'], $_POST['type'],
                           $_POST['description']));
  } catch (ValidationException $ex) {
    echo "<p style=\"color: red\">Failed to validate all fields: ",
         htmlspecialchars($ex->getMessage()), "</p>\n";
  }
}


if (isset($_GET['group'])) {
  $groups = [db_fetch_group_id((int)$_GET['group'])];
} else if ($user->role == ROLE_STUDENT) {
  $groups = $user->groups;
} else {
  $groups = db_fetch_groups(get_current_year());
}

$only_needs_review = !empty($_POST['needs_review']);
$own_shifts_only   = !empty($_POST['own_shifts']);

if (auth_at_least(ROLE_TA)) {
  $only_review_checked = $only_needs_review ? ' checked' : '';
  $own_shifts_checked   = $own_shifts_only ? ' checked' : '';
  echo <<<HTML
<form action="index.php?page=patches" method="post">
<label for="needs_review">Show only patches that need review</label>
<input type="checkbox" id="needs_review" name="needs_review" value="1"
onchange='this.form.submit()'$only_review_checked>
<br>
<label for="own_shifts">Show only own shifts</label>
<input type="checkbox" id="own_shifts" name="own_shifts" value="1"
onchange='this.form.submit()'$own_shifts_checked>
</form>
<br>
HTML;
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

    $authors = [];
    foreach ($patch->students() as $author) {
      $authors[] = $author->shortName();
    }

    $table[] = [
      'id'      => dolink('editpatch', $patch->id, ['id' => $patch->id]),
      'Group'   => $group->group_number,
      'Status'  => $patch->getStatus(),
      'Type'    => $patch->getType(),
      'URL'     => '<a href="'. $patch->getURL() . '">link</a>',
      '+'       => $patch->linesAdded(),
      '-'       => $patch->linesRemoved(),
      'Files'   => $patch->filesModified(),
      'Authors' => implode(', ', $authors),
    ];
  }
}

print_table($table);


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
<p>&nbsp;</p>

EOF;
