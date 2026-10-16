<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Moderação do Fórum pelo content_editor (ACULTA Fórum, F9, decisões de 2026-10-16).
 *
 * O moderador pode ocultar, despublicar ou excluir tópicos e comentários, com estas exceções:
 * - conteúdo de autoria histórica (campo field_autor_legado preenchido) não é moderado;
 * - o moderador precisa ter e-mail confirmado;
 * - o administrador tem bypass total e não passa por esta regra.
 *
 * Motivo: a permissão de editar e excluir é do papel e não distingue conteúdo legado nem exige e-mail confirmado.
 * Esta regra fecha as duas lacunas no ponto único de acesso.
 */
final class ForumModerationHooks {

  private const MODERATION_OPS = ['update', 'delete'];

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if (!in_array($operation, self::MODERATION_OPS, TRUE) || $account->hasPermission('bypass node access')) {
      return NULL;
    }
    $topico = $entity instanceof NodeInterface && $entity->bundle() === 'forum';
    $comentario = $entity instanceof CommentInterface && $entity->bundle() === 'comment_forum';
    if (!$topico && !$comentario) {
      return NULL;
    }
    $autor = $entity->getOwner();
    if ($entity->hasField('field_autor_legado') && !$entity->get('field_autor_legado')->isEmpty()) {
      return AccessResult::forbidden('Conteúdo histórico não é moderado pela fila atual.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['user.roles']);
    }
    $moderando = $autor === NULL || (int) $autor->id() !== (int) $account->id();
    if ($moderando && in_array('content_editor', $account->getRoles(), TRUE) && !in_array('email_confirmed', $account->getRoles(), TRUE)) {
      return AccessResult::forbidden('Moderação exige e-mail confirmado.')
        ->addCacheContexts(['user.roles']);
    }
    return NULL;
  }

}
