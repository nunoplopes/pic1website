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

use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;

$formFactory = Forms::createFormFactoryBuilder()
  ->addExtension(new HttpFoundationExtension())
  ->getFormFactory();
$custom_header = null;
$form = null;
$select_form = null;
$embed_file = null;
$info_message = null;
$success_message = null;
$table = null;
$lists = null;
$deadline = null;
$top_box = null;
$info_box = null;
$monospace = null;
$bottom_links = null;
$refresh_url = null;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

try {
  $file = "pages/$page.php";
  if (ctype_alpha($page) && file_exists($file)) {
    require $file;
  }
} catch (PDOException $e) {
  if (IN_PRODUCTION) {
    terminate('Error while accessing the DB');
  } else {
    terminate(print_r($e, true));
  }
} catch (ValidationException $ex) {
  terminate('Failed to validate all fields: ' . $ex->getMessage());
} catch (DateMalformedStringException $ex) {
  terminate('Failed to parse date: ' . $ex->getMessage());
}

function terminate($error_message = null, $template = 'main.html.twig',
                   $extra_fields = []) {
  global $page, $deadline, $table, $lists, $info_box, $form, $select_form;
  global $embed_file, $info_message, $success_message, $monospace, $refresh_url;
  global $custom_header, $bottom_links, $top_box;

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
  $title = $custom_header ?? 'Welcome';

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
    'info_message'    => $info_message,
    'success_message' => $error_message ? '' : $success_message,
    'error_message'   => $error_message,
    'table'           => $table,
    'lists'           => $lists,
    'top_box'         => $top_box,
    'info_box'        => $info_box,
    'monospace'       => $monospace,
    'deadline'        => $deadline ? $deadline->format('c') : null,
    'bottom_links'    => $bottom_links,
    'refresh_url'     => $refresh_url,
    'form'            => $form === null ? null : $form->createView(),
    'select_form'     => $select_form === null
                           ? null : $select_form->createView(),
  ];

  if (!$error_message) {
    db_flush();
  }
  echo $twig->render($template, $content + $extra_fields);
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
    $years = [];
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
