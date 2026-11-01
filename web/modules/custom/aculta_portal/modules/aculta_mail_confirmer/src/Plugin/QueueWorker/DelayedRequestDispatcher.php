<?php

namespace Drupal\aculta_mail_confirmer\Plugin\QueueWorker;

use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[QueueWorker(
  id: 'aculta_mail_confirmer_requests',
  title: new TranslatableMarkup('Delayed confirmation request dispatcher'),
  cron: ['time' => 30],
)]
/**
 * Processa, no cron, os pedidos de confirmação adiados.
 */
class DelayedRequestDispatcher extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Already processed items.
   *
   * @var array
   */
  protected $already;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The email confirmation service.
   *
   * @var \Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface
   */
  protected $manager;

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
   * Creates a new DelayedRequestDispatcher object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface $manager
   *   The email confirmation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, AcultaMailConfirmerManagerInterface $manager, ConfigFactoryInterface $config_factory, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->already = [];
    $this->entityTypeManager = $entity_type_manager;
    $this->manager = $manager;
    $this->configFactory = $config_factory;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('aculta_mail_confirmer'),
      $container->get('config.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Ignore dupes.
    if (isset($this->already[$data])) {
      return;
    }

    // Delivery pendings requests.
    /** @var \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation */
    $confirmation = $this->entityTypeManager->getStorage('aculta_mail_confirmer')->load($data);
    if (!$confirmation) {
      return;
    }

    $resend_delay = (int) $this->configFactory->get('aculta_mail_confirmer.settings')->get('resendrequest_delay');
    if ($confirmation->getLastRequestDate() + $resend_delay > $this->time->getRequestTime()) {
      throw new RequeueException('Early confirmation request re-send');
    }

    // Remember already processed.
    $this->already[$data] = $data;

    // Deliver the confirmation request.
    if ($this->manager->isPending($confirmation)) {
      $confirmation->setLastRequestDate(NULL);
      if ($this->manager->sendRequest($confirmation)) {
        $confirmation->save();
      }
    }
  }

}
