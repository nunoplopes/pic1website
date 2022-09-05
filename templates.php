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
    ['listprojects', 'Display projects', ROLE_STUDENT],
    ['changerole', 'Change Role', ROLE_PROF],
    ['impersonate', 'Impersonate', ROLE_SUDO],
    ['phpinfo', 'PHP Info', ROLE_PROF],
  ];
  echo '<p>';
  foreach ($pages as $page) {
    if (auth_at_least($page[2]))
      echo dolink($page[0], $page[1]), ' | ';
  }
  echo <<< EOF
<a href="logout.php">Logout</a></p>
</body>
</html>
EOF;
}

function dolink($page, $txt, $args = []) {
  $args['page'] = $page;
  $q = http_build_query($args, '', '&amp;');
  return "<a href=\"index.php?$q\">$txt</a>";
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
        $val = implode('<br>', $val);
      echo "<td>$val</td>\n";
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

function handle_form(&$obj, $hide_fields, $readonly) {
  if (!empty($_POST['submit'])) {
    foreach (get_object_vars($obj) as $name => $val) {
      if (in_array($name, $hide_fields) ||
          in_array($name, $readonly) ||
          !isset($_POST[$name]))
        continue;
      $set = "set_$name";
      $obj->$set(trim($_POST[$name]));
    }
    db_flush();
  }

  echo '<form action="',htmlspecialchars($_SERVER['REQUEST_URI']),
                    '" method="post">';
  echo '<input type="hidden" name="submit" value="1">';
  echo "<table>\n";

  $class = new ReflectionClass($obj);
  $docReader = new AnnotationReader();

  foreach (get_object_vars($obj) as $name => $orig_value) {
    if (in_array($name, $hide_fields))
      continue;

    $types = $docReader->getPropertyAnnotations($class->getProperty($name));

    $print_name = strtr($name, '_', ' ');
    $val = htmlspecialchars((string)$orig_value);
    $freeze = '';
    if (in_array($name, $readonly))
      $freeze = ' readonly';

    echo "<tr><td><label for=\"$name\">$print_name:</label></td><td>\n";
    if ($types[0]->type == "boolean") {
      $checked = '';
      if ($val)
        $checked = ' checked';
      echo "<input type=\"checkbox\" id=\"$name\" name=\"$name\" ",
           "value=\"true\"$checked>";
    }
    else if (isset($types[1]->targetEntity)) {
      $orderby = $types[1]->targetEntity::orderBy();
      echo "<select name=\"$name\" id=\"$name\">\n";

      foreach (db_fetch_entity($types[1]->targetEntity, $orderby) as $entity) {
        $selected = '';
        if ($entity == $orig_value)
          $selected = ' selected';
        echo "<option value=\"", htmlspecialchars($entity->id), "\"$selected>",
             htmlspecialchars((string)$entity), "</option>\n";
      }
      echo "</select>";
    }
    else {
      echo "<input type=\"text\" id=\"$name\" name=\"$name\" ",
           "value=\"$val\" size=\"60\"$freeze>";
    }
    echo "</td></tr>\n";
  }
  echo "</table><p><input type=\"submit\"></p></form>\n";
}
