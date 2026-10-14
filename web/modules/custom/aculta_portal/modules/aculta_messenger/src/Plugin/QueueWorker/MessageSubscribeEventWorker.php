<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\QueueWorker;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\message_subscribe\SubscribersInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Dispatches newly published forum topics to current subscribers. */
#[QueueWorker(
  id: 'aculta_messenger_message_subscribe_event',
  title: new TranslatableMarkup('ACULTA Messenger subscription event'),
  cron: ['time' => 30],
)]
final class MessageSubscribeEventWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const TEMPLATE = 'aculta_messenger_forum_topic_published';

  private const SOURCE_FIELD = 'field_aculta_messenger_source_node';

  private const REVISION_FIELD = 'field_aculta_messenger_source_revision';

  private const STATUS_FIELD = 'field_aculta_messenger_delivery_status';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  private const MAX_ATTEMPTS = 3;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SubscribersInterface $subscribers,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly AliasManagerInterface $aliasManager,
    private readonly LockBackendInterface $lock,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('message_subscribe.subscribers'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('path_alias.manager'),
      $container->get('lock.persistent'),
    );
  }

  /** {@inheritdoc} */
  public function processItem($data): void {
    if (!is_array($data) || !isset($data['node_id']) || !is_numeric($data['node_id'])) {
      return;
    }

    $node = $this->entityTypeManager->getStorage('node')->load((int) $data['node_id']);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'forum' || !$node->isPublished()) {
      return;
    }

    $internalPath = '/node/' . $node->id();
    if ($this->aliasManager->getAliasByPath($internalPath, $node->language()->getId()) === $internalPath) {
      // Do not send a public human-facing email link with an English/Core slug.
      return;
    }

    $revisionId = (int) $node->getRevisionId();
    $lockId = 'aculta_messenger:forum_topic_published:' . $node->id() . ':' . $revisionId;
    if (!$this->lock->acquire($lockId, 60.0)) {
      throw new \RuntimeException('Forum topic publication notification is already being processed.');
    }

    try {
      $storage = $this->entityTypeManager->getStorage('message');
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition(self::SOURCE_FIELD, $node->id())
        ->condition(self::REVISION_FIELD, $revisionId)
        ->range(0, 1)
        ->execute();
      $message = $ids === [] ? NULL : $storage->load(reset($ids));
      if ($message !== NULL && $message->get(self::STATUS_FIELD)->value === 'sent') {
        return;
      }
      if ($message !== NULL && (int) $message->get(self::ATTEMPTS_FIELD)->value >= self::MAX_ATTEMPTS) {
        $message->set(self::STATUS_FIELD, 'failed');
        $message->save();
        return;
      }

      if ($message === NULL) {
        $message = $storage->create([
          'template' => self::TEMPLATE,
          'uid' => $node->getOwnerId(),
          self::SOURCE_FIELD => $node->id(),
          self::REVISION_FIELD => $revisionId,
          self::STATUS_FIELD => 'pending',
          self::ATTEMPTS_FIELD => 0,
        ]);
      }
      $attempts = (int) $message->get(self::ATTEMPTS_FIELD)->value + 1;
      $message->set(self::ATTEMPTS_FIELD, $attempts);
      $message->save();

      $processed = $this->domainPurposeManager->runInPurpose('forum', function () use ($node, $message): bool {
        $url = $this->domainPurposeManager->routeUrl(
          'forum',
          'entity.node.canonical',
          ['node' => $node->id()],
        );
        if ($url === NULL) {
          throw new \RuntimeException('The forum purpose URL is not available.');
        }

        $message->setArguments([
          '@topic_title' => $node->label(),
          '@topic_url' => $url->toString(),
        ]);
        $message->setLanguage('pt-br');
        $message->save();

        $this->subscribers->sendMessage(
          $node,
          $message,
          [],
          [
            'use queue' => FALSE,
            'entity access' => TRUE,
            'notify blocked users' => FALSE,
            'notify message owner' => FALSE,
          ],
          ['node' => [(int) $node->id()]],
        );
        $message->set(self::STATUS_FIELD, 'sent');
        $message->save();
        return TRUE;
      });
      if ($processed !== TRUE) {
        throw new \RuntimeException('The forum purpose is not available for subscription delivery.');
      }
    }
    finally {
      $this->lock->release($lockId);
    }
  }

}
