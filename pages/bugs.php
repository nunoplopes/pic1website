<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

$user = get_user();
$group = $user->getGroup();
$year = $group ? $group->year : get_current_year();
$deadlines = db_fetch_deadline($year);
$deadline = $deadlines->bug_selection;

if (auth_at_least(ROLE_TA)) {
  $groups = filter_by(['group', 'year', 'shift', 'own_shifts', 'repo']);
} else {
  $groups = $user->groups;
}

$table = [];
foreach ($groups as $group) {
  foreach (db_fetch_bugs_group($group) as $bug) {
    $video = $bug->getVideoHTML();
    if ($video) {
      $video = <<<HTML
<button class="btn btn-primary" onclick="toggleVideo(this)">Show Video</button>
<div style="display: none; margin-top: 10px">$video</div>
HTML;
    }
    $repo = $group->getRepository();
    $table[] = [
      'id'          => $bug->id,
      'Group'       => dolink_group($group, $group),
      'Project'     => $repo ? dolink_ext($repo, $repo->name()) : '',
      'Student'     => $bug->user->shortName(),
      'Issue'       => dolink_ext($bug->issue_url, 'link'),
      'Description' => $bug->description,
      'Video'       => ['html' => $video],
    ];
  }
}

if ($user->role == ROLE_STUDENT && $deadlines->isBugSelectionActive()) {
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
      'data' => $issue_url,
    ])
    ->add('repro_url', UrlType::class, [
      'label' => 'URL of video reproducing the issue',
      'data' => $repro_url,
    ])
    ->add('description', TextareaType::class, [
      'label'    => 'Description',
      'data'     => $description,
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
      $bug->set_repro_url($form->get('repro_url')->getData());
    } else {
      $bug = SelectedBug::factory(
        $group, $user, $form->get('description')->getData(),
        $form->get('issue_url')->getData(),
        $form->get('repro_url')->getData());
      db_save($bug);
    }
  }
}
