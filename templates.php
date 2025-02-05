<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\Common\Annotations\AnnotationReader;

function html_header($title) {
  $user = get_user();
  $role = get_role_string();
  $name = htmlspecialchars($user->name);

echo <<< EOF
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
table, th, td {
  border: 1px solid
}
th, td {
  padding: 3px
}
</style>
<title>PIC1: $title</title>
</head>
<body>
<p><img src="{$user->getPhoto()}" alt="Photo"></p>
<p>User: $name ($user->id)<br>
Email: <a href="mailto:$user->email">$user->email</a><br>
Role: $role</p>
EOF;
}

function html_footer() {
  $pages = [
    ['dashboard', 'Statistics', ROLE_STUDENT],
    ['profile', 'Edit profile', ROLE_STUDENT],
    ['listprojects', 'Projects', ROLE_STUDENT],
    ['patches', 'Patches', ROLE_STUDENT],
    ['shifts', 'Shifts', ROLE_PROF],
    ['deadlines', 'Deadlines', ROLE_PROF],
    ['changerole', 'Change Role', ROLE_PROF],
    ['impersonate', 'Impersonate', ROLE_SUDO],
    ['cron', 'Cron', ROLE_PROF],
    ['phpinfo', 'PHP Info', ROLE_PROF],
  ];
  echo '<p>&nbsp;</p><footer>';
  foreach ($pages as $page) {
    if (auth_at_least($page[2]))
      echo dolink($page[0], $page[1]), ' | ';
  }
  echo <<< EOF
<a href="logout.php">Logout</a></footer>
</body>
</html>
EOF;
}

function quote($str) {
  return "'" . htmlspecialchars($str) . "'";
}

function do_ext_link($page, $args = []) {
  return 'https://' . $_SERVER['HTTP_HOST'] . '/' . dourl($page, $args, '&');
}

function link_patch(Patch $patch) {
 return do_ext_link('editpatch', ['id' => $patch->id]);
}

function link_group(ProjGroup $group) {
  return do_ext_link('listproject',  ['id' => $group->id]);
 }

function dourl($page, $args = [], $separator = '&amp;') {
  $args['page'] = $page;
  $q = http_build_query($args, '', $separator);
  return "index.php?$q";
}

function dolink($page, $txt, $args = []) {
  return '<a href="' . dourl($page, $args) . "\">$txt</a>";
}

function mk_box_left_begin() {
  echo '<div style="display: inline-block"><div style="float: left">';
}

function mk_box_right_begin() {
  echo '<div style="float: right; width: 300px; padding: 10px; margin: 10px; ',
       'background: blue; color: white">';
}

function mk_box_end() {
  echo "</div>\n";
}

function mk_deadline_box($deadline) {
  $now = new DateTimeImmutable();
  echo '<div style="float: right; width: 300px; padding: 10px; margin: 10px; ',
       'background: green; color: white">';
  if ($now > $deadline) {
    echo "<p>Deadline has expired!</p>";
  } else {
    echo "<p>Time until deadline: ",
         $deadline->diff($now)->format('%ad, %Hh, %Im, %Ss'), "</p>\n";
  }
  mk_box_end();
}

function print_table($table) {
  if (!$table)
    return;

  echo "<table><tr>\n";
  foreach ($table[0] as $key => $val) {
    echo "<th>$key</th>\n";
  }
  echo "</tr>\n";
  foreach ($table as $row) {
    echo "<tr>\n";
    foreach ($row as $val) {
      if (is_array($val))
        $val = implode("<br>\n", $val);
      echo "<td>$val</td>\n";
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

function handle_form(&$obj, $hide_fields, $readonly, $only_fields = null,
                     $extra_buttons = null, $flush_db = true) {
  $class = new ReflectionClass($obj);
  $docReader = new AnnotationReader();

  if (!empty($_POST['submit'])) {
    $errors = [];
    foreach (get_object_vars($obj) as $name => $val) {
      if (in_array($name, $hide_fields) ||
          in_array($name, $readonly) ||
          ($only_fields && !in_array($name, $only_fields)))
        continue;

      $set = "set_$name";

      if (is_bool($val)) {
        $obj->$set(isset($_POST[$name]));
        continue;
      }

      if (!isset($_POST[$name]))
        continue;

      $set = "set_$name";
      try {
        $obj->$set(trim($_POST[$name]));
      } catch (ValidationException $ex) {
        $errors[$name] = $ex;
      }
    }

    if ($errors) {
      echo "<span style=\"color: red\">\n",
           "<p>Failed to validate all fields:</p><ul>\n";
      foreach ($errors as $name => $error) {
        $print_name = strtr($name, '_', ' ');
        echo "<li>$print_name: ", $error->getMessage(), "</li>\n";
      }
      echo "</ul></span><p>&nbsp;</p>\n";
    } else {
      if ($flush_db)
        db_flush();
      echo '<p style="color: green">Database updated!</p>';
    }
  }

  echo '<form action="',htmlspecialchars($_SERVER['REQUEST_URI']),
                    '" method="post">';
  echo '<input type="hidden" name="submit" value="1">';
  echo "<table>\n";

  $not_all_readonly = false;

  foreach (get_object_vars($obj) as $name => $orig_value) {
    if (in_array($name, $hide_fields) ||
        ($only_fields && !in_array($name, $only_fields)))
      continue;

    $annotations
      = $docReader->getPropertyAnnotations($class->getProperty($name));

    $column = null;
    foreach ($annotations as $t) {
      if (get_class($t) === 'Doctrine\ORM\Mapping\Column') {
        $column = $t;
        break;
      }
    }
    $length = $column ? $column->length : 0;

    $print_name = strtr($name, '_', ' ');

    $getter = "getstr_$name";

    if ($orig_value instanceof DateTimeInterface) {
      $val = $orig_value->format('Y-m-d\TH:i:s');
    } elseif (is_object($orig_value) &&
              $orig_value instanceof \Doctrine\ORM\PersistentCollection) {
      $val = array_map(function($e) { return htmlspecialchars($e); },
                       $orig_value->toArray());
      $val = implode(', ', $val);
    } elseif (method_exists($obj, $getter)) {
      $val = htmlspecialchars($obj->$getter());
    } else {
      $val = htmlspecialchars((string)$orig_value);
    }

    if (str_starts_with($val, 'https://'))
      $print_name = "<a href=\"$val\">$print_name</a>";

    $freeze = '';
    if (in_array($name, $readonly))
      $freeze = ' readonly';
    else
      $not_all_readonly = true;

    echo "<tr><td><label for=\"$name\">$print_name:</label></td><td>\n";
    if (is_bool($orig_value)) {
      $checked = '';
      if ($val)
        $checked = ' checked';
      echo "<input type=\"checkbox\" id=\"$name\" name=\"$name\" ",
           "value=\"true\"$checked>";
    }
    else if ($orig_value instanceof DateTimeInterface) {
      echo "<input type=\"datetime-local\" id=\"$name\" name=\"$name\"",
           " step=1 value=\"$val\">";
    }
    else if (isset($annotations[1]->targetEntity)) {
      $orderby = $annotations[1]->targetEntity::orderBy();
      echo "<select name=\"$name\" id=\"$name\">\n";

      $entities = db_fetch_entity($annotations[1]->targetEntity, $orderby);
      foreach ($entities as $entity) {
        $selected = '';
        if ($entity == $orig_value)
          $selected = ' selected';
        echo "<option value=\"", htmlspecialchars($entity->id), "\"$selected>",
             htmlspecialchars((string)$entity), "</option>\n";
      }
      echo "</select>";
    }
    else if (method_exists($obj, "get_$name"."_options")) {
      if (in_array($name, $readonly))
        $freeze = ' disabled';
      echo "<select name=\"$name\" id=\"$name\"$freeze>\n";

      $method_name = "get_$name"."_options";
      foreach ($obj->$method_name() as $id => $name) {
        $selected = '';
        if ($id == $orig_value)
          $selected = ' selected';
        echo "<option value=\"$id\"$selected>", htmlspecialchars($name),
             "</option>\n";
      }
      echo "</select>";
    }
    else {
      if ($length > 200) {
        echo "<textarea id=\"$name\" name=\"$name\" rows=\"5\" cols=\"60\"",
             "$freeze>$val</textarea>";
      } else {
        echo "<input type=\"text\" id=\"$name\" name=\"$name\" ",
             "value=\"$val\" size=\"60\"$freeze>";
      }
    }
    echo "</td></tr>\n";
  }
  echo "</table><p>";

  if ($not_all_readonly)
    echo "<input type=\"submit\" value=\"Save changes\">";
  echo "</p>\n";

  if ($extra_buttons) {
    echo "<p>";
    foreach ($extra_buttons as $name => $args) {
      $key = $args[0];
      $value = $args[1];
      echo "<input type=\"submit\" value=\"$name\"",
           " onclick=\"this.form.$key.value='$value'\">\n";
    }
    echo "</p>\n";
  }
  echo "</form>\n";
}

function do_start_form($page) {
  echo <<<HTML
<form action="index.php" method="get">
<input type="hidden" name="page" value="$page">

HTML;
}

function do_year_selector() {
  $years = db_get_group_years();
  $selected_year = $_REQUEST['year'] ?? ($years[0]['year'] ?? '');

  echo <<<HTML
<label for="year">Year:</label>
<select name="year" id="year" onchange='this.form.submit()'>
HTML;

  foreach ($years as $year) {
    $year = $year['year'];
    $select = $year == $selected_year ? ' selected' : '';
    echo "<option value=\"$year\"$select>$year/",$year+1,"</option>\n";
  }
  echo "</select>\n<br>\n";

  return $selected_year;
}

function do_bool_selector($label, $var) {
  $yes = !empty($_REQUEST[$var]);
  $checked = $yes ? ' checked' : '';
  echo <<<HTML
<label for="$var">$label</label>
<input type="checkbox" id="$var" name="$var" value="1"
       onchange='this.form.submit()'$checked>
<br>
HTML;
  return $yes;
}

function do_shift_selector($selected_year, $own_shifts_only) {
  $selected_shift
    = isset($_REQUEST['shift']) ? db_fetch_shift_id($_REQUEST['shift']) : null;

  echo <<<HTML
  <label for="shift">Show specific shift:</label>
<select name="shift" id="shift" onchange='this.form.submit()'>
<option value="all">All</option>
HTML;

  foreach (db_fetch_shifts($selected_year) as $shift) {
    if (!has_shift_permissions($shift))
      continue;
    if ($own_shifts_only &&
        $shift->prof != get_user())
      continue;
    $select = $shift == $selected_shift ? ' selected' : '';
    echo "<option value=\"$shift->id\"$select>", htmlspecialchars($shift->name),
         "</option>\n";
  }
  echo "</select>\n<br>\n";

  return $selected_shift;
}

function do_group_selector($selected_year, $selected_shift, $own_shifts_only,
                           $selected_repo) {
echo <<< HTML
<label for="group">Filter by group:</label>
<select name="group" id="group" onchange='this.form.submit()'>
<option value="all">All</option>
HTML;

  $user   = get_user();
  $groups = [];
  foreach (db_fetch_groups($selected_year) as $group) {
    if (!has_group_permissions($group))
      continue;
    if ($own_shifts_only && $group->prof() != $user)
      continue;
    if ($selected_shift && $group->shift != $selected_shift)
      continue;
    if ($selected_repo != 'all' && $group->getRepositoryId() != $selected_repo)
      continue;

    $groups[] = $group;
    $selected = @$_REQUEST['group'] == $group->id ? ' selected' : '';
    echo "<option value=\"{$group->id}\"$selected>", $group->group_number,
         "</option>\n";
  }
  echo "</select>\n<br>\n";

  if (isset($_REQUEST['group']) && $_REQUEST['group'] != 'all') {
    foreach ($groups as $group) {
      if ($group->id == $_REQUEST['group'])
        return [$group];
    }
  }
  return $groups;
}

function do_repo_selector($selected_year) {
  $selected_repo = $_REQUEST['repo'] ?? 'all';
  echo <<<HTML
  <label for="repo">Filter by repository:</label>
  <select name="repo" id="repo" onchange='this.form.submit()'>
  <option value="all">All</option>
  HTML;

    $repos = [];
    foreach (db_fetch_groups($selected_year) as $group) {
      if (!has_group_permissions($group))
        continue;

      if ($repo = $group->getRepositoryId())
        $repos[$repo] = true;
    }
    $repos = array_keys($repos);
    natsort($repos);

    foreach ($repos as $repo) {
      $select = $repo == $selected_repo ? ' selected' : '';
      echo "<option value=\"$repo\"$select>", htmlspecialchars($repo),
           "</option>\n";
    }
    echo "</select>\n<br>\n";
    return $selected_repo;
}
