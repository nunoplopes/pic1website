<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

$config = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/entities'],
                                                       !IN_PRODUCTION);

$entityManager = EntityManager::create(['url' => DB_DSN], $config);

function db_flush() {
  global $entityManager;
  $entityManager->flush();
}

function db_fetch_user($username) : ?User {
  global $entityManager;
  return $entityManager->find('User', $username);
}

function db_fetch_or_add_user($username, $name, $role, $email = '') : User {
  $user = db_fetch_user($username);
  if ($user) {
    if ($email && $user->email != $email) {
      $user->email = $email;
      $GLOBALS['entityManager']->flush();
    }
    return $user;
  }

  global $entityManager;
  $user = new User($username, $name, $email, $role);
  $entityManager->persist($user);
  $entityManager->flush();
  return $user;
}

function db_get_all_users() {
  global $entityManager;
  return $entityManager->getRepository('User')->findBy([], ['id' => 'ASC']);
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
  return $entityManager->getRepository('ProjGroup')->find($id);
}

function db_update_license($id, $name) {
  global $entityManager;
  $license = $entityManager->getRepository('License')->find($id);
  if ($license)
    $license->name = $name;
  else
    $entityManager->persist(new License($id, $name));
}
