<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

$languages = [
  'C',
  'C++',
  'C#',
  'Clojure',
  'Erlang',
  'Go',
  'Java',
  'JavaScript',
  'Kotlin',
  'Lisp',
  'PHP',
  'Python',
  'Ruby',
  'Rust',
  'Scala',
  'Swift',
  'TypeScript',
  'Zig',
];


$form = $formFactory->createNamedBuilder('', FormType::class)
                    ->setMethod('GET');
$form->add('page', HiddenType::class, [
  'data' => $page,
]);
$form->add('language', ChoiceType::class, [
  'choices'     => array_combine($languages, $languages),
  'placeholder' => 'Select Language',
  'required'    => false,
]);
$form->add('keywords', SearchType::class, [
  'required' => false,
  'attr' => [
    'placeholder' => 'Enter keywords...',
  ],
]);
$form->add('newbies', CheckboxType::class, [
  'label'    => 'Have issues for beginners',
  'required' => false,
]);
$form->add('search', SubmitType::class);
$form = $form->getForm();
$form->handleRequest($request);

$fields = [
  'form' => $form->createView(),
];

if ($form->isSubmitted() && $form->isValid() &&
    (strlen($keywords = $form->get('keywords')->getData() ?? '') |
     strlen($language = $form->get('language')->getData() ?? ''))) {
  try {
    $projs = DiscoverProjects::searchByKeyword($keywords, $language,
      $form->get('newbies')->getData());
  } catch (Exception $ex) {
    terminate($ex->getMessage(), 'discover.html.twig', $fields);
  }

  $topics = [];
  foreach ($projs as $proj) {
    foreach ($proj['topics'] as $t) {
      $topics[$t] = $t;
    }
  }
  if (count($topics) > 0) {
    shuffle($topics);
    $fields['topics'] = $topics;
  }

  foreach ($projs as &$proj) {
    $total = 0;
    $merged = 0;
    foreach (db_get_patch_stats_per_project($proj['name']) as $patches) {
      switch ($patches['status']) {
        case PatchStatus::WaitingReview:
        case PatchStatus::Reviewed:
        case PatchStatus::Approved:
        case PatchStatus::Closed:
          break;

        case PatchStatus::Merged:
        case PatchStatus::MergedIllegal:
          $merged += $patches['patches'];
          // fallthrough

        case PatchStatus::PROpen:
        case PatchStatus::PROpenIllegal:
        case PatchStatus::NotMerged:
        case PatchStatus::NotMergedIllegal:
          $total += $patches['patches'];
          break;

        default:
          die('invalid patch status');
      }
    }
    $proj['patches'] = $total > 0
      ? sprintf("%s (%s%% merged)", $total, round($merged / $total * 100))
      : 'none';
  }
  if (count($projs) > 0) {
    shuffle($projs);
    $fields['projects'] = $projs;
  }

  $fields['language'] = $language;
}

terminate(null, 'discover.html.twig', $fields);
