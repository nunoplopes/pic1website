<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

require_once 'email.php';

$user = get_user();

if (empty($_GET['id']))
  die('Missing id');

$patch = db_fetch_patch_id($_GET['id']);
if (!$patch || !has_group_permissions($patch->group))
  die('Permission error');

$readonly = ['group'];
if ($user->role == ROLE_STUDENT) {
  $readonly[] = 'status';
}

if (!auth_at_least(ROLE_TA) &&
    !db_fetch_deadline($patch->group->year)->isPatchSubmissionActive()) {
  $readonly = array_keys(get_object_vars($patch));
}

$prev_status = $patch->getStatus();

$comments_form = $formFactory->createNamedBuilder('comments', FormType::class)
  ->add('text', TextareaType::class, [
    'attr' => [
      'rows' => 7,
    ],
  ])
  ->add('submit', SubmitType::class, [
    'label' => 'Add new comment',
  ]);

// Add approve/reject buttons to simplify the life of TAs
if ($patch->status->value <= PatchStatus::Reviewed->value &&
    auth_at_least(ROLE_TA)) {
  $comments_form->add('approve', SubmitType::class, [
    'label' => 'Approve',
  ]);
  $comments_form->add('reject', SubmitType::class, [
    'label' => 'Reject',
  ]);
}

$comments_form = $comments_form->getForm();
$comments_form->handleRequest($request);

if ($comments_form->isSubmitted() && $comments_form->isValid()) {
  if (auth_at_least(ROLE_TA)) {
    if ($comments_form->has('approve') &&
        $comments_form->get('approve')->isClicked()) {
      $patch->status = PatchStatus::Approved;
    } elseif ($comments_form->has('reject') &&
              $comments_form->get('reject')->isClicked()) {
      $patch->status = PatchStatus::Reviewed;
    }
  }
  elseif ($user->role == ROLE_STUDENT &&
          in_array($patch->status,
                   [PatchStatus::Reviewed, PatchStatus::NotMerged,
                    PatchStatus::NotMergedIllegal])) {
    $patch->status = PatchStatus::WaitingReview;
  }

  $new_status  = $patch->getStatus();
  $new_comment = $comments_form->get('text')->getData();
  if ($new_status != $prev_status) {
    $new_comment = "Status changed: $prev_status → $new_status\n\n$new_comment";
  }
  $patch->comments->add(new PatchComment($patch, $new_comment, $user));

  $pic1link = link_patch($patch);

  if ($user->role == ROLE_STUDENT) {
    $name = $user->shortName();
    email_ta($patch->group, 'PIC1: new patch comment',
             "$name ($user) added a new comment:\n" .
             "\n$new_comment\n\n$pic1link");

  // notify students of the patch review
  } elseif ($new_status != $prev_status) {
    if ($patch->status == PatchStatus::Approved) {
      $subject = 'PIC1: Patch approved';
      $line = 'Congratulations! Your patch was approved. You can now open a PR.';
    } else {
      assert($patch->status == PatchStatus::Reviewed);
      $subject = 'PIC1: Patch reviewed';
      $line = 'Your patch was reviewed, but it needs further changes.';
    }

    $patchurl = $patch->getPatchURL();
    email_group($patch->group, $subject, <<<EOF
$line

$new_comment

Patch: $patchurl
$pic1link
EOF);
  } else {
    email_group($patch->group, 'PIC1: new patch comment',
                "$new_comment\n\n$pic1link");
  }
  terminate_redirect();
}

$old_video_url = $patch->video_url;
handle_form($patch, [], $readonly, ['group', 'status', 'type', 'video_url'],
            ['group', 'status', 'type']);

if ($patch->video_url != $old_video_url) {
  $new_comment = "Video URL changed: $old_video_url → {$patch->video_url}";
  $patch->comments->add(new PatchComment($patch, $new_comment, $user));
}

if ($patch->video_url) {
  try {
    $large_video = get_video_html($patch->video_url, false);
  } catch (ValidationException $e) {
    $info_message = 'Video no longer available';
  }
}

foreach ($patch->comments as $comment) {
  $data = [];
  if ($comment->user) {
    $data['author'] = $comment->user->shortName() . ' ('.$comment->user->id.')';
    $data['photo']  = $comment->user->getPhoto();
  } else {
    $data['author'] = '';
    $data['photo']  = 'https://api.dicebear.com/9.x/bottts/svg?seed=Liliana&baseColor=00acc1&eyes=roundFrame02&mouth=smile01&texture[]&top=antenna';
  }
  $data['date'] = $comment->time;
  $data['text'] = $comment->text;
  $comments[] = $data;
}

$ci_failures = [];
foreach ($patch->ci_failures as $ci) {
  $ci_failures[$ci->hash]['url'] = $ci->getCommitURL();
  $ci_failures[$ci->hash]['failed'][$ci->name] = $ci->url;
}

if (auth_at_least(ROLE_PROF)) {
  $bottom_links[] = dolink('rmpatch', 'Delete patch', ['id' => $patch->id]);
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
$info_box['rows']['Patch'] = dolink_ext($patch->getPatchURL(), 'link');

if ($pr = $patch->getPRURL()) {
  $info_box['rows']['PR'] = dolink_ext($pr, 'link');
}
if ($issue = $patch->getIssueURL()) {
  $info_box['rows']['Issue'] = dolink_ext($issue, 'link');
}

function gen_authors($list) {
  $data = [];
  $invalid = false;
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

$type = $patch->getType();
mk_eval_box($patch->group->year, 'patch-' . $type, $patch->getSubmitter(),
            $type == 'feature' ? $patch->group : null);
