<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

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

$prev_status = $patch->getStatus();

$new_comment = trim($_POST['text'] ?? '');
if ($new_comment && get_user()->role == ROLE_STUDENT) {
  if ($patch->status == PATCH_REVIEWED || $patch->status == PATCH_NOTMERGED) {
    $patch->set_status(PATCH_WAITING_REVIEW);
  }
}

handle_form($patch, [], $readonly, ['group', 'status', 'type'], null, false);

if (auth_at_least(ROLE_PROF)) {
  $link = dolink('rmpatch', 'Delete', ['id' => $patch->id]);
  echo "<p>&nbsp;</p>\n<p>", dolink('rmpatch', 'Delete', ['id' => $patch->id]),
       "</p>\n";
}

if ($new_comment) {
  $new_status = $patch->getStatus();
  if ($new_status != $prev_status) {
    $new_comment = "Status changed: $prev_status → $new_status\n\n$new_comment";
  }
  $patch->comments->add(new PatchComment($patch, $new_comment, get_user()));
}
db_flush();

echo "<table>\n";
foreach ($patch->comments as $comment) {
  break;
  if ($comment->user) {
    $author = $comment->user->shortName() . ' (' . $comment->user->id . ')';
    $photo  = $comment->user->getPhoto();
  } else {
    $author = '';
    $photo  = 'https://api.dicebear.com/9.x/bottts/svg?seed=Liliana&baseColor=00acc1&eyes=roundFrame02&mouth=smile01&texture[]&top=antenna';
  }
  $text = format_text($comment->text);

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

$ci_failures = [];
foreach ($patch->ci_failures as $ci) {
  $ci_failures[$ci->hash][] = $ci->name;
}

if ($ci_failures) {
  echo <<<HTML
<p>&nbsp;</p>
<p><b>CI Failures:</b></p>
<table>
  <tr><th>Commit hash</th><th>Failed CI jobs</th></tr>
HTML;

  foreach ($ci_failures as $hash => $names) {
    echo "<tr><td>$hash</td><td>",
         nl2br(htmlspecialchars(implode("\n", $names))),
         "</td></tr>\n";
  }
  echo "</table>\n";
}

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

// notify students of the patch review
if ($patch->getStatus() != $prev_status) {
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

if ($patch->isValid()) {
  $info_box['title'] = 'Statistics';
  $info_box['rows'] = [
    'Lines added'    => $patch->lines_added,
    'Lines removed'  => $patch->lines_deleted,
    'Files modified' => $patch->files_modified,
    'All authors'    => gen_authors($patch->allAuthors()),
  ];
} else {
  $info_box['title'] = 'The patch is no longer available!';
}
$info_box['rows']['Patch'] = dolink_ext($issue, 'link');

if ($issue = $patch->getIssueURL()) {
  $info_box['rows']['Issue'] = dolink_ext($issue, 'link');
}
if ($pr = $patch->getPRURL()) {
  $info_box['rows']['PR'] = dolink_ext($pr, 'link');
}

function gen_authors($list) {
  $data = [];
  $invalid = true;
  foreach ($list as $author) {
    $name  = $author[1];
    $email = $author[2];
    if (!check_email($email))
      $invalid = true;

    $data[] = "$name <$email>";
  }
  $data = implode(', ', $data);
  return $invalid ? ["warn" => true, "data" => $data] : $data;
}
