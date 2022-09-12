<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

$config = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/entities'],
                                                       !IN_PRODUCTION,
                                                       '.proxies');

$entityManager = EntityManager::create(['url' => DB_DSN], $config);

function db_flush() {
  global $entityManager;
  $entityManager->flush();
}

function db_fetch_entity($name, $orderby) {
  global $entityManager;
  return $entityManager->getRepository($name)->findBy([], [$orderby => 'ASC']);
}

function db_fetch_user($username) : ?User {
  global $entityManager;
  return $entityManager->find('User', $username);
}

function db_fetch_or_add_user($username, $name, $role, $email = '',
                              $photo = '') : User {
  $user = db_fetch_user($username);
  if ($user) {
    $changed = false;
    if ($user->name != $name) {
      $user->name = $name;
      $changed = true;
    }
    if ($email && $user->email != $email) {
      $user->email = $email;
      $changed = true;
    }
    if ($photo && $user->photo != $photo) {
      $user->photo = $photo;
      $changed = true;
    }
    if ($changed)
      $GLOBALS['entityManager']->flush();
    return $user;
  }

  global $entityManager;
  $user = new User($username, $name, $email, $photo, $role);
  $entityManager->persist($user);
  $entityManager->flush();
  return $user;
}

function db_get_all_users() {
  return db_fetch_entity('User', 'id');
}

function db_get_all_profs() {
  global $entityManager;
  return $entityManager->getRepository('User')->findByRole(ROLE_PROF);
}

function db_save_session($session) {
  global $entityManager;
  $entityManager->persist($session);
  db_flush();
}

function db_fetch_session($id) {
  global $entityManager;
  return $entityManager->find('Session', $id);
}

function db_get_all_sessions() {
  global $entityManager;
  return $entityManager->getRepository('Session')->findAll();
}

function db_delete_session($session) {
  global $entityManager;
  return $entityManager->remove($session);
}

function db_get_group_years() {
  global $entityManager;
  return $entityManager->createQueryBuilder()
                       ->from('ProjGroup', 'g')
                       ->select('g.year')->distinct()
                       ->orderBy('g.year', 'DESC')
                       ->getQuery()
                       ->getArrayResult();
}

function db_fetch_groups($year) {
  global $entityManager;
  return $entityManager->getRepository('ProjGroup')
                       ->findByYear($year, ['group_number' => 'ASC']);
}

function db_fetch_group($year, $number, $shift) : ProjGroup {
  global $entityManager;
  $group = $entityManager->getRepository('ProjGroup')
                         ->findOneBy(['year'=>$year, 'group_number'=>$number]);
  if ($group)
    return $group;
  $group = new ProjGroup($number, $year, $shift);
  $entityManager->persist($group);
  return $group;
}

function db_fetch_shift($year, $name) : Shift{
  global $entityManager;
  $shift = $entityManager->getRepository('Shift')
                         ->findOneBy(['year' => $year, 'name' => $name]);
  if ($shift)
    return $shift;
  $shift = new Shift($name, $year);
  $entityManager->persist($shift);
  return $shift;
}

function db_fetch_group_id($id) : ?ProjGroup {
  global $entityManager;
  return $entityManager->find('ProjGroup', $id);
}

function db_fetch_license($id) : ?License {
  global $entityManager;
  return $entityManager->find('License', $id);
}

function db_update_license($id, $name) {
  global $entityManager;
  $license = $entityManager->find('License', $id);
  if ($license)
    $license->name = $name;
  else
    $entityManager->persist(new License($id, $name));
}

function db_fetch_prog_language($id) : ?ProgLanguage {
  global $entityManager;
  return $entityManager->find('ProgLanguage', $id);
}

function db_insert_prog_language($name) {
  global $entityManager;
  if (!db_fetch_prog_language($name))
    $entityManager->persist(new ProgLanguage($name));
}

function db_fetch_deadline($year) : Deadline {
  global $entityManager;
  $deadline = $entityManager->find('Deadline', $year);
  if (!$deadline) {
    $deadline = new Deadline($year);
    $entityManager->persist($deadline);
    db_flush();
  }
  return $deadline;
}
