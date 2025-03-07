<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

$user = get_user();
$group = $user->getGroup();
$year = $user->getYear() ?? get_current_year();
$deadlines = db_fetch_deadline($year);
$deadline = $deadlines->bug_selection;

if ($group !== null)
  $deadline = $deadline > $group->allow_modifications_date
                ? $deadline : $group->allow_modifications_date;

if (auth_at_least(ROLE_TA)) {
  $groups = filter_by(['group', 'year', 'shift', 'own_shifts', 'repo']);
} else {
  $groups = $user->groups;
}

if ($user->role == ROLE_STUDENT && is_deadline_current($deadline)) {
  $info_message = "You can submit this form multiple times until the deadline.".
                  " Only the last submission will be considered.";

  if ($bug = db_fetch_bug_user($year, $user)) {
    $issue_url = $bug->issue_url;
    $repro_url = $bug->repro_url;
    $description = $bug->description;
  } else {
    $issue_url = $repro_url = $description = null;
  }

  $form = $formFactory->createBuilder(FormType::class)
    ->add('issue_url', UrlType::class, [
      'label' => 'Issue URL',
      'data'  => $issue_url,
    ])
    ->add('repro_url', UrlType::class, [
      'label'    => 'URL of video reproducing the issue',
      'data'     => $repro_url,
      'required' => false,
    ])
    ->add('description', TextareaType::class, [
      'data' => $description,
    ])
    ->add('submit', SubmitType::class)
    ->getForm();

  $form->handleRequest($request);

  if ($form->isSubmitted() && $form->isValid()) {
    if (!$group)
      terminate("Student is not in a group");

    if ($bug = db_fetch_bug_user($year, $user)) {
      $bug->description = $form->get('description')->getData();
      $bug->set_issue_url($form->get('issue_url')->getData());
      $bug->set_repro_url($form->get('repro_url')->getData() ?? '');
    } else {
      $bug = SelectedBug::factory(
        $group, $user, $form->get('description')->getData(),
        $form->get('issue_url')->getData(),
        $form->get('repro_url')->getData() ?? '');
      db_save($bug);
    }
  }
}

$table = [];
foreach ($groups as $group) {
  foreach (db_fetch_bugs_group($group) as $bug) {
    $repo = $group->getRepository();
    $table[] = [
      'Group'        => dolink_group($group, $group),
      'Project'      => $repo ? dolink_ext($repo, $repo->name()) : '',
      'Student'      => $bug->user->shortName(),
      'Issue'        => dolink_ext($bug->issue_url, 'link'),
      'Description'  => ['longdata' => $bug->description],
      'Video'        => get_video_html($bug->repro_url),
      '_large_table' => true,
    ];
  }
}

if (sizeof($groups) == 1) {
  $group = $groups[0];
  mk_eval_box($group->year, 'bugs', null, $group);
}
