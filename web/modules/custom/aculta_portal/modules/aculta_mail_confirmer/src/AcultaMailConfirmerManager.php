<?php

namespace Drupal\aculta_mail_confirmer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Email confirmation service.
 *
 * Concentra o estado, o envio, o hash e as transições de uma confirmação. A
 * entidade guarda os dados; os serviços vêm por injeção de dependência.
 */
class AcultaMailConfirmerManager implements AcultaMailConfirmerManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The private key, used to sign the confirmation hash.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected $privateKey;

  /**
   * Construct an AcultaMailConfirmerManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\PrivateKey $private_key
   *   The private key.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, TimeInterface $time, MailManagerInterface $mail_manager, ModuleHandlerInterface $module_handler, QueueFactory $queue_factory, AccountProxyInterface $current_user, RequestStack $request_stack, PrivateKey $private_key) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->time = $time;
    $this->mailManager = $mail_manager;
    $this->moduleHandler = $module_handler;
    $this->queueFactory = $queue_factory;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->privateKey = $private_key;
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

    $this->sendRequest($confirmation);
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

    $hash_expiration = $this->hashExpiration();
    // Filter by status.
    switch ($status) {
      case 'expired':
        $confirmation_query->condition('created', $this->time->getRequestTime() - $hash_expiration, '<');
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
          ->condition('created', $this->time->getRequestTime() - $hash_expiration, '>');
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
    $request = $this->requestStack->getCurrentRequest();
    return $this->entityTypeManager->getStorage('aculta_mail_confirmer')->create([
      'email' => $email,
      'uid' => $this->currentUser->id(),
      'ip' => $request?->getClientIp(),
      'created' => $this->time->getRequestTime(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(AcultaMailConfirmationInterface $confirmation) {
    if ($this->isExpired($confirmation)) {
      $status = 'expired';
    }
    elseif ($confirmation->isCancelled()) {
      $status = 'cancelled';
    }
    elseif ($confirmation->isConfirmed()) {
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
  public function isExpired(AcultaMailConfirmationInterface $confirmation) {
    // Same boundary as the pending query in getConfirmations() and the Portal's
    // SingleUseLinkPolicy::isExpired(): a request is expired from its TTL on.
    return $this->time->getRequestTime() - $confirmation->getCreatedTime() >= $this->hashExpiration();
  }

  /**
   * {@inheritdoc}
   */
  public function isPending(AcultaMailConfirmationInterface $confirmation) {
    return !$this->isExpired($confirmation)
      && !$confirmation->isCancelled()
      && !$confirmation->isConfirmed();
  }

  /**
   * {@inheritdoc}
   */
  public function getHash(AcultaMailConfirmationInterface $confirmation) {
    $data = $confirmation->getEmail()
      . $confirmation->getCreatedTime()
      . $confirmation->getIp();
    return Crypt::hmacBase64($data, $this->privateKey->get());
  }

  /**
   * {@inheritdoc}
   */
  public function sendRequest(AcultaMailConfirmationInterface $confirmation) {
    $status = $this->getStatus($confirmation);
    if ($status != 'pending') {
      throw new InvalidConfirmationStateException('Unable to send request email for ' . $status . ' confirmations.');
    }

    $resend_delay = (int) $this->configFactory->get('aculta_mail_confirmer.settings')->get('resendrequest_delay');
    // Recently sent?
    if ($confirmation->isRequestSent()
        && $confirmation->getLastRequestDate() + $resend_delay > $this->time->getRequestTime()) {
      // Add to queue for further processing.
      $this->queueFactory->get('aculta_mail_confirmer_requests')->createItem($confirmation->id());
      return TRUE;
    }

    // Send the confirmation request.
    $message = $this->mailManager->mail('aculta_mail_confirmer',
      'confirmation_request',
      $confirmation->getEmail(),
      $confirmation->language()->getId(),
      ['context' => ['aculta_mail_confirmer' => $confirmation]]);

    if ($ok = !empty($message['result'])) {
      $confirmation->setLastRequestDate($this->time->getRequestTime());
    }

    return $ok;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmWithHash(AcultaMailConfirmationInterface $confirmation, $hash) {
    $status = $this->getStatus($confirmation);
    if ($status != 'pending') {
      throw new InvalidConfirmationStateException('Unable to confirm ' . $status . ' confirmations.');
    }

    if (hash_equals($this->getHash($confirmation), (string) $hash)) {
      $confirmation->get('confirmed')->setValue(AcultaMailConfirmationInterface::CONFIRMED);
      // Invoke aculta_mail_confirmer hook.
      $this->moduleHandler->invokeAll('aculta_mail_confirmer', ['confirm', $confirmation]);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelConfirmation(AcultaMailConfirmationInterface $confirmation) {
    $status = $this->getStatus($confirmation);
    if ($status != 'pending') {
      throw new InvalidConfirmationStateException('Unable to cancel ' . $status . ' confirmations.');
    }

    $confirmation->get('status')->setValue(AcultaMailConfirmationInterface::CANCELLED);
    // Invoke aculta_mail_confirmer hook.
    $this->moduleHandler->invokeAll('aculta_mail_confirmer', ['cancel', $confirmation]);
  }

  /**
   * Returns the confirmation lifetime, in seconds.
   *
   * @return int
   *   The hash_expiration setting.
   */
  protected function hashExpiration(): int {
    return (int) $this->configFactory->get('aculta_mail_confirmer.settings')->get('hash_expiration');
  }

}
