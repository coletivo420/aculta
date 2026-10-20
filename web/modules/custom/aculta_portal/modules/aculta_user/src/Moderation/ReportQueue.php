<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Moderation;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Fila de denúncias do Fórum (ACULTA Fórum, F5.1).
 *
 * Não há contador persistido: cada item é derivado dos registros Flag, um por
 * conta e conteúdo. O Flag garante a deduplicação e é a fonte de verdade das
 * denúncias. Um limite de leitura impede varredura sem fim; acima dele a fila
 * mostra o que coube e avisa.
 */
final class ReportQueue {

  /**
   * Sinalizações do Fórum (tópico e comentário).
   */
  public const FLAGS = ['sinalizar_topico', 'sinalizar_comentario'];

  /**
   * Registros Flag lidos por consulta.
   */
  public const SCAN_LIMIT = 500;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Retorna denúncias agrupadas por conteúdo em ordem recente.
   *
   * @return array{items: array<int, array{entity_type: string, entity_id: int, total: int, ultima: int}>, truncado: bool}
   *   Denúncias agrupadas e indicação se o limite de leitura foi excedido.
   */
  public function items(): array {
    $storage = $this->entityTypeManager->getStorage('flagging');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', self::FLAGS, 'IN')
      ->condition('entity_type', ['node', 'comment'], 'IN')
      ->sort('created', 'DESC')
      ->range(0, self::SCAN_LIMIT + 1)
      ->execute();
    $truncado = count($ids) > self::SCAN_LIMIT;
    $ids = array_slice($ids, 0, self::SCAN_LIMIT);

    $flaggings = $storage->loadMultiple($ids);
    $target_ids = [];
    foreach ($flaggings as $flagging) {
      $entity_type = $flagging->getFlaggableType();
      if (in_array($entity_type, ['node', 'comment'], TRUE)) {
        $target_ids[$entity_type][] = $flagging->getFlaggableId();
      }
    }
    $targets = [];
    foreach ($target_ids as $entity_type => $entity_ids) {
      $targets[$entity_type] = $this->entityTypeManager->getStorage($entity_type)
        ->loadMultiple(array_unique($entity_ids));
    }

    $grupos = [];
    foreach ($flaggings as $flagging) {
      $entity = $targets[$flagging->getFlaggableType()][$flagging->getFlaggableId()] ?? NULL;
      if ($entity === NULL || !$this->isPublicForumContent($entity)) {
        continue;
      }
      $chave = $flagging->getFlaggableType() . ':' . $flagging->getFlaggableId();
      $grupos[$chave] ??= [
        'entity_type' => $flagging->getFlaggableType(),
        'entity_id' => (int) $flagging->getFlaggableId(),
        'total' => 0,
        'ultima' => (int) $flagging->getCreatedTime(),
      ];
      $grupos[$chave]['total']++;
    }
    return ['items' => array_values($grupos), 'truncado' => $truncado];
  }

  /**
   * Keeps the queue limited to current public Forum content.
   */
  private function isPublicForumContent(EntityInterface $entity): bool {
    if ($entity instanceof NodeInterface) {
      return $entity->bundle() === 'aculta_forum_topic'
        && !$this->hasHistoricalAuthor($entity);
    }
    if (!$entity instanceof CommentInterface || $entity->bundle() !== 'aculta_discussion_post') {
      return FALSE;
    }
    if ($this->hasHistoricalAuthor($entity)) {
      return FALSE;
    }

    $host = $entity->getCommentedEntity();
    return $host instanceof NodeInterface
      && $host->bundle() === 'aculta_forum_topic'
      && !$this->hasHistoricalAuthor($host);
  }

  /**
   * Checks the marker shared by imported historical topics and comments.
   */
  private function hasHistoricalAuthor(EntityInterface $entity): bool {
    return $entity instanceof FieldableEntityInterface
      && $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty();
  }

}
