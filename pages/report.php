<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

$user = get_user();
$group = $user->getGroup();
$deadlines = db_fetch_deadline($user->getYear() ?? get_current_year());
$deadline = $deadlines->final_report;

if ($group !== null)
  $deadline = $deadline > $group->allow_modifications_date
                ? $deadline : $group->allow_modifications_date;

if (!$group && $user->role === ROLE_STUDENT) {
  terminate('Student is not in a group');
}

if (!empty($_GET['download'])) {
  $group = db_fetch_group_id($_GET['download']);
  if (has_group_permissions($group) && $group->hash_final_report) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="final_report_' .
           $group->group_number . '.pdf"');
    readfile(__DIR__ . "/../uploads/{$group->hash_final_report}");
    exit();
  } else {
    die("No permissions");
  }
}

if ($user->role === ROLE_STUDENT && is_deadline_current($deadline)) {
  $info_message = "You can submit this form multiple times until the deadline.".
                  " Only the last submission will be considered.";

  $form = $formFactory->createBuilder(FormType::class)
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

      $group->hash_final_report = $hash;
    }
  }
}

if (auth_at_least(ROLE_TA)) {
  $groups = filter_by(['group', 'year', 'shift', 'own_shifts']);
} else {
  $groups = [$group];
}

foreach ($groups as $group) {
  $table[] = [
    'Group' => $group->group_number,
    'PDF'   => $group->hash_final_report
                 ? dolink('report', 'link', ['download' => $group->id]) : '',
  ];
}

$group = sizeof($groups) === 1 ? $groups[0] : null;

if ($group && $group->hash_final_report) {
  $embed_file = dourl('report', ['download' => $group->id]);
}

if ($group) {
  mk_eval_box($group->year, 'report', null, $group);
}
