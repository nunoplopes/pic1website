<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'email.php';

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

$user = get_user();
$group = $user->getGroup();
$deadlines = db_fetch_deadline($group ? $group->year : get_current_year());
$deadline = $deadlines->patch_submission;

if ($user->role === ROLE_STUDENT && $deadlines->isPatchSubmissionActive()) {
  $form = $formFactory->createBuilder(FormType::class)
    ->add('url', UrlType::class, ['label' => 'URL'])
    ->add('type', ChoiceType::class, [
      'label'   => 'Type',
      'choices' => ['Bug fix' => PATCH_BUGFIX, 'Feature' => PATCH_FEATURE],
    ])
    ->add('description', TextareaType::class, ['label' => 'Description'])
    ->add('submit', SubmitType::class, ['label' => 'Submit'])
    ->getForm();

  $form->handleRequest($request);

  if ($form->isSubmitted() && $form->isValid()) {
    if (!$group)
      terminate("Student is not in a group");

    $url = $form->get('url')->getData();
    $type = $form->get('type')->getData();
    $description = $form->get('description')->getData();
    $p = Patch::factory($group, $url, $type, $description, $user);
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
  $groups = filter_by(['group', 'year', 'shift', 'own_shifts', 'repo']);
/*  $only_needs_review = do_bool_selector('Show only patches that need review',
                                        'needs_review');
  $only_open_patches = do_bool_selector('Show only non-merged patches',
                                        'open_patches');*/
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
    ];
  }
}
