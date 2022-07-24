<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

$config = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/entities'],
                                                       !IN_PRODUCTION);

$entityManager = EntityManager::create(['url' => DB_DSN], $config);

function db_fetch_user($username) {
  global $entityManager;
  return $entityManager->find('User', $username);
}

function db_fetch_or_add_user($username, $name, $role) : User {
  $user = db_fetch_user($username);
  if ($user)
    return $user;

  global $entityManager;
  $user = new User;
  $user->id   = $username;
  $user->name = $name;
  $user->role = $role;
  $entityManager->persist($user);
  $entityManager->flush();
  return $user;
}

function db_get_all_users() {
  global $entityManager;
  return $entityManager->getRepository('User')->findBy([], ['id' => 'ASC']);
}


/*
function db_update_group($id, $year, $students) {
  $students = implode(',', $students);

  // doesn't update row if it exists already
  db_prepare('INSERT OR IGNORE INTO groups(id, year, students) VALUES (?,?,?)')
    ->execute([$id, $year, $students]);
  db_prepare('UPDATE groups SET students=? WHERE id=? AND year=?')
    ->execute([$students, $id, $year]);
}

function db_get_group_years() {
  global $db;
  return $db->query('SELECT DISTINCT year FROM groups ORDER BY year DESC')
            ->fetchAll(PDO::FETCH_COLUMN, 0);
}

function db_fetch_groups($year) {
  $stmt = db_prepare('SELECT * FROM groups WHERE YEAR=?');
  $stmt->execute([$year]);
  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($data as &$row) {
    $row['students'] = explode(',', $row['students']);
  }
  return $data;
}
*/
