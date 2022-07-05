<?php

// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

// API doc: https://fenixedu.org/dev/api/

require 'config.php';
ini_set('user_agent', USERAGENT);

function get($path, $year = null) {
  $url = "https://fenix.tecnico.ulisboa.pt/api/fenix/v1/$path";
  if ($year)
    $url .= "?academicTerm=$year";
  return json_decode(@file_get_contents($url));
}

// get a string like 2004/2005
function get_term() {
  $year = (int)date('Y');
  if (date('n') >= MONTH_NEW_YEAR)
    return "$year/" . ($year+1);
  return ($year-1) . "/$year";
}

function get_course_ids($year) {
  $ids = [];
  foreach (get("degrees", $year) as $degree) {
    if (array_search($degree->acronym, FENIX_DEGREES) !== false) {
      foreach (get("degrees/".$degree->id."/courses") as $course) {
        if (array_search($course->acronym, FENIX_COURSES)  !== false) {
          $ids[] = $course->id;
        }
      }
    }
  }
  return array_unique($ids);
}

function get_groups($course) {
  $groups = [];
  foreach (get("courses/$course/groups")[0]->associatedGroups as $group) {
    foreach ($group->members as $m) {
      $groups[$group->groupNumber][] = $m->username;
    }
  }
  return $groups;
}
