<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

function send_email($dsts, $subject, $msg) {
  $email = (new Email())
    ->from(new Address(EMAIL_FROM_ADDR, 'PIC1'))
    ->subject($subject);

  if (IN_PRODUCTION) {
    if (!is_array($dsts))
      $dsts = [$dsts];

    $has_email = false;
    foreach ($dsts as $dst) {
      if ($dst) {
        $email->addTo($dst);
        $has_email = true;
      }
    }
    if (!$has_email)
      return;
  } else {
    $email->to(DEBUG_EMAIL_DST);
    $msg = "PIC1 DEBUG MODE\nTo: " . implode(', ', $dsts) . "\n\n$msg";
  }
  $email->text($msg);

  $transport = Transport::fromDsn(MAILER_DSN);
  $mailer = new Mailer($transport);
  $mailer->send($email);
}

function email_profs($subject, $msg) {
  $emails = [];
  foreach (db_get_all_profs(false) as $prof) {
    $emails[] = $prof->email;
  }
  send_email($emails, $subject, $msg);
}

function email_ta($group, $subject, $msg) {
  if ($ta = $group->shift->prof) {
    send_email($ta->email, $subject, $msg);
  } else {
    email_profs($subject, $msg);
  }
}

function email_group($group, $subject, $msg) {
  $emails = [$group->shift->prof->email];
  foreach ($group->students as $user) {
    $emails[] = $user->email;
  }
  send_email($emails, $subject, $msg);
}
