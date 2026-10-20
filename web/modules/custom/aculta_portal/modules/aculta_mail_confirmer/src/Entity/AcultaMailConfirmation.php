<?php

namespace Drupal\aculta_mail_confirmer\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmationAccessControlHandler;
use Drupal\aculta_mail_confirmer\Form\AcultaMailConfirmerResponseForm;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\aculta_mail_confirmer\InvalidConfirmationStateException;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;

#[ContentEntityType(
  id: 'aculta_mail_confirmer',
  label: new TranslatableMarkup('Email confirmation'),
  label_singular: new TranslatableMarkup('Email confirmation'),
  label_plural: new TranslatableMarkup('Email confirmations'),
  handlers: [
    'access' => AcultaMailConfirmationAccessControlHandler::class,
    'form' => [
      'response' => AcultaMailConfirmerResponseForm::class,
    ],
  ],
  base_table: 'aculta_mail_confirmer',
  entity_keys: [
    'id' => 'cid',
    'uuid' => 'uuid',
    'label' => 'email',
    'langcode' => 'langcode',
    'uid' => 'uid',
  ],
  admin_permission: 'administer email confirmations',
)]
/**
 * Entidade de confirmação de e-mail do ACULTA (incorporada do email_confirmer).
 */
class AcultaMailConfirmation extends ContentEntityBase implements AcultaMailConfirmationInterface {

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->get('email')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['cid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Email confirmation ID'))
      ->setDescription(t('The ID of the email confirmation.'))
      ->setReadOnly(TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t('The email address of this confirmation.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['realm'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Realm'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['ip'] = BaseFieldDefinition::create('string')
      ->setLabel(t('IP address'))
      ->setDescription(t('Confirmation related IP address.'))
      ->setSetting('max_length', 45)
      ->setDefaultValueCallback('Drupal\aculta_mail_confirmer\Entity\AcultaMailConfirmation::getRequestIp')
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('Any arbitrary data to store with the confirmation.'));

    $fields['confirm_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('On confirmation URL'))
      ->setDescription(t('A URL to go after an email confirmation was done.'))
      ->setSetting('max_length', 255);

    $fields['cancel_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('On cancellation URL'))
      ->setDescription(t('A URL to go after an email confirmation was cancelled.'))
      ->setSetting('max_length', 255);

    $fields['error_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('On error URL'))
      ->setDescription(t('A URL to go on email confirmation error.'))
      ->setSetting('max_length', 255);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID who created the email confirmation.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\aculta_mail_confirmer\Entity\AcultaMailConfirmation::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['private'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Private'))
      ->setDescription(t('A boolean indicating whether the email confirmation is private.'))
      ->setDefaultValue(AcultaMailConfirmationInterface::IS_PUBLIC)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Cancelled'))
      ->setDescription(t('A boolean indicating whether the email confirmation is cancelled.'))
      ->setDefaultValue(AcultaMailConfirmationInterface::ACTIVE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['sent'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last request sent on'))
      ->setDescription(t('The time the last request was sent.'))
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => 0,
      ]);

    $fields['confirmed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Confirmed'))
      ->setDescription(t('A boolean indicating whether the email confirmation is confirmed.'))
      ->setDefaultValue(AcultaMailConfirmationInterface::UNCONFIRMED)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Added on'))
      ->setDefaultValueCallback('Drupal\aculta_mail_confirmer\Entity\AcultaMailConfirmation::getRequestTime')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function isPending() {
    return !$this->isExpired()
      && !$this->isCancelled()
      && !$this->isConfirmed();
  }

  /**
   * {@inheritdoc}
   */
  public function isCancelled() {
    return $this->get('status')->value == AcultaMailConfirmationInterface::CANCELLED;
  }

  /**
   * {@inheritdoc}
   */
  public function isConfirmed() {
    return $this->get('confirmed')->value == AcultaMailConfirmationInterface::CONFIRMED;
  }

  /**
   * {@inheritdoc}
   */
  public function isExpired() {
    return \Drupal::time()->getRequestTime() > $this->getCreatedTime() + \Drupal::config('aculta_mail_confirmer.settings')->get('hash_expiration');
  }

  /**
   * {@inheritdoc}
   */
  public function isRequestSent() {
    return !$this->get('sent')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function isPrivate() {
    return $this->get('private')->value == AcultaMailConfirmationInterface::IS_PRIVATE;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrivate($private = TRUE) {
    $this->get('private')->setValue($private ? AcultaMailConfirmationInterface::IS_PRIVATE : AcultaMailConfirmationInterface::IS_PUBLIC);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    if ($this->isExpired()) {
      $status = 'expired';
    }
    elseif ($this->isCancelled()) {
      $status = 'cancelled';
    }
    elseif ($this->isConfirmed()) {
      $status = 'confirmed';
    }
    else {
      $status = 'pending';
    }

    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function sendRequest() {
    $status = $this->getStatus();
    if ($status != 'pending') {
      throw new InvalidConfirmationStateException('Unable to send request email for ' . $status . ' confirmations.');
    }

    // Recently sent?
    if ($this->isRequestSent()
        && $this->getLastRequestDate() + intval(\Drupal::config('aculta_mail_confirmer.settings')->get('resendrequest_delay')) > \Drupal::time()->getRequestTime()) {
      // Add to queue for further processing.
      \Drupal::queue('aculta_mail_confirmer_requests')->createItem($this->id());
      return TRUE;
    }

    // Send the confirmation request.
    $message = \Drupal::service('plugin.manager.mail')->mail('aculta_mail_confirmer',
      'confirmation_request',
      $this->getEmail(),
      $this->language()->getId(),
      ['context' => ['aculta_mail_confirmer' => $this]]);

    if ($ok = !empty($message['result'])) {
      $this->setLastRequestDate(\Drupal::time()->getRequestTime());
    }

    return $ok;
  }

  /**
   * {@inheritdoc}
   */
  public function confirm($hash) {
    $status = $this->getStatus();
    if ($status != 'pending') {
      throw new InvalidConfirmationStateException('Unable to confirm ' . $status . ' confirmations.');
    }

    if ($hash == $this->getHash()) {
      $this->get('confirmed')->setValue(AcultaMailConfirmationInterface::CONFIRMED);
      // Invoke aculta_mail_confirmer hook.
      \Drupal::moduleHandler()->invokeAll('aculta_mail_confirmer', ['confirm', $this]);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function cancel() {
    $status = $this->getStatus();
    if ($status != 'pending') {
      throw new InvalidConfirmationStateException('Unable to cancel ' . $status . ' confirmations.');
    }

    $this->get('status')->setValue(AcultaMailConfirmationInterface::CANCELLED);
    // Invoke aculta_mail_confirmer hook.
    \Drupal::moduleHandler()->invokeAll('aculta_mail_confirmer', ['cancel', $this]);
  }

  /**
   * {@inheritdoc}
   */
  public function getHash() {
    $data = $this->getEmail()
      . $this->getCreatedTime()
      . $this->getIp() ?: '';
    return Crypt::hmacBase64($data, \Drupal::service('private_key')->get());
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->get('email')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail($email) {
    $this->get('email')->setValue($email);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRealm() {
    return $this->get('realm')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setRealm($realm) {
    $this->get('realm')->setValue($realm);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIp() {
    return $this->get('ip')->isEmpty() ? FALSE : $this->get('ip')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setIp($ip) {
    if (ip2long($ip) === FALSE) {
      throw new \InvalidArgumentException($ip . ' is not a valid IP address.');
    }
    $this->get('ip')->setValue($ip);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperty($key = NULL) {
    $data_field = $this->get('data');
    $values = $data_field->isEmpty() ? [] : $data_field->first()->toArray();
    if ($key) {
      return isset($values[$key]) ? $values[$key] : NULL;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function setProperty($key, $value = NULL) {
    $item = $this->get('data')->isEmpty() ? $this->get('data')->appendItem() : $this->get('data')->first();
    $map = $item->getValue();
    if ($value === NULL && isset($map[$key])) {
      unset($map[$key]);
    }
    else {
      $map[$key] = $value;
    }
    $item->setValue($map);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties() {
    $map = [];
    if (!$this->get('data')->isEmpty()) {
      $map = $this->get('data')->first()->getValue();
    }
    return $map;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastRequestDate() {
    return $this->get('sent')->isEmpty() ? FALSE : intval($this->get('sent')->getString());
  }

  /**
   * {@inheritdoc}
   */
  public function setLastRequestDate($timestamp) {
    $this->get('sent')->setValue($timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->get('created')->setValue($timestamp);
    return $this;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Default value callback for 'ip' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getRequestIp() {
    return [\Drupal::request()->getClientIp()];
  }

  /**
   * Default value callback for 'created' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getRequestTime() {
    return [\Drupal::time()->getRequestTime()];
  }

  /**
   * {@inheritdoc}
   */
  public function setResponseUrl(Url $url, $operation = NULL) {
    $operations = $operation ? [$operation] : ['confirm', 'cancel', 'error'];
    foreach ($operations as $operation) {
      $this->get($operation . '_url')->setValue($url->toUriString());
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseUrl($operation) {
    $uri = $this->get($operation . '_url')->getString();
    return $uri ? Url::fromUri($uri) : NULL;
  }

}
