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

use Doctrine\ORM\Exception\EntityManagerClosed;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Psr18NetworkException;
use Symfony\Component\HttpFoundation\RedirectResponse;

$formFactory = Forms::createFormFactoryBuilder()
  ->addExtension(new HttpFoundationExtension())
  ->getFormFactory();
$custom_header = null;
$form = null;
$select_form = null;
$comments_form = null;
$eval_forms = [];
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
$display_formula = null;
$plots = null;

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

$all_pages = [
  'dashboard'    => ['Statistics', ROLE_STUDENT],
  'grades'       => ['Grades', ROLE_STUDENT],
  'profile'      => ['Edit profile', ROLE_STUDENT],
  'listprojects' => ['Projects', ROLE_STUDENT],
  'bugs'         => ['Bugs', ROLE_STUDENT],
  'feature'      => ['Feature', ROLE_STUDENT],
  'patches'      => ['Patches', ROLE_STUDENT],
  'report'       => ['Final Report', ROLE_STUDENT],
  'shifts'       => ['Shifts', ROLE_PROF],
  'deadlines'    => ['Deadlines', ROLE_PROF],
  'grading'      => ['Grading System', ROLE_PROF],
  'changerole'   => ['Change Role', ROLE_PROF],
  'impersonate'  => ['Impersonate', ROLE_SUDO],
  'cron'         => ['Cron', ROLE_PROF],
  'phpinfo'      => ['PHP Info', ROLE_PROF],
  'editpatch'    => ['Patch', ROLE_STUDENT, true],
  'listproject' =>  ['Project Detail', ROLE_STUDENT, true],
  'rmpatch'      => ['Delete Patch', ROLE_PROF, true],
];

try {
  if ($page !== '') {
    if (empty($all_pages[$page])) {
      die('Invalid page');
    }
    auth_require_at_least($all_pages[$page][1]);
    require "pages/$page.php";
  }
  terminate();
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
} catch (TimeoutException $ex) {
  terminate('Operation timed out: ' . $ex->getMessage());
} catch (TransportException $ex) {
  terminate('Network error: ' . $ex->getMessage());
} catch (Psr18NetworkException $ex) {
  terminate('Network error: ' . $ex->getMessage());
} catch (\Github\Exception\RuntimeException $ex) {
  terminate('Failed to access GitHub: ' . $ex->getMessage());
} catch (EntityManagerClosed $ex) {
  terminate('Database connection closed: ' . $ex->getMessage());
}

function terminate($error_message = null, $template = 'main.html.twig',
                   $extra_fields = []) {
  global $page, $deadline, $table, $lists, $info_box, $form, $select_form;
  global $embed_file, $info_message, $success_message, $monospace, $refresh_url;
  global $bottom_links, $top_box, $confirm, $comments, $display_formula;
  global $large_video, $comments_form, $ci_failures, $all_pages, $eval_forms;
  global $plots;

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

  $navbar = [];
  $title = 'Welcome';

  foreach ($all_pages as $tag => $p) {
    if ($tag === $page)
      $title = $p[0];

    if (auth_at_least($p[1]) && empty($p[2]))
      $navbar[] = [
        'url' => dourl($tag),
        'name' => $p[0]
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
    'display_formula' => $display_formula,
    'plots'           => $plots,
    'refresh_url'     => $refresh_url,
    'form'            => $form === null ? null : $form->createView(),
    'select_form'     => $select_form === null
                           ? null : $select_form->createView(),
    'comments_form'   => $comments_form === null
                           ? null : $comments_form->createView(),
    'eval_forms'      => array_map(function ($f) {
        $f['fields'] = $f['fields']->createView();
        return $f;
      }, $eval_forms),
    'dependencies'    => get_webpack_deps(),
  ];

  if (!$error_message) {
    db_flush();
  }
  echo $twig->render($template, $content + $extra_fields);
  exit();
}

function terminate_redirect() {
  db_flush();
  (new RedirectResponse($_SERVER['REQUEST_URI']))->send();
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
