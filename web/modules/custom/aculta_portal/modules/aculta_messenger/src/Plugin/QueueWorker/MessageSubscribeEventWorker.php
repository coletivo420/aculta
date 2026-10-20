<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\QueueWorker;

use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Atualização de tópico seguido: vira aviso por pessoa, não mensagem (ACULTA Fórum, F5.2, F-D21).
 *
 * Eventos chegam da fila de publicação. Revisão já contada não conta de novo (dedupe no próprio aviso).
 * Tópico sem alias espera a geração do alias, pois o aviso leva ao link público do tópico.
 */
#[QueueWorker(
  id: 'aculta_messenger_message_subscribe_event',
  title: new TranslatableMarkup('ACULTA Messenger subscription event'),
  cron: ['time' => 30],
)]
final class MessageSubscribeEventWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AvisoService $avisos,
    private readonly AliasManagerInterface $aliasManager,
    private readonly LockBackendInterface $lock,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('aculta_messenger.aviso_service'),
      $container->get('path_alias.manager'),
      $container->get('lock.persistent'),
      $container->get('logger.factory'),
    );
  }

  public function processItem($data): void {
    if (!is_array($data) || !isset($data['node_id'], $data['revision_id'])
      || !is_numeric($data['node_id']) || !is_numeric($data['revision_id'])) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $current = $storage->load((int) $data['node_id']);
    $node = $storage->loadRevision((int) $data['revision_id']);
    if (!$current instanceof NodeInterface || !$current->isPublished()
      || !$node instanceof NodeInterface || $node->bundle() !== 'aculta_forum_topic' || !$node->isPublished()
      || (int) $node->id() !== (int) $current->id()
      || ($node->hasField('field_autor_legado') && !$node->get('field_autor_legado')->isEmpty())) {
      return;
    }

    $internalPath = '/node/' . $node->id();
    if ($this->aliasManager->getAliasByPath($internalPath, $node->language()->getId()) === $internalPath) {
      // Sem alias português o aviso não tem link público. Espera o pathauto por uma semana, depois descarta com erro.
      if ((int) $node->getCreatedTime() < time() - 7 * 86400) {
        $this->loggerFactory->get('aculta_messenger')->error('Forum topic @id still has no public alias after a week; the update notice was dropped.', ['@id' => $node->id()]);
        return;
      }
      $this->loggerFactory->get('aculta_messenger')->warning('Forum topic @id has no public alias yet; the update notice was requeued.', ['@id' => $node->id()]);
      throw new RequeueException();
    }

    $revisionId = (int) $data['revision_id'];
    $lockId = 'aculta_messenger:forum_topic_updated:' . $node->id() . ':' . $revisionId;
    if (!$this->lock->acquire($lockId, 60.0)) {
      throw new \RuntimeException('Forum topic update notice is already being processed.');
    }
    try {
      $this->avisos->notificarAtualizacao($node, $revisionId);
    }
    finally {
      $this->lock->release($lockId);
    }
  }

}
