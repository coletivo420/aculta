<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Applies contextual access to Forum report flags.
 *
 * Flag action access combines hook results with the permission result using
 * OR. The role therefore does not receive broad flag/unflag permissions;
 * this hook grants the action only for current ACULTA discussion content.
 */
final class HistoricForumFlagAccessHooks {

  private const FLAGS = ['sinalizar_topico', 'sinalizar_comentario'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_flag_action_access().
   */
  #[Hook('flag_action_access')]
  public function flagActionAccess(string $action, FlagInterface $flag, AccountInterface $account, ?EntityInterface $flaggable = NULL): AccessResultInterface {
    if (!in_array($action, ['flag', 'unflag'], TRUE)
      || !in_array($flag->id(), self::FLAGS, TRUE)
      || $flaggable === NULL) {
      return AccessResult::neutral();
    }

    $applies = match ($flag->id()) {
      'sinalizar_topico' => $flaggable instanceof NodeInterface && $flaggable->bundle() === 'aculta_forum_topic',
      'sinalizar_comentario' => $this->isPublicForumComment($flaggable),
    };
    if (!$applies) {
      return AccessResult::neutral();
    }

    $result = $this->isHistoricalTarget($flaggable, $flag->id())
      ? AccessResult::forbidden('Conteúdo histórico do Growroom não entra no fluxo de denúncias.')
      : AccessResult::allowedIf($account->hasPermission('report aculta forum content'));

    return $result
      ->addCacheableDependency($flaggable)
      ->addCacheContexts(['user.permissions']);
  }

  /**
   * Refuses direct Flagging saves against the immutable historic archive.
   *
   * Flag's action access combines hook results with granted permissions using
   * OR, so it cannot veto a bypass administrator. The storage lifecycle guard
   * keeps direct API calls and administrator accounts from creating reports
   * for archived material as well.
   */
  #[Hook('flagging_presave')]
  public function preventHistoricalFlagging(FlaggingInterface $flagging): void {
    if (!in_array($flagging->getFlagId(), self::FLAGS, TRUE)) {
      return;
    }

    if (!$this->currentUser->hasPermission('report aculta forum content')) {
      throw new AccessDeniedHttpException('A conta não pode denunciar conteúdo do Fórum público.');
    }

    $expectedType = $flagging->getFlagId() === 'sinalizar_topico' ? 'node' : 'comment';
    if ($flagging->getFlaggableType() !== $expectedType) {
      throw new AccessDeniedHttpException('Este tipo de entidade não aceita denúncia pela fila do Fórum público.');
    }

    $entity = $this->entityTypeManager->getStorage($expectedType)
      ->load($flagging->getFlaggableId());
    if ($entity === NULL || !$this->isPublicForumTarget($entity, $flagging->getFlagId())
      || $this->isHistoricalTarget($entity, $flagging->getFlagId())) {
      throw new AccessDeniedHttpException('Este conteúdo não aceita denúncia pela fila do Fórum público.');
    }
  }

  /**
   * Checks archive status and the entity type expected by the flag.
   */
  private function isHistoricalTarget(EntityInterface $entity, string $flagId): bool {
    if ($flagId === 'sinalizar_topico') {
      return $entity instanceof NodeInterface
        && $entity->bundle() === 'aculta_forum_topic'
        && $this->hasHistoricAuthor($entity);
    }
    if (!$this->isPublicForumComment($entity)) {
      return FALSE;
    }
    if ($this->hasHistoricAuthor($entity)) {
      return TRUE;
    }
    $host = $entity->getCommentedEntity();
    return $host instanceof NodeInterface
      && $host->bundle() === 'aculta_forum_topic'
      && $this->hasHistoricAuthor($host);
  }

  /**
   * Checks that a flag target belongs to the public Forum context.
   */
  private function isPublicForumTarget(EntityInterface $entity, string $flagId): bool {
    return match ($flagId) {
      'sinalizar_topico' => $entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic',
      'sinalizar_comentario' => $this->isPublicForumComment($entity),
      default => FALSE,
    };
  }

  /**
   * Checks that a shared Comment is hosted by a public Forum topic.
   */
  private function isPublicForumComment(EntityInterface $entity): bool {
    if (!$entity instanceof CommentInterface || $entity->bundle() !== 'aculta_discussion_post') {
      return FALSE;
    }
    $host = $entity->getCommentedEntity();
    return $host instanceof NodeInterface && $host->bundle() === 'aculta_forum_topic';
  }

  /**
   * Checks whether an entity carries the immutable historical author marker.
   */
  private function hasHistoricAuthor(EntityInterface $entity): bool {
    return $entity instanceof FieldableEntityInterface
      && $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty();
  }

}
