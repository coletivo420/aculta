<?php

namespace Drupal\aculta_mail_confirmer_test;

use Drupal\Core\Mail\MailManager;

/**
 * A test mail collector.
 */
class MailCollector extends MailManager {

  /**
   * The collected emails.
   *
   * @var array
   */
  protected $emails = [];

  /**
   * {@inheritdoc}
   */
  public function doMail($module, $key, $to, $langcode, $params = [], $reply = NULL, $send = TRUE) {
    $this->emails[] = parent::doMail($module, $key, $to, $langcode, $params, $reply, FALSE);
  }

  /**
   * Returns the collected emails.
   *
   * @return array
   *   An array of emails.
   */
  public function getMails() {
    return $this->emails;
  }

  /**
   * Resets the collected emails.
   */
  public function resetMails() {
    $this->emails = [];
  }

}
