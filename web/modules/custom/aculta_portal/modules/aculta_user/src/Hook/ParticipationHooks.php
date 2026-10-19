<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Access\AccessResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\ParticipationRules;
use Drupal\comment\CommentInterface;

/**
 * Moderação do primeiro comentário e limite para criar tópico (seções E6 e E7).
 *
 * Regra derivada de contagens: até haver comentário publicado, os comentários
 * novos vão para a fila. Após a primeira aprovação, os seguintes são
 * publicados.
 */
final class ParticipationHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.participation_rules')]
    private readonly ParticipationRules $rules,
  ) {}

  /**
   * Implements hook_comment_presave().
   */
  #[Hook('comment_presave')]
  public function commentPresave(CommentInterface $comment): void {
    if (!$comment->isNew()) {
      return;
    }
    $settings = $this->rules->settings();

    // Keep the contributed Forum behavior intact until its removal. The new
    // shared Comment bundle applies to every discussion context except LMS;
    // LMS discussion is deliberately governed by the LMS adapter.
    $isLegacyForumComment = $comment->bundle() === ParticipationRules::COMMENT_TYPE;
    $isDiscussionComment = $this->rules->isNonLmsDiscussionComment($comment);
    if (!$isLegacyForumComment && !$isDiscussionComment) {
      return;
    }
    if (($isLegacyForumComment && !$settings['primeiro_comentario_moderado']) ||
      ($isDiscussionComment && !$settings['primeiro_comentario_discussao_moderado']) ||
      $comment->getOwner() === NULL) {
      return;
    }
    $owner = $comment->getOwner();
    $hasPublishedComment = $isLegacyForumComment
      ? $this->rules->publishedCount((int) $owner->id()) > 0
      : $this->rules->hasPublishedDiscussionComment((int) $owner->id());
    if ($this->rules->isExempt($owner) || $hasPublishedComment) {
      return;
    }
    $comment->setUnpublished();
  }

  /**
   * Implements hook_entity_create_access().
   *
   * Tópico de Fórum exige comentários publicados (limite configurável).
   * Também bloqueia a criação enquanto há comentário não LMS pendente, se
   * essa regra estiver ligada.
   */
  #[Hook('entity_create_access')]
  public function entityCreateAccess(AccountInterface $account, array $context, ?string $entity_bundle): ?AccessResult {
    $isLegacyForumTopic = $entity_bundle === ParticipationRules::TOPIC_TYPE;
    $isPublicDiscussionTopic = $entity_bundle === 'aculta_forum_topic';
    if (($context['entity_type_id'] ?? NULL) !== 'node' || (!$isLegacyForumTopic && !$isPublicDiscussionTopic)) {
      return NULL;
    }
    if ($this->rules->isExempt($account)) {
      return NULL;
    }
    $settings = $this->rules->settings();
    $uid = (int) $account->id();
    $result = AccessResult::neutral()
      ->addCacheContexts(['user', 'user.roles'])
      ->addCacheTags(['comment_list', ...$this->rules->settingsCacheTags()]);

    $firstCommentPending = $isLegacyForumTopic
      ? $this->rules->firstCommentPending($uid)
      : $this->rules->firstDiscussionCommentPending($uid);
    if ($settings['bloqueio_ate_aprovacao'] && $firstCommentPending) {
      return $this->forbidden(
        'Seu primeiro comentário aguarda moderação.',
      );
    }
    if ($settings['topico_limite_ativo']) {
      $published = $isLegacyForumTopic
        ? $this->rules->publishedCount($uid)
        : $this->rules->publishedPublicForumCommentCount($uid);
      $minimum = (int) $settings['topico_comentarios_minimos'];
      if ($published < $minimum) {
        return $this->forbidden(
          'Você pode criar tópicos a partir de ' . $minimum . ' comentários publicados. Você tem ' . $published . '.',
        );
      }
    }
    return $result;
  }

  /**
   * Creates a user-specific denial invalidated by comment and settings changes.
   */
  private function forbidden(string $reason): AccessResult {
    return AccessResult::forbidden($reason)
      ->addCacheContexts(['user', 'user.roles'])
      ->addCacheTags(['comment_list', ...$this->rules->settingsCacheTags()]);
  }

}
