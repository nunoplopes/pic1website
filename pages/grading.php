<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

$year = filter_by(['year']);

$bottom_links[] = dolink('grading', 'Edit final grade', ['final' => 1]);

if (isset($_GET['final'])) {
  $data = db_get_final_grade($year);
  $form = $formFactory->createBuilder(FormType::class)
    ->add('final', HiddenType::class, ['data' => 1])
    ->add('formula', TextType::class, ['data' => $data ? $data->formula : ''])
    ->add('submit', SubmitType::class)
    ->getForm();

  $form->handleRequest($request);
  if ($form->isSubmitted() && $form->isValid()) {
    if (!$data) {
      $data = new FinalGrade($year);
      $data->year = $year;
    }
    $data->formula = $form->get('formula')->getData();
    db_save($data);
  }
  $final_grade = db_get_final_grade($year);
  terminate();
}

if (empty($_GET['id'])) {
  $form = $formFactory->createBuilder(FormType::class)
    ->add('name', TextType::class)
    ->add('submit', SubmitType::class)
    ->getForm();

  $form->handleRequest($request);
  if ($form->isSubmitted() && $form->isValid()) {
    db_save(new Milestone($year, $form->get('name')->getData()));
  }
} else {
  $milestone = db_fetch_milestone_id($_GET['id']);
  if (!$milestone) {
    die('Invalid milestone');
  }
  handle_form($milestone, [], ['id', 'year'], null, ['milestone', 'name']);
}

foreach (db_get_all_milestones($year) as $milestone) {
  $data = [
    'name' => dolink('grading', $milestone->name, ['id' => $milestone->id]),
    '_large_table' => true,
  ];
  foreach ($milestone as $key => $value) {
    if (!in_array($key, ['id', 'year', 'name'])) {
      $data[$key] = $value;
    }
  }
  $table[] = $data;
}
