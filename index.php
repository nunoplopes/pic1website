<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'include.php';
require 'templates.php';
require 'db.php';
require 'auth.php';
require 'github.php';
require 'validation.php';

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

$page = $_REQUEST['page'] ?? '';
$file = "pages/$page.php";

use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;

$formFactory = Forms::createFormFactoryBuilder()
  ->addExtension(new HttpFoundationExtension())
  ->getFormFactory();
$form = null;
$select_form = null;
$embed_file = null;
$success_message = null;
$table = null;
$deadline = null;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

try {
  if (ctype_alpha($page) && file_exists($file)) {
    require $file;
  } else {
    require 'pages/main.php';
  }
} catch (PDOException $e) {
  if (IN_PRODUCTION) {
    echo "<p>Error while accessing the DB</p>";
  } else {
    echo "<pre>", htmlspecialchars(print_r($e, true)), "</pre>";
  }
}

function terminate($error_message = null) {
  global $page, $deadline, $table, $form, $select_form, $embed_file,
         $success_message;

  $appvar = new \ReflectionClass('\Symfony\Bridge\Twig\AppVariable');
  $loader = new \Twig\Loader\FilesystemLoader([
    __DIR__ . '/templates',
    dirname($appvar->getFileName()) . '/Resources/views/Form'
  ]);

  $options = [
    'debug' => !IN_PRODUCTION,
    'cache' => __DIR__ . '/.cache/twig',
  ];
  $twig = new \Twig\Environment($loader, $options);
  $formEngine = new \Symfony\Bridge\Twig\Form\TwigRendererEngine(
    ['bootstrap_5_layout.html.twig'], $twig);
  $twig->addRuntimeLoader(new \Twig\RuntimeLoader\FactoryRuntimeLoader([
    Symfony\Component\Form\FormRenderer::class => function () use ($formEngine) {
        return new \Symfony\Component\Form\FormRenderer($formEngine);
    }
  ]));
  $twig->addExtension(new \Symfony\Bridge\Twig\Extension\FormExtension());
  $twig->addExtension(new \Symfony\Bridge\Twig\Extension\TranslationExtension());

  $pages = [
    ['dashboard', 'Statistics', ROLE_STUDENT],
    ['profile', 'Edit profile', ROLE_STUDENT],
    ['listprojects', 'Projects', ROLE_STUDENT],
    ['bugs', 'Bugs', ROLE_STUDENT],
    ['features', 'Features', ROLE_STUDENT],
    ['patches', 'Patches', ROLE_STUDENT],
    ['shifts', 'Shifts', ROLE_PROF],
    ['deadlines', 'Deadlines', ROLE_PROF],
    ['changerole', 'Change Role', ROLE_PROF],
    ['impersonate', 'Impersonate', ROLE_SUDO],
    ['cron', 'Cron', ROLE_PROF],
    ['phpinfo', 'PHP Info', ROLE_PROF],
  ];
  $navbar = [];
  $title = '';

  foreach ($pages as $p) {
    if ($p[0] === $page)
      $title = $p[1];

    if (auth_at_least($p[2]))
      $navbar[] = [
        'url' => dourl($p[0]),
        'name' => $p[1]
      ];
  }

  $user = get_user();

  $content = [
    'title'           => $title,
    'navbar'          => $navbar,
    'name'            => $user->name,
    'email'           => $user->email,
    'user_id'         => $user->id,
    'role'            => get_role_string(),
    'photo'           => $user->getPhoto(),
    'embed_file'      => $embed_file,
    'success_message' => $error_message ? '' : $success_message,
    'error_message'   => $error_message,
    'table'           => $table,
    'deadline'        => $deadline ? $deadline->format('c') : null,
  ];

  if ($form)
    $content['form'] = $form->createView();
  if ($select_form)
    $content['select_form'] = $select_form->createView();

  echo $twig->render('main.html.twig', $content);
  db_flush();
  exit();
}

function filter_by($filters) {
  global $page, $request, $formFactory, $select_form;
  $select_form = $formFactory->createNamedBuilder('',
    Symfony\Component\Form\Extension\Core\Type\FormType::class);

  $select_form->add('page', HiddenType::class, [
    'data' => $page,
  ]);

  $selected_year   = $request->query->get('year', db_get_group_years()[0]['year']);
  $selected_shift  = $request->query->get('shift', null);
  $own_shifts_only = $request->query->get('own_shifts', false) ? true : false;
  $selected_group  = $request->query->get('group', 'all');
  $selected_repo   = $request->query->get('repo', 'all');
  $return = null;

  $selected_shift_obj = $selected_shift && $selected_shift != 'all'
                          ? db_fetch_shift_id($selected_shift) : null;

  if (in_array('year', $filters)) {
    foreach (db_get_group_years() as $year) {
      $years[$year['year']] = $year['year'];
    }
    $select_form->add('year', ChoiceType::class, [
      'label'   => 'Year',
      'choices' => $years,
      'data'    => $selected_year,
    ]);
    $return = $selected_year;
  }
  if (in_array('shift', $filters)) {
    $shifts = ['All' => 'all'];
    foreach (db_fetch_shifts($selected_year) as $shift) {
      if (!has_shift_permissions($shift))
        continue;
      if ($own_shifts_only && $shift->prof != get_user())
        continue;
      $shifts[$shift->name] = $shift->id;
    }
    $select_form->add('shift', ChoiceType::class, [
      'label'   => 'Shift',
      'choices' => $shifts,
      'data'    => $selected_shift,
    ]);
  }
  if (in_array('group', $filters)) {
    $groups = ['All' => 'all'];
    $return = [];
    foreach (db_fetch_groups($selected_year) as $group) {
      if (!has_group_permissions($group))
        continue;
      if ($own_shifts_only && $group->prof() != get_user())
        continue;
      if ($selected_shift_obj && $group->shift != $selected_shift_obj)
        continue;
      if ($selected_repo != 'all' && $group->getRepositoryId() != $selected_repo)
        continue;

      if ($selected_group == 'all' || $group->id == $selected_group)
        $return[] = $group;
      $groups[$group->group_number] = $group->id;
    }
    $select_form->add('group', ChoiceType::class, [
      'label'   => 'Group',
      'choices' => $groups,
      'data'    => $selected_group,
    ]);
  }
  if (in_array('repo', $filters)) {
    $repos = [];
    foreach (db_fetch_groups($selected_year) as $group) {
      if (!has_group_permissions($group))
        continue;

      if ($repo = $group->getRepositoryId())
        $repos[$repo] = true;
    }
    $repos = array_keys($repos);
    natsort($repos);

    $repos = ['All' => 'all'] + array_combine($repos, $repos);
    $select_form->add('repo', ChoiceType::class, [
      'label'   => 'Repository',
      'choices' => $repos,
      'data'    => $selected_repo,
    ]);
  }
  if (in_array('own_shifts', $filters)) {
    $select_form->add('own_shifts', CheckboxType::class, [
      'label' => 'Show only own shifts',
      'data'  => $own_shifts_only,
    ]);
  }
  $select_form = $select_form->getForm();
  $select_form->handleRequest($request);
  return $return;
}

terminate();
