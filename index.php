<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'include.php';
require 'templates.php';
require 'db.php';
require 'auth.php';
require 'github.php';
require 'validation.php';
require 'video.php';

$page = $_REQUEST['page'] ?? '';

use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;

$formFactory = Forms::createFormFactoryBuilder()
  ->addExtension(new HttpFoundationExtension())
  ->getFormFactory();
$custom_header = null;
$form = null;
$select_form = null;
$comments_form = null;
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
$confirm = null;
$large_video = null;
$comments = null;
$ci_failures = null;

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
  global $custom_header, $bottom_links, $top_box, $confirm, $comments;
  global $large_video, $comments_form, $ci_failures;

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
    'confirm'         => $confirm,
    'large_video'     => $large_video,
    'comments'        => $comments,
    'ci_failures'     => $ci_failures,
    'deadline'        => $deadline ? $deadline->format('c') : null,
    'bottom_links'    => $bottom_links,
    'refresh_url'     => $refresh_url,
    'form'            => $form === null ? null : $form->createView(),
    'select_form'     => $select_form === null
                           ? null : $select_form->createView(),
    'comments_form'   => $comments_form === null
                           ? null : $comments_form->createView(),
    'dependecies'     => get_webpack_deps(),
  ];

  if (!$error_message) {
    db_flush();
  }
  echo $twig->render($template, $content + $extra_fields);
  exit();
}

function get_webpack_deps() {
  $json = json_decode(file_get_contents('assets/public/entrypoints.json'));
  $html = '';
  foreach ($json->entrypoints->app->css as $entrypoint) {
    $html .= "<link rel='stylesheet' href='$entrypoint'>\n";
  }
  foreach ($json->entrypoints->app->js as $entrypoint) {
    $html .= "<script src='$entrypoint'></script>\n";
  }
  return $html;
}

terminate();
