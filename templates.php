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
  echo '<footer>';
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

function dolink($page, $txt, $args = []) {
  $args['page'] = $page;
  $q = http_build_query($args, '', '&amp;');
  return "<a href=\"index.php?$q\">$txt</a>";
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

function handle_form(&$obj, $hide_fields, $readonly, $only_fields = null) {
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
      db_flush();
      echo '<p style="color: green">Database updated!</p>';
    }
  }

  echo '<form action="',htmlspecialchars($_SERVER['REQUEST_URI']),
                    '" method="post">';
  echo '<input type="hidden" name="submit" value="1">';
  echo "<table>\n";

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
           " value=\"$val\">";
    }
    else if (isset($annotations[1]->targetEntity) &&
             !$annotations[1]->targetEntity::userCanCreate()) {
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
  echo "</table><p><input type=\"submit\"></p></form>\n";
}
