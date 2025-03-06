<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Embera\Embera;
use Embera\Cache\Filesystem;
use Embera\Http\HttpClient;
use Embera\Http\HttpClientCache;

function get_video_info($url, $maxwidth = 500, $maxheight = 500) {
  $cache = new HttpClientCache(new HttpClient());
  $cache->setCachingEngine(new Filesystem('.cache', 8 * 3600));

  $embera = new Embera([
    'fake_responses' => Embera::DISABLE_FAKE_RESPONSES,
    'user_agent'     => USERAGENT,
    'https_only'     => true,
    'responsive'     => true,
    'maxheight'      => $maxheight,
    'maxwidth'       => $maxwidth,
  ], null, $cache);

  $data = $embera->getUrlData($url);
  if (empty($data[$url])) {
    throw new ValidationException('Video not found or URL not recognized');
  }
  $data = $data[$url];

  if ($data['type'] != 'video') {
    throw new ValidationException('URL is not a video ');
  }
  return $data;
}


function get_video_html($url, $hidden = true) {
  if (!$url)
    return '';

  $video = get_video_info($url)['html_pre_responsive'];
  if (!$hidden)
    return $video;

  return ['html' => <<<HTML
<button class="btn btn-primary" onclick="toggleVideo(this)">Show Video</button>
<div style="display: none; margin-top: 10px">$video</div>
HTML, 'width' => 100];
}
