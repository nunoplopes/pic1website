<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Project List');

$years = db_get_group_years();
print_r($years);

?>
