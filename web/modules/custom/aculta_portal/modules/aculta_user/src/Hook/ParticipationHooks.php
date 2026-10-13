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
 * Regra única, derivada de contagens: enquanto a conta não tem comentário publicado no Fórum, cada comentário novo
 * vai para a fila. Quando o primeiro é aprovado, os seguintes são publicados na hora.
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
    if ($comment->bundle() !== ParticipationRules::COMMENT_TYPE || !$comment->isNew()) {
      return;
    }
    $settings = $this->rules->settings();
    if (!$settings['primeiro_comentario_moderado'] || $comment->getOwner() === NULL) {
      return;
    }
    $owner = $comment->getOwner();
    if ($this->rules->isExempt($owner) || $this->rules->publishedCount((int) $owner->id()) > 0) {
      return;
    }
    $comment->setUnpublished();
  }

  /**
   * Implements hook_entity_create_access().
   *
   * Tópico de Fórum exige comentários publicados (limite configurável). Bloqueia a criação enquanto o primeiro
   * comentário aguarda aprovação, se a regra de bloqueio estiver ligada.
   */
  #[Hook('entity_create_access')]
  public function entityCreateAccess(AccountInterface $account, array $context, ?string $entity_bundle): ?AccessResult {
    if (($context['entity_type_id'] ?? NULL) !== 'node' || $entity_bundle !== ParticipationRules::TOPIC_TYPE) {
      return NULL;
    }
    if ($this->rules->isExempt($account)) {
      return NULL;
    }
    $settings = $this->rules->settings();
    $uid = (int) $account->id();
    $result = AccessResult::neutral()->addCacheContexts(['user'])->addCacheTags(['comment_list']);

    if ($settings['bloqueio_ate_aprovacao'] && $this->rules->firstCommentPending($uid)) {
      return AccessResult::forbidden('Seu primeiro comentário aguarda moderação.')->addCacheContexts(['user'])->addCacheTags(['comment_list']);
    }
    if ($settings['topico_limite_ativo']) {
      $published = $this->rules->publishedCount($uid);
      $minimum = (int) $settings['topico_comentarios_minimos'];
      if ($published < $minimum) {
        return AccessResult::forbidden('Você pode criar tópicos a partir de ' . $minimum . ' comentários publicados. Você tem ' . $published . '.')->addCacheContexts(['user'])->addCacheTags(['comment_list']);
      }
    }
    return $result;
  }

}
