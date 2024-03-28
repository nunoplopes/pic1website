<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Patch Detail');
require_once 'email.php';

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

if (!auth_at_least(ROLE_TA) &&
    !db_fetch_deadline($patch->group->year)->isPatchSubmissionActive()) {
  $readonly = array_keys(get_object_vars($patch));
}

echo "<p>&nbsp;</p>\n";
mk_box_left_begin();

// if the student changes description, get the patch back on the review queue
if (get_user()->role == ROLE_STUDENT &&
    ($patch->status == PATCH_REVIEWED || $patch->status == PATCH_NOTMERGED) &&
    isset($_POST['description']) &&
    $patch->description != $_POST['description']) {
  $patch->set_status(PATCH_WAITING_REVIEW);

  $user = get_user();
  $name = $user->shortName();
  email_ta($patch->group, 'PIC1: patch updated',
           "$name ($user) requested review for an existing patch\n" .
           link_patch($patch));
}

// Add approve/reject buttons to simplify the life of TAs
$extra_buttons = [];
if ($patch->status <= PATCH_REVIEWED &&
    auth_at_least(ROLE_TA)) {
  $extra_buttons['Approve'] = ['status', PATCH_APPROVED];
  $extra_buttons['Reject']  = ['status', PATCH_REVIEWED];
}

$prev_status = $patch->status;

handle_form($patch, [], $readonly,
            ['group', 'status', 'type', 'issue_url', 'description', 'review'],
            $extra_buttons);

if (auth_at_least(ROLE_PROF)) {
  $link = dolink('rmpatch', 'Delete', ['id' => $patch->id]);
  echo "<p>&nbsp;</p>\n<p>", dolink('rmpatch', 'Delete', ['id' => $patch->id]),
       "</p>\n";
}

mk_box_end();

// notify students of the patch review
if ($patch->status != $prev_status) {
  $subject = null;
  $patchurl = $patch->getPatchURL();
  $pic1link = link_patch($patch);

  if ($patch->status == PATCH_APPROVED) {
    $subject = 'PIC1: Patch approved';
    $line = 'Congratulations! Your patch was approved. You can now open a PR.';
  } else if ($patch->status == PATCH_REVIEWED) {
    $subject = 'PIC1: Patch reviewed';
    $line = 'Your patch was reviewed, but it needs further changes.';
  }

  if ($subject) {
    email_group($patch->group, $subject, <<<EOF
$line

Description:
{$patch->description}

Review:
{$patch->review}

Patch: $patchurl
$pic1link
EOF);
  }
}

$authors = [];
foreach ($patch->students as $author) {
  $authors[] = $author->shortName() . ' (' . $author->id . ')';
}

mk_box_right_begin();
if ($patch->isValid()) {
  echo "<p>Statistics:</p><ul>";
  echo "<li><b>Students:</b> ", implode(', ', $authors), "</li>\n";
  echo "<li><b>Lines added:</b> ", $patch->lines_added, "</li>\n";
  echo "<li><b>Lines removed:</b> ", $patch->lines_deleted, "</li>\n";
  echo "<li><b>Files modified:</b> ", $patch->files_modified, "</li>\n";
  echo '<li><a style="color: white" href="', $patch->getPatchURL(),
      '">Patch</a></li>';
  if ($pr = $patch->getPRURL()) {
    echo '<li><a style="color: white" href="', $pr, '">PR</a></li>';
  }
  echo "<li><b>All authors:</b> ", gen_authors($patch->allAuthors()), "</li>\n";
  echo '</ul>';
} else {
  echo '<p>The patch is no longer available!</p>';
  if ($pr = $patch->getPRURL()) {
    echo '<p><a style="color: white" href="', $pr, '">PR</a></p>';
  }
}
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
