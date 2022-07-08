<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'config.php';
require 'db.php';

$db->exec(<<<EOF
CREATE TABLE IF NOT EXISTS students (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  github_username TEXT,
  github_etag TEXT
) WITHOUT ROWID
EOF);

$db->exec(<<<EOF
CREATE TABLE IF NOT EXISTS groups (
  id INT,
  year INT,
  students TEXT NOT NULL,
  provider INT,
  project_id TEXT,
  project_name TEXT,
  project_description TEXT,
  project_website TEXT,
  needs_CLA INT,
  major_users TEXT,
  locs INT,
  coding_style TEXT,
  beginners_bugs TEXT,
  project_ideas TEXT,
  getting_started TEXT,
  dev_manual TEXT,
  test_manual TEXT,
  mailing_list TEXT,
  patch_submission TEXT,
  comments TEXT,
  PRIMARY KEY (id, year)
) WITHOUT ROWID
EOF);
