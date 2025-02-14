<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

auth_require_at_least(ROLE_PROF);

$form = $formFactory->createBuilder(FormType::class)
->add('username', TextType::class, [
  'label' => 'Username',
])
->add('role', ChoiceType::class, [
  'label'   => 'Role',
  'choices' => array_flip(get_all_roles(auth_at_least(ROLE_SUDO))),
])
->add('submit', SubmitType::class)
->getForm();

$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
  $role = (int)$form->get('role')->getData();
  if (!validate_role($role, auth_at_least(ROLE_SUDO)))
    die('Unknown role');
  $user = db_fetch_user($form->get('username')->getData());
  if (!$user)
    die('Unknown user');
  $user->role = $role;
  db_flush();
  $success_message = 'Changed role successfully!';
}
