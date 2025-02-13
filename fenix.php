<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

// API doc: https://fenixedu.org/dev/api/

require_once 'include.php';

function get_auth_redirect_url() {
  return 'https://' . $_SERVER['HTTP_HOST'] . '/?fenixlogin';
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
  if (!$auth || isset($auth->error))
    return null;

  $auth->expires_in = time() + (int)$auth->expires_in;
  return $auth;
}

function fenix_reauth_if_needed($auth) {
  if (time() < $auth->expires_in)
    return;

  $url = 'https://fenix.tecnico.ulisboa.pt/oauth/refresh_token';
  $data = [
    'client_id'     => FENIX_APP_ID,
    'client_secret' => FENIX_CLIENT_SECRET,
    'refresh_token' => $auth->refresh_token,
    'grant_type'    => 'refresh_token',
  ];
  $auth = fenix_do_post($url, $data);
  if (isset($auth->error))
    return null;

  $auth->expires_in = time() + (int)$auth->expires_in;
  return $auth;
}

function get_fnx($path, $year = null, $auth = null) {
  $data = [];
  if ($year)
    $data['academicTerm'] = $year;
  if ($auth)
    $data['access_token'] = $auth->access_token;

  $data = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
  $url = "https://fenix.tecnico.ulisboa.pt/api/fenix/v1/$path?$data";
  if (!($data = @file_get_contents($url)))
    die("Fenix is dead; try again!\n");
  return json_decode($data);
}

function get_current_year() {
  $year = (int)date('Y');
  return date('n') >= MONTH_NEW_YEAR ? $year : ($year-1);
}

// get a string like 2004/2005
function get_term_for($year) {
  return "$year/" . ($year+1);
}

function get_term() {
  return get_term_for(get_current_year());
}

function fenix_get_personal_data($auth) {
  $data = get_fnx('person', null, $auth);
  return [
    'name'     => $data->displayName,
    'username' => $data->username,
    'email'    => $data->email,
    'photo'    => "data:{$data->photo->type};base64,{$data->photo->data}",
  ];
}

function get_course_ids($year) {
  $ids = [];
  foreach (get_fnx("degrees", $year) as $degree) {
    if (array_search($degree->acronym, FENIX_DEGREES) !== false) {
      foreach (get_fnx("degrees/".$degree->id."/courses", $year) as $course) {
        if (array_search($course->acronym, FENIX_COURSES) !== false) {
          $ids[] = $course->id;
        }
      }
    }
  }
  $data = array_unique($ids);
  sort($data);
  return $data;
}

// returns array of [shift-name, [username => name]*]
function get_groups($course) {
  $groups = [];
  $data = get_fnx("courses/$course/groups");
  if (!$data)
    return [];
  foreach ($data as $proj) {
    foreach ($proj->associatedGroups as $group) {
      $students = [];
      foreach ($group->members as $m) {
        $students[$m->username] = $m->name;
      }
      if (!$students)
        continue;
      assert(empty($groups[$group->groupNumber]));
      $groups[$group->groupNumber] = [trim($group->shift), $students];
    }
  }
  return $groups;
}

// returns array of [id, name, role]
function get_course_teachers($course) {
  $profs = [];
  $data = get_fnx("courses/$course");
  if (!$data)
    return [];
  foreach ($data->teachers as $prof) {
    // TODO: the API doesn't allow us to distinguish between PROF & TA
    $profs[] = [$prof->istId, $prof->name, ROLE_PROF];
  }
  return $profs;
}
