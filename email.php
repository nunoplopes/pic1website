<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailLoggerSubscriber implements EventSubscriberInterface
{
  public static function getSubscribedEvents() {
      return [ MessageEvent::class => 'onMessage' ];
  }

  public function onMessage(MessageEvent $event): void {
    $message = $event->getMessage();
    $to = array_map(function($x) { return $x->toString(); }, $message->getTo());
    error_log(
      "Sent email to: " . implode(', ', $to) .
      "\nSubject: " . $message->getSubject() .
      "\n\n" . $message->getTextBody());
  }
}

function send_email($dsts, $subject, $msg) {
  $email = (new Email())
    ->from(new Address(EMAIL_FROM_ADDR, 'PIC1'))
    ->subject($subject);

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
  $email->text($msg);

  if (IN_PRODUCTION) {
    $dispatcher = null;
    $bus = null;
  } else {
    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new MailLoggerSubscriber());
    $bus = new MessageBus();
  }

  $transport = Transport::fromDsn(MAILER_DSN);
  $mailer = new Mailer($transport, $bus, $dispatcher);
  $mailer->send($email);
}

function get_prof_emails() {
  $emails = [];
  foreach (db_get_all_profs(false) as $prof) {
    $emails[] = $prof->email;
  }
  return $emails;
}

function email_profs($subject, $msg) {
  send_email(get_prof_emails(), $subject, $msg);
}

function email_ta($group, $subject, $msg) {
  if ($ta = $group->shift->prof) {
    send_email($ta->email, $subject, $msg);
  } else {
    email_profs($subject, $msg);
  }
}

function email_group($group, $subject, $msg) {
  if ($ta = $group->shift->prof) {
    $emails = [$ta->email];
  } else {
    $emails = get_prof_emails();
  }
  foreach ($group->students as $user) {
    $emails[] = $user->email;
  }
  send_email($emails, $subject, $msg);
}
