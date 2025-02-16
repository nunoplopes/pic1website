<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

auth_require_at_least(ROLE_PROF);

$year = get_current_year();
$shifts = db_fetch_shifts($year);

$form = $formFactory->createBuilder(FormType::class);

foreach (db_get_all_profs(true) as $prof) {
  $profs[$prof->shortName()] = $prof->id;
}

foreach ($shifts as $shift) {
  $form->add("shift_$shift->id", ChoiceType::class, [
    'label'    => $shift->name,
    'choices'  => $profs,
    'data'     => $shift->prof ? $shift->prof->id : null,
  ]);
}

$form->add('submit', SubmitType::class);
$form = $form->getForm();

$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
  foreach ($shifts as $shift) {
    $var = "shift_$shift->id";
    $user = db_fetch_user($form->get($var)->getData());
    if (!$user || !$user->roleAtLeast(ROLE_TA))
      die("Unknown user");
    $shift->prof = $user;
  }
  $success_message = 'Saved!';
}
