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
  $readonly[] = 'status';
}

if (!auth_at_least(ROLE_TA) &&
    !db_fetch_deadline($patch->group->year)->isPatchSubmissionActive()) {
  $readonly = array_keys(get_object_vars($patch));
}

echo "<p>&nbsp;</p>\n";
mk_box_left_begin();

$prev_status = $patch->status;

handle_form($patch, [], $readonly, ['group', 'status', 'type', 'issue_url'],
            null, false);

if (auth_at_least(ROLE_PROF)) {
  $link = dolink('rmpatch', 'Delete', ['id' => $patch->id]);
  echo "<p>&nbsp;</p>\n<p>", dolink('rmpatch', 'Delete', ['id' => $patch->id]),
       "</p>\n";
}

$new_comment = trim($_POST['text'] ?? '');
if ($new_comment) {
  $user = get_user();
  if ($user->role == ROLE_STUDENT) {
    if ($patch->status == PATCH_REVIEWED || $patch->status == PATCH_NOTMERGED) {
      $patch->set_status(PATCH_WAITING_REVIEW);
    }
  }
  if ($patch->status != $prev_status) {
    $old = Patch::get_status_options()[$prev_status];
    $new = Patch::get_status_options()[$patch->status];
    $new_comment = "Status changed: $old → $new\n\n$new_comment";
  }
  $patch->comments->add(new PatchComment($patch, $new_comment, $user));
}
db_flush();

echo "<table>\n";
foreach ($patch->comments as $comment) {
  if ($comment->user) {
    $author = $comment->user->shortName() . ' (' . $comment->user->id . ')';
    $photo  = $comment->user->getPhoto();
  } else {
    $author = '';
    $photo  = 'https://api.dicebear.com/9.x/bottts/svg?seed=Liliana&baseColor=00acc1&eyes=roundFrame02&mouth=smile01&texture[]&top=antenna';
  }
  $text = nl2br(htmlspecialchars(wordwrap($comment->text, 80, "\n", true)));

  echo <<<HTML
<tr>
  <td>
    <p><img src="$photo" alt="Photo" width="100px"></p>
    <p><b>$author</b></p>
    <p>{$comment->time->format('d/m/Y H:i:s')}</p>
  </td>
  <td>$text</td>
</tr>
HTML;
}
echo "</table>\n";

// Add approve/reject buttons to simplify the life of TAs
$extra_buttons = '';
if ($patch->status <= PATCH_REVIEWED && auth_at_least(ROLE_TA)) {
  $app = PATCH_APPROVED;
  $rej = PATCH_REVIEWED;
  $extra_buttons = <<<HTML
  <input type="hidden" name="status" value="{$patch->status}">
  <input type="submit" value="Approve" onclick="this.form.status.value='$app'">
  <input type="submit" value="Reject" onclick="this.form.status.value='$rej'">
HTML;
}

echo <<<HTML
<p>&nbsp;</p>
<form method="post">
  <input type="hidden" name="submit" value="1">
  <input type="hidden" name="id" value="{$patch->id}">
  <textarea name="text" rows="5" cols="80"></textarea><br>
  <input type="submit" value="Add comment">
  $extra_buttons
</form>
HTML;

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

Review:
$new_comment

Patch: $patchurl
$pic1link
EOF);
  }
} elseif ($new_comment) {
  if (get_user()->role == ROLE_STUDENT) {
    $name = $user->shortName();
    email_ta($patch->group, 'PIC1: new patch comment',
             "$name ($user) added a new comment:\n" .
             "\n$new_comment\n\n" .
             link_patch($patch));
  } else {
    email_group($patch->group, 'PIC1: new patch comment',
                "$new_comment\n\n" .
                link_patch($patch));
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
  echo '<p><a style="color: white" href="', $patch->getPatchURL(),
       '">Patch</a></p>';
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
    if (!check_email($email))
      $email = '<span style="color: red">' . $email . '</span>';

    $data[] = "$name &lt;$email&gt;";
  }
  return implode(', ', $data);
}
