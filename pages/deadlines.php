<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_require_at_least(ROLE_PROF);

$year = get_current_year();
$obj = db_fetch_deadline($year);

handle_form($obj,
            /* hidden= */[],
            /* readonly= */['year']);
