<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

function review_patch($project_name, $bug_fix, $patch, $patch_description, $issue_url, $issue_description, $coding_standard_url) {
  if ($coding_standard_url) {
    $std = fetch_coding_standard($coding_standard_url);
    $coding_standard = <<<TXT
-----------------------------------------------

The coding style of the project is documented at: $coding_standard_url
It is reproduced below:
$std
TXT;
  } else {
    $coding_standard = '';
  }

  if ($bug_fix) {
    $todo = "fix a bug";
  } else {
    $todo = "implement a new feature";
  }

  if ($issue_url) {
    $issue = "The patch aims to resolve issue $issue_url";
  } else {
    $issue = '';
  }

  if ($issue_description) {
    $issue_description
      = "The description of the issue is as follows:\n$issue_description";
  }

$message = <<<TXT
You are a computer science professor reviewing a student's patch for an introductory course on open-source software.
Most patches have subtle issues that need to be caught.
Be nice and constructive in your feedback, as the student is still learning, but point out **all** mistakes.
If unsure about correctness, state so clearly. Only say something is correct if you are fully sure.

You are to answer only in English.

Answer in **Markdown**.
Requirements:
  - Use headings
  - Use bullet lists when appropriate
  - Use fenced code blocks for any formulas or code
  - Can use emphasis like **bold** and *italic* to highlight key points
  - Can use emojis to enhance readability

Review the patch and provide constructive feedback, focusing on:
  - Spelling and Grammar: Are there any spelling or grammatical errors in the function and variable names, code comments, documentation, or commit messages?
  - Correctness: Does the patch correctly implement the intended functionality? Be especially vigilant for edge cases and potential bugs. Does the patch introduce any new bugs or regressions?
  - Code Quality: Is the code well-structured, readable, and maintainable? Does it follow the project's coding standard? Does it avoid code smells and anti-patterns? Does it change existing code in a minimal and non-intrusive way? Does it make unnecessary changes to unrelated parts of the codebase?
  - Testing: Are there sufficient tests included to verify the new functionality? Do the tests cover edge cases?
  - Documentation: Is the code adequately documented? Are there comments explaining complex sections?
  - Performance: Does the patch introduce any performance improvements or regressions?
  - Security: Are there any potential security vulnerabilities introduced by the patch?
  - Best Practices: Does the patch adhere to best practices for the programming language and framework used in the project?


The student has submitted a patch to $todo in the open-source project $project_name.
$issue

$issue_description

-----------------------------------------------

Description of the patch provided by the student:
$patch_description

-----------------------------------------------

The patch is as follows:
$patch

$coding_standard
TXT;

  $postFields = [
    'channel_id'  => AI_CHANNEL_ID,
    'thread_id'   => bin2hex(random_bytes(16)),
    'user_info'   => '{}',
    'message'     => $message,
  ];

  $ch = curl_init(AI_ENDPOINT);

  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_HTTPHEADER     => [
      'x-api-key: ' . AI_APIKEY,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
  ]);

  $response = curl_exec($ch);

  if ($response === false) {
    throw new Exception('cURL error when contacting AI service');
  } else {
    foreach (explode("\n", $response) as $line) {
      if (str_contains($line, ', "type": "message",')) {
        $data = json_decode($line, true);
        return $data['content']['content'];
      }
    }
  }
  throw new Exception('No valid response from AI service');
}


use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

function fetch_coding_standard(string $url): string {
  $ttl = 10 * 24 * 60 * 60; // 10 days
  $cache = new FilesystemAdapter('coding_standard', $ttl, __DIR__.'/.cache');
  $cacheKey = 'coding_std_' . md5($url);

  $item = $cache->getItem($cacheKey);
  if ($item->isHit())
    return $item->get();

  $client = Psr18ClientDiscovery::find();
  $requestFactory = Psr17FactoryDiscovery::findRequestFactory();

  $request = $requestFactory
    ->createRequest('GET', $url)
    ->withHeader('User-Agent', 'USERAGENT');

  $response = $client->sendRequest($request);
  $status = $response->getStatusCode();
  if ($status >= 400)
    return ''; // could be a temporary error, skip caching

  $html = (string)$response->getBody();
  $converter = new HtmlConverter(['strip_tags' => true]);
  $content = $converter->convert($html);

  // Cache it
  $item->set($content);
  $item->expiresAfter($ttl);
  $cache->save($item);

  return $content;
}
