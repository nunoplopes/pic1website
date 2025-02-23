<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

$user = get_user();
$group = $user->getGroup();
$year = $group ? $group->year : get_current_year();
$deadlines = db_fetch_deadline($year);
$deadline = $deadlines->feature_selection;

if (!$group && $user->role === ROLE_STUDENT) {
  terminate('Student is not in a group');
}

if (!empty($_GET['download'])) {
  $group = db_fetch_group_id($_GET['download']);
  if (has_group_permissions($group) && $group->hash_proposal_file) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="feature_proposal_' .
           $group->group_number . '.pdf"');
    readfile(__DIR__ . "/../uploads/{$group->hash_proposal_file}");
    exit();
  } else {
    die("No permissions");
  }
}

if ($user->role === ROLE_STUDENT && $deadlines->isFeatureSelectionActive()) {
  $form = $formFactory->createBuilder(FormType::class)
    ->add('url', UrlType::class, [
      'label'    => 'Issue URL (if applicable)',
      'required' => false,
      'data'     => $group->url_proposal,
    ])
    ->add('file', FileType::class, [
      'label' => 'Upload PDF',
      'attr'  => ['accept' => '.pdf'],
    ])
    ->add('submit', SubmitType::class, ['label' => 'Upload'])
    ->getForm();

  $form->handleRequest($request);

  if ($form->isSubmitted() && $form->isValid()) {
    $file = $form->get('file')->getData();
    if ($file instanceof UploadedFile) {
      if ($file->getSize() > 5 * 1024 * 1024) {
        terminate('Error: File size exceeds 5 MB');
      }
      if ($file->getClientMimeType() !== 'application/pdf' ||
          (new finfo())->file($file->getPathname(), FILEINFO_MIME_TYPE)
            !== 'application/pdf') {
        terminate('Error: Only PDF files are allowed');
      }

      $hash = hash_file('sha1', $file->getPathname());
      $file->move(__DIR__ . '/../uploads', $hash);
      $success_message = "File uploaded successfully!";

      $group->hash_proposal_file = $hash;
      $group->url_proposal = check_url($form->get('url')->getData());
    }
  }
}

if (auth_at_least(ROLE_TA)) {
  $groups = filter_by(['group', 'year', 'shift', 'own_shifts']);

  foreach ($groups as $group) {
    $table[] = [
      'Group'     => $group->group_number,
      'Issue URL' => $group->url_proposal
                       ? dolink_ext($group->url_proposal, 'link') : '',
      'PDF' => $group->hash_proposal_file
                 ? dolink('features', 'link', ['download' => $group->id]) : '',
    ];
  }
  if (sizeof($groups) === 1) {
    $group = $groups[0];
  }
}

if ($group && $group->hash_proposal_file) {
  $embed_file = dourl('features', ['download' => $group->id]);
}
