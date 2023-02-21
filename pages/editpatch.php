<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Patch Detail');

if (empty($_GET['id']))
  die('Missing id');

$patch = db_fetch_patch_id($_GET['id']);
if (!$patch || !has_group_permissions($patch->group))
  die('Permission error');

$readonly = ['group'];
if (get_user()->role == ROLE_STUDENT) {
  $readonly[] = 'review';
  $readonly[] = 'status';
}

if (!db_fetch_deadline(get_current_year())->isPatchSubmissionActive()) {
  $readonly = array_keys(get_object_vars($patch));
}

echo "<p>&nbsp;</p>\n";
mk_box_left_begin();

// if the student changes description, get the patch back on the review queue
if (get_user()->role == ROLE_STUDENT &&
    $patch->status == PATCH_REVIEWED &&
    isset($_POST['description']) &&
    $patch->description != $_POST['description']) {
  $patch->set_status(PATCH_WAITING_REVIEW);
}

handle_form($patch, [], $readonly,
            ['group', 'status', 'type', 'description', 'review']);
mk_box_end();

$authors = [];
foreach ($patch->students as $author) {
  $authors[] = $author->shortName() . ' (' . $author->id . ')';
}

mk_box_right_begin();
echo "<p>Statistics:</p><ul>";
echo "<li><b>Students:</b> ", implode(', ', $authors), "</li>\n";
echo "<li><b>Lines added:</b> ", $patch->lines_added, "</li>\n";
echo "<li><b>Lines removed:</b> ", $patch->lines_deleted, "</li>\n";
echo "<li><b>Files modified:</b> ", $patch->files_modified, "</li>\n";
echo '<li><a style="color: white" href="', $patch->getURL(), '">Link</a></li>';
echo "<li><b>All authors:</b> ", gen_authors($patch->allAuthors()), "</li>\n";
echo '</ul>';
mk_box_end();
mk_box_end();


function gen_authors($list) {
  $data = [];
  foreach ($list as $author) {
    $name  = htmlspecialchars($author[1]);
    $email = htmlspecialchars($author[2]);
    $emails = explode('@', $email);
    if (sizeof($emails) != 2 || $emails[1] !== 'tecnico.ulisboa.pt')
      $email = '<span style="color: red">' . $email . '</span>';

    $data[] = "$name &lt;$email&gt;";
  }
  return implode(', ', $data);
}
