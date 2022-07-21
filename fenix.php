<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

// API doc: https://fenixedu.org/dev/api/

require_once 'include.php';

function get_auth_redirect_url() {
  return $_SERVER['SCRIPT_URI'] . '?fenixlogin';
}

function fenix_get_auth_url() {
  $data = [
    'client_id'    => FENIX_APP_ID,
    'redirect_uri' => get_auth_redirect_url(),
  ];
  $data = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
  return "https://fenix.tecnico.ulisboa.pt/oauth/userdialog?$data";
}

function fenix_do_post($url, $data) {
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_USERAGENT, USERAGENT);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POST, true);
  $data = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
  return json_decode(curl_exec($curl));
}

function fenix_get_auth_token($code) {
  $url = 'https://fenix.tecnico.ulisboa.pt/oauth/access_token';
  $data = [
    'client_id'     => FENIX_APP_ID,
    'client_secret' => FENIX_CLIENT_SECRET,
    'redirect_uri'  => get_auth_redirect_url(),
    'code'          => $code,
    'grant_type'    => 'authorization_code',
  ];
  $auth = fenix_do_post($url, $data);

  if (isset($auth['expires_in']))
    $auth['expires_in'] = time() + (int)$auth['expires_in'];
  return $auth;
}

function fenix_reauth_if_needed($auth) {
  if (time() < $auth['expires_in'])
    return;

  $url = 'https://fenix.tecnico.ulisboa.pt/oauth/refresh_token';
  $data = [
    'client_id'     => FENIX_APP_ID,
    'client_secret' => FENIX_CLIENT_SECRET,
    'refresh_token' => $auth['refresh_token'],
    'grant_type'    => 'refresh_token',
  ];
  $auth = fenix_do_post($url, $data);

  if (isset($auth['expires_in']))
    $auth['expires_in'] = time() + (int)$auth['expires_in'];
  return $auth;
}

function get_fnx($path, $year = null, $auth = null) {
  $data = [];
  if ($year)
    $data['academicTerm'] = $year;
  if ($auth)
    $data['access_token'] = $auth['access_token'];

  $data = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
  $url = "https://fenix.tecnico.ulisboa.pt/api/fenix/v1/$path?$data";
  return json_decode(@file_get_contents($url));
}

function get_current_year() {
  $year = (int)date('Y');
  return date('n') >= MONTH_NEW_YEAR ? $year : ($year-1);
}

// get a string like 2004/2005
function get_term() {
  $year = get_current_year();
  return "$year/" . ($year+1);
}

function fenix_get_personal_data($auth) {
  $data = get_fnx('person', null, $auth);
  return [
    'name'     => $data['name'],
    'username' => $data['username'],
    'email'    => $data['email'],
  ];
}

function get_course_ids($year) {
  $ids = [];
  foreach (get_fnx("degrees", $year) as $degree) {
    if (array_search($degree->acronym, FENIX_DEGREES) !== false) {
      foreach (get_fnx("degrees/".$degree->id."/courses") as $course) {
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
  $data = get_fnx("courses/$course/groups");
  if (!$data)
    return [];
  foreach ($data[0]->associatedGroups as $group) {
    foreach ($group->members as $m) {
      $groups[$group->groupNumber][$m->username] = $m->name;
    }
  }
  return $groups;
}
