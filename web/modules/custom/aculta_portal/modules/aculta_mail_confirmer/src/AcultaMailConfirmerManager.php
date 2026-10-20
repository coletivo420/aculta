<?php

namespace Drupal\aculta_mail_confirmer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Email confirmation service.
 */
class AcultaMailConfirmerManager implements AcultaMailConfirmerManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The aculta_mail_confirmer config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Construct an AcultaMailConfirmerManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('aculta_mail_confirmer.settings');
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function confirm($email, array $data = [], $realm = '') {
    $confirmation = $this->getConfirmation($email, 'pending', $realm);
    if (!$confirmation) {
      $confirmation = $this->createConfirmation($email);
    }

    // Set the realm.
    if (!empty($realm)) {
      $confirmation->setRealm($realm);
    }

    // Store properties.
    foreach ($data as $key => $value) {
      $confirmation->setProperty($key, $value);
    }

    $confirmation->sendRequest();
    $confirmation->save();

    return $confirmation;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmations($email, $status = FALSE, $limit = 0, $realm = '') {
    $results = [];
    $confirmation_query = $this->entityTypeManager->getStorage('aculta_mail_confirmer')->getQuery();

    // Add realm filter condition.
    if (!empty($realm)) {
      $confirmation_query->condition('realm', $realm);
    }

    // Add limit condition.
    if ($limit) {
      $confirmation_query->range(0, $limit);
    }

    // Filter by status.
    switch ($status) {
      case 'expired':
        $confirmation_query->condition('created', $this->time->getRequestTime() - $this->config->get('hash_expiration'), '<');
        break;

      case 'cancelled':
        $confirmation_query->condition('status', AcultaMailConfirmationInterface::CANCELLED);
        break;

      case 'confirmed':
        $confirmation_query->condition('confirmed', AcultaMailConfirmationInterface::CONFIRMED);
        break;

      case 'pending':
        // Non cancelled, confirmed and expired.
        $confirmation_query->condition('status', AcultaMailConfirmationInterface::ACTIVE)
          ->condition('confirmed', AcultaMailConfirmationInterface::UNCONFIRMED)
          ->condition('created', $this->time->getRequestTime() - $this->config->get('hash_expiration'), '>');
        break;
    }

    $old_confirmations = $confirmation_query
      ->condition('email', $email)
      ->accessCheck()
      ->sort('created', 'DESC')
      ->execute();

    $confirmation_storage = $this->entityTypeManager->getStorage('aculta_mail_confirmer');
    if (is_array($old_confirmations) && count($old_confirmations)) {
      // Return existent email confirmation.
      $results = array_values($confirmation_storage->loadMultiple($old_confirmations));
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmation($email, $status = FALSE, $realm = '') {
    $confirmation = NULL;
    $confirmations = $this->getConfirmations($email, $status, 1, $realm);
    if (count($confirmations)) {
      $confirmation = $confirmations[0];
    }
    return $confirmation;
  }

  /**
   * {@inheritdoc}
   */
  public function createConfirmation($email) {
    return $this->entityTypeManager->getStorage('aculta_mail_confirmer')->create([
      'email' => $email,
    ]);
  }

}
