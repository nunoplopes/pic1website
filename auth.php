<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'fenix.php';

$__session__ = null;
function get_user() : User {
  return $GLOBALS['__session__']->user;
}

function auth_set_user($user) {
  global $__session__;
  $__session__->user = $user;
  db_flush();
}

function create_session($user) {
  $session = new Session($user);
  db_save_session($session);

  $time = $session->expires->getTimestamp();
  setcookie('sessid', $session->id, $time, '/', '', IN_PRODUCTION, true);

  // Let's cleanup the URL to remove any auth keys there
  $cleanurl = (IN_PRODUCTION ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] .
              '/index.php?page=' . @$_GET['page'];
  header("Location: $cleanurl");
  exit;
}


if (isset($_GET['key']) &&
    password_verify('4X6EM' . $_GET['key'] . 'fgOGi', SUDO_HASH)) {
  $user = db_fetch_or_add_user('ist00000', 'Sudo', ROLE_SUDO, '', '', true);
  create_session($user);
}

if (isset($_GET['fenixlogin'])) {
  if (isset($_GET['code'])) {
    $token = fenix_get_auth_token($_GET['code']);
    if (!$token)
      die("Failed to authenticate Fenix's token");

    $person = fenix_get_personal_data($token);

    $user = db_fetch_or_add_user($person['username'], $person['name'],
                                 ROLE_STUDENT, $person['email'],
                                 $person['photo']);
    create_session($user);

  } else if (isset($_GET['error_description'])) {
    die("Fenix returned an error: " .
        htmlspecialchars($_GET['error_description']));
  } else {
    die("Couldn't understand Fenix's reply\n");
  }
}

if ($__session__ === null) {
  if (isset($_COOKIE['sessid']) &&
      ($__session__ = db_fetch_session($_COOKIE['sessid'])) &&
      $__session__->isFresh()) {
    // authenticated
  } else {
    header('Location: ' . fenix_get_auth_url());
    exit;
  }
}

function validate_role($role, $allow_sudo) {
  return is_int($role) &&
         $role >= ($allow_sudo ? ROLE_SUDO : ROLE_PROF) &&
         $role <= ROLE_STUDENT;
}

function auth_user_at_least(User $user, $role) {
  return $user->role <= $role;
}

function auth_at_least($role) {
  return auth_user_at_least(get_user(), $role);
}

function auth_require_at_least($role) {
  if (!auth_at_least($role))
    die('Unauthorized access');
}

function has_shift_permissions(Shift $shift) {
  $user = get_user();
  switch ($user->role) {
    case ROLE_SUDO:
    case ROLE_PROF:
      return true;
    case ROLE_TA:
      return $shift->prof == $user;
    case ROLE_STUDENT:
      foreach ($user->groups as $group) {
        if ($group->shift == $shift)
          return true;
      }
      return false;
  }
}

function has_group_permissions(ProjGroup $group) {
  $user = get_user();
  switch ($user->role) {
    case ROLE_SUDO:
    case ROLE_PROF:
      return true;
    case ROLE_TA:
      return $group->shift->prof == $user;
    case ROLE_STUDENT:
      return $user->groups->contains($group);
  }
}

function get_all_roles($include_sudo) {
  $roles = [
    ROLE_PROF    => 'Professor',
    ROLE_TA      => 'TA',
    ROLE_STUDENT => 'Student'
  ];
  if ($include_sudo)
    $roles[ROLE_SUDO] = 'Sudo';
  return $roles;
}

function get_role_string() {
  return get_all_roles(true)[get_user()->role];
}
