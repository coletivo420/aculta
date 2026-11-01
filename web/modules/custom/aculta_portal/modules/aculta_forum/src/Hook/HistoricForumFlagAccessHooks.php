<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\comment\CommentInterface;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Migration\ForumFlaggingMigrationContext;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
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

  private const POST_FLAG = 'sinalizar_post_forum';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly ?DomainPurposeManager $domainPurposeManager = NULL,
    private readonly ?ForumFlaggingMigrationContext $flaggingMigrationContext = NULL,
  ) {}

  /**
   * Implements hook_flag_action_access().
   */
  #[Hook('flag_action_access')]
  public function flagActionAccess(string $action, FlagInterface $flag, AccountInterface $account, ?EntityInterface $flaggable = NULL): AccessResultInterface {
    if (!in_array($action, ['flag', 'unflag'], TRUE)
      || (!in_array($flag->id(), self::FLAGS, TRUE) && $flag->id() !== self::POST_FLAG)) {
      return AccessResult::neutral();
    }
    if ($flaggable === NULL) {
      return $flag->id() === self::POST_FLAG
        ? AccessResult::forbidden('A denúncia exige uma publicação do Fórum válida.')
          ->addCacheContexts(['domain'])
        : AccessResult::neutral();
    }

    // Unrelated content stays with its own policy; once the target belongs to
    // the Forum, an unpublished or historical state vetoes the action instead
    // of delegating to a broad flag permission.
    $isForumKind = match ($flag->id()) {
      'sinalizar_topico' => $flaggable instanceof NodeInterface && $flaggable->bundle() === 'aculta_forum_topic',
      'sinalizar_comentario' => $this->isForumComment($flaggable),
      default => TRUE,
    };
    if (!$isForumKind) {
      return AccessResult::neutral();
    }

    $postAccess = NULL;
    $public = match ($flag->id()) {
      'sinalizar_topico' => $flaggable instanceof NodeInterface && $flaggable->isPublished(),
      'sinalizar_comentario' => $this->isPublicForumComment($flaggable),
      default => ($postAccess = $this->forumPostAccess($flaggable, $account))->isAllowed(),
    };

    if (!$public) {
      $result = AccessResult::forbidden('Denúncia disponível somente para publicação pública deste Fórum.');
    }
    elseif ($this->isHistoricalTarget($flaggable, $flag->id())) {
      $result = AccessResult::forbidden('Conteúdo histórico do Growroom não entra no fluxo de denúncias.');
    }
    else {
      // The permission alone must not expose unpublished or out-of-purpose
      // content through a direct Flag action.
      $viewAccess = $flaggable->access('view', $account, TRUE);
      $result = AccessResult::allowedIf($account->hasPermission('report aculta forum content'))
        ->andIf($viewAccess);
      $result->addCacheableDependency($viewAccess);
    }

    $result->addCacheableDependency($flaggable)
      ->addCacheContexts(['user.permissions']);
    if ($flaggable instanceof ForumPost) {
      $topic = $flaggable->get('topic')->entity;
      if ($topic instanceof NodeInterface) {
        $result->addCacheableDependency($topic);
      }
      $result->addCacheContexts(['domain']);
    }
    if ($postAccess instanceof AccessResultInterface) {
      $result->addCacheableDependency($postAccess);
    }
    return $result;
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
    if (!in_array($flagging->getFlagId(), self::FLAGS, TRUE) && $flagging->getFlagId() !== self::POST_FLAG) {
      return;
    }
    if ($this->flaggingMigrationContext?->authorizes($flagging)) {
      return;
    }

    if (!$this->currentUser->hasPermission('report aculta forum content')) {
      throw new AccessDeniedHttpException('A conta não pode denunciar conteúdo do Fórum público.');
    }
    if ($flagging->getFlagId() === self::POST_FLAG
      && ($this->domainPurposeManager === NULL || $this->domainPurposeManager->getCurrentPurpose() !== 'forum')) {
      throw new AccessDeniedHttpException('Denúncias de publicações só são aceitas no purpose Fórum.');
    }

    $expectedType = match ($flagging->getFlagId()) {
      'sinalizar_topico' => 'node',
      'sinalizar_comentario' => 'comment',
      self::POST_FLAG => 'aculta_forum_post',
    };
    if ($flagging->getFlaggableType() !== $expectedType) {
      throw new AccessDeniedHttpException('Este tipo de entidade não aceita denúncia pela fila do Fórum público.');
    }

    $entity = $this->entityTypeManager->getStorage($expectedType)
      ->load($flagging->getFlaggableId());
    $validTarget = $entity !== NULL && $this->isPublicForumTarget($entity, $flagging->getFlagId());
    if ($flagging->getFlagId() === self::POST_FLAG && $entity !== NULL) {
      $validTarget = $this->forumPostAccess($entity, $this->currentUser)->isAllowed();
    }
    if ($entity === NULL || !$validTarget
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
    if ($flagId === self::POST_FLAG) {
      if (!$this->isPublicForumPost($entity)) {
        return FALSE;
      }
      if ($this->hasHistoricAuthor($entity)) {
        return TRUE;
      }
      $topic = $entity instanceof ForumPost ? $entity->get('topic')->entity : NULL;
      return $topic instanceof NodeInterface && $this->hasHistoricAuthor($topic);
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
      'sinalizar_topico' => $entity instanceof NodeInterface
        && $entity->bundle() === 'aculta_forum_topic'
        && $entity->isPublished(),
      'sinalizar_comentario' => $this->isPublicForumComment($entity),
      self::POST_FLAG => $this->isPublicForumPost($entity),
      default => FALSE,
    };
  }

  /**
   * Checks that a Comment belongs to the public Forum discussion bundle.
   */
  private function isForumComment(EntityInterface $entity): bool {
    if (!$entity instanceof CommentInterface || $entity->bundle() !== 'aculta_discussion_post') {
      return FALSE;
    }
    $host = $entity->getCommentedEntity();
    return $host instanceof NodeInterface && $host->bundle() === 'aculta_forum_topic';
  }

  /**
   * Checks that a shared Comment is hosted by a published Forum topic.
   */
  private function isPublicForumComment(EntityInterface $entity): bool {
    if (!$this->isForumComment($entity)) {
      return FALSE;
    }
    $host = $entity->getCommentedEntity();
    return $host instanceof NodeInterface && $host->isPublished();
  }

  /**
   * Checks that a revisionable post belongs to a published Forum topic.
   */
  private function isPublicForumPost(EntityInterface $entity): bool {
    if (!$entity instanceof ForumPost || !$entity->isPublished() || !$entity->isDefaultRevision()
      || !$entity->hasField('topic') || $entity->get('topic')->isEmpty()) {
      return FALSE;
    }
    $topic = $entity->get('topic')->entity;
    return $topic instanceof NodeInterface
      && $topic->bundle() === 'aculta_forum_topic'
      && $topic->isPublished()
      && $topic->hasField('field_domain_source')
      && !$topic->get('field_domain_source')->isEmpty()
      && $this->domainPurposeManager?->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) === 'forum';
  }

  /**
   * Returns cacheable access for viewing and flagging one Forum post.
   */
  private function forumPostAccess(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    if (!$this->isPublicForumPost($entity) || $this->domainPurposeManager?->getCurrentPurpose() !== 'forum') {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->addCacheContexts(['domain']);
    }
    assert($entity instanceof ForumPost);
    $topic = $entity->get('topic')->entity;
    assert($topic instanceof NodeInterface);
    return $entity->access('view', $account, TRUE)
      ->andIf($topic->access('view', $account, TRUE))
      ->addCacheableDependency($entity)
      ->addCacheableDependency($topic)
      ->addCacheContexts(['domain']);
  }

  /**
   * Checks whether an entity carries the immutable historical author marker.
   */
  private function hasHistoricAuthor(EntityInterface $entity): bool {
    if ($entity instanceof ForumPost && $entity->hasField('legacy_author')) {
      return !$entity->get('legacy_author')->isEmpty();
    }
    return $entity instanceof FieldableEntityInterface
      && $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty();
  }

}
