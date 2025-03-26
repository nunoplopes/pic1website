<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'email.php';

use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

$user = get_user();
$group = $user->getGroup();
$deadlines = db_fetch_deadline($user->getYear() ?? get_current_year());
$deadline = $deadlines->patch_submission;

if ($user->role === ROLE_STUDENT && $deadlines->isPatchSubmissionActive()) {
  $form = $formFactory->createBuilder(FormType::class)
    ->add('url', UrlType::class, ['label' => 'URL'])
    ->add('type', EnumType::class, [
      'class'        => PatchType::class,
      'choice_label' => fn (PatchType $type) => $type->label(),
    ])
    ->add('video_url', UrlType::class, [
      'label' => 'URL of video demonstrating the bug fix/feature',
      'required' => false
    ])
    ->add('description', TextareaType::class)
    ->add('submit', SubmitType::class)
    ->getForm();

  $form->handleRequest($request);

  if ($form->isSubmitted() && $form->isValid()) {
    if (!$group)
      terminate("Student is not in a group");

    $url = $form->get('url')->getData();
    $type = $form->get('type')->getData();
    $description = $form->get('description')->getData();
    $video_url = $form->get('video_url')->getData() ?? '';
    $p = Patch::factory($group, $url, $type, $description, $user, $video_url);
    $group->patches->add($p);
    db_save($p);

    $success_message = 'Patch submitted successfully!';
    $patch_accepted = true;
    $name = $user->shortName();
    email_ta($group, 'PIC1: New patch',
            "$name ($user) of group $group submitted a new patch\n\n" .
            link_patch($p));
  }
}

if (auth_at_least(ROLE_TA)) {
  [$groups, $only_needs_review, $only_open_patches]
    = filter_by(['group', 'year', 'shift', 'own_shifts', 'repo'],
                [
                  'needs_review' => 'Show only patches that need review',
                  'open_patches' => 'Show only non-merged patches',
                ]);
} else {
  $groups = $user->groups;
}

$table = [];
foreach ($groups as $group) {
  foreach ($group->patches as $patch) {
    if (auth_at_least(ROLE_TA)) {
      if ($only_needs_review && $patch->status != PatchStatus::WaitingReview)
        continue;

      if ($only_open_patches &&
          $patch->status->value >= PatchStatus::Merged->value)
        continue;
    }

    $authors = [];
    foreach ($patch->students as $author) {
      $authors[] = $author->shortName();
    }

    $pr = $patch->getPRURL();
    $issue = $patch->getIssueURL();

    $table[] = [
      'id'        => dolink('editpatch', $patch->id, ['id' => $patch->id]),
      'Group'     => dolink('listproject', $group->group_number,
                            ['id' => $group->id]),
      'Status'    => $patch->getStatus(),
      'Type'      => $patch->getType(),
      'Issue'     => $issue ? dolink_ext($issue, 'link') : '',
      'Patch'     => dolink_ext($patch->getPatchURL(), 'link'),
      'PR'        => $pr ? dolink_ext($pr, 'link') : '',
      '+'         => $patch->lines_added,
      '-'         => $patch->lines_deleted,
      'Files'     => $patch->files_modified,
      'Submitter' => $patch->getSubmitterName(),
      'Authors'   => implode(', ', $authors),
      //'Video'     => get_video_html($patch->video_url),
      '_large_table' => true,
    ];
  }
}
