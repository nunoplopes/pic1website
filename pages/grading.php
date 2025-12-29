<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

$year = filter_by(['year']);

$bottom_links[] = dolink('grading', 'Edit final grade', ['final' => 1]);

$current_finalgrade = db_get_final_grade($year);

if (isset($_GET['final'])) {
  $form = $formFactory->createBuilder(FormType::class)
    ->add('final', HiddenType::class, ['data' => 1])
    ->add('formula', TextType::class, ['data' => $current_finalgrade?->formula])
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
  terminate();
}

$grading_is_empty = !$current_finalgrade && !db_get_all_milestones($year);
$copy_form = null;

if ($grading_is_empty && empty($_GET['id'])) {
  $years = [];
  foreach (db_get_milestones_years() as $row) {
    $y = $row['year'];
    $years[$y] = $y;
  }

  $copy_form = $formFactory->createNamedBuilder('copy', FormType::class)
    ->add('source_year', ChoiceType::class, [
      'label'       => 'Copy grading from year',
      'choices'     => $years,
      'placeholder' => 'Select year',
    ])
    ->add('copy', SubmitType::class)
    ->getForm();

  $copy_form->handleRequest($request);

  if ($copy_form->isSubmitted() && $copy_form->isValid()) {
    $source_year = $copy_form->get('source_year')->getData();
    foreach (db_get_all_milestones($source_year) as $source_milestone) {
      $new_milestone = new Milestone($year, $source_milestone->name);
      foreach ($source_milestone as $key => $value) {
        if (!in_array($key, ['id', 'year'], true)) {
          $new_milestone->$key = $value;
        }
      }
      db_bulk_save($new_milestone);
    }

    // Copy final grade formula, if it exists for the source year
    $source_final = db_get_final_grade($source_year);
    if ($source_final) {
      $new_final = new FinalGrade($year);
      $new_final->year = $year;
      $new_final->formula = $source_final->formula;
      db_bulk_save($new_final);
    }
    db_flush();
    $success_message = "Grading copied successfully from year $source_year.";
  }
}

if (empty($_GET['id'])) {
  $form = $formFactory->createNamedBuilder('add_milestone', FormType::class)
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
