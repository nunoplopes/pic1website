<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

$github_builder = new Github\HttpClient\Builder;
$github_client  = new \Github\Client($github_builder);
$github_client->authenticate(GH_TOKEN, null, \Github\AuthMethod::CLIENT_ID);

$github_client->addCache(
  new Symfony\Component\Cache\Adapter\FilesystemAdapter('github', 3*3600,
                                                        '.cache'));

function github_set_etag($etag) {
  $GLOBALS['github_builder']
    ->addPlugin(new Http\Client\Common\Plugin\HeaderSetPlugin([
      'If-None-Match' => $etag,
    ]));
}
