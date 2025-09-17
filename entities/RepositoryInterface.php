<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

interface RepositoryInterface {
  static function parse($url) : ?string;
  static function defaultBranch($name) : string;
  static function parent($name) : ?string;
  static function language($name) : string;
  static function license($name) : ?string;
  static function stars($name) : int;
  static function topics($name) : array;
  static function linesOfCode($name) : int;
  static function commitsLastMonth($name) : int;
  static function toString($name) : string;
}
