<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header("Bugs");

$user = get_user();
$group = $user->getGroup();
$year = $group ? $group->year : get_current_year();
$deadline = db_fetch_deadline($year);

if (isset($_POST['issue_url'])) {
  if ($user->role == ROLE_STUDENT && !$deadline->isBugSelectionActive())
    die('Deadline expired');

  if (!$group)
    die("Student's group not found");

  try {
    if ($bug = db_fetch_bug_user($year, $user)) {
      $bug->description = trim($_POST['description'] ?? '');
      $bug->set_issue_url($_POST['issue_url'] ?? '');
      $bug->set_repro_url($_POST['repro_url'] ?? '');
    } else {
      $bug = SelectedBug::factory($group, $user, $_POST['description'],
                                  $_POST['issue_url'], $_POST['repro_url']);
      db_save($bug);
    }
  } catch (ValidationException $ex) {
    echo "<p style=\"color: red\">Failed to validate all fields: ",
         nl2br(htmlspecialchars($ex->getMessage())), "</p>\n";
  }
}

mk_box_left_begin();

if (auth_at_least(ROLE_TA)) {
  do_start_form('bugs');
  $selected_year     = do_year_selector();
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
  foreach (db_fetch_bugs_group($group) as $bug) {
    $video = $bug->getVideoHTML();
    if ($video) {
      $video = <<<HTML
<button onclick="toggleVideo(this)">Show Video</button>
<div style="display: none">$video</div>
HTML;
    }
    $repo = $group->getRepository();
    $table[] = [
      'id' => $bug->id,
      'Group' => '<a href="' . link_group($group) . "\">$group</a>",
      'Project' => "<a href=\"$repo\">{$repo->name()}</a>",
      'Student' => $bug->user->shortName(),
      'Issue' => '<a href="' . $bug->issue_url . '">link</a>',
      'Desciption' => nl2br(htmlspecialchars($bug->description)),
      'Video' => $video,
    ];
  }
}
print_table($table);

echo <<<HTML
<script>
function toggleVideo(button) {
  let videoContainer = button.nextElementSibling;
  if (videoContainer.style.display === "none" ) {
    videoContainer.style.display = "block";
    button.textContent = "Hide Video";
  } else {
    videoContainer.style.display = "none";
    button.textContent = "Show Video";
  }
}
</script>
HTML;

if ($bug = db_fetch_bug_user($year, $user)) {
  $issue_url = $bug->issue_url;
  $repro_url = $bug->repro_url;
  $description = format_text($bug->description);
} else {
  $issue_url = $repro_url = $description = '';
}

if ($user->role == ROLE_STUDENT && $deadline->isBugSelectionActive()) {
  echo <<<EOF
<p>&nbsp;</p>
<p>Submit/edit bug proposal:</p>
<form action="index.php?page=bugs" method="post">

<label for="issue_url">Issue URL:</label>
<input type="text" id="issue_url" name="issue_url" value="$issue_url" size="50">

<br>
<label for="repro_url">URL of video reproducing the issue:</label>
<input type="text" id="repro_url" name="repro_url" value="$repro_url" size="50">

<br>
<label for="description">Description:</label>
<textarea id="description" name="description" rows="5" cols="60">
$description
</textarea>

<p><input type="submit"></p>
</form>

EOF;
}

mk_box_end();

mk_deadline_box($deadline->bug_selection);
mk_box_end();
