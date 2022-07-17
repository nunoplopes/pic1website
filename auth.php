<?php

// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

session_start();

if (isset($_GET['key']) &&
    password_verify('4X6EM' . $_GET['key'] . 'fgOGi', SUDO_HASH)) {
  $_SESSION['role'] = 'superuser';
  $_SESSION['username'] = 'ist00000';
  $_SESSION['name'] = 'Sudo';
}

if (empty($_SESSION['role'])) {
  phpCAS::client(CAS_VERSION_3_0, CAS_HOSTNAME, CAS_PORT, CAS_URI);
  phpCAS::setCasServerCACert(CAS_CA_CERT);
  phpCAS::forceAuthentication();
  var_dump(phpCAS::getAttributes());
  $_SESSION['role'] = 'Prof';
  $_SESSION['username'] = phpCAS::getUser();
  $_SESSION['name'] = 'Maria Manuel';
}

function has_group_permissions($group) {
  switch ($_SESSION['role']) {
    case 'superuser': return true;
    case 'TA':
      return false; // TODO
    case 'student':
      return in_array($_SESSION['username'], $group['students']);
  }
}
