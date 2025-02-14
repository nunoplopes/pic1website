<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'include.php';
require 'db.php';

if (isset($_COOKIE['sessid'])) {
  $session = db_fetch_session($_COOKIE['sessid']);
  if ($session) {
    db_delete($session);
  }
}

setcookie('sessid', '', 1);

header('Location: https://fenix.tecnico.ulisboa.pt/logout', true, 302);
