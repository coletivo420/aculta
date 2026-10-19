<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Moderação do Fórum pelo content_editor.
 *
 * O moderador pode ocultar, despublicar ou excluir tópicos e comentários.
 * Conteúdo histórico não é moderado; o e-mail do moderador deve estar
 * confirmado; administrador mantém bypass total.
 *
 * No bundle novo, o editor confirmado também recebe acesso contextual sem
 * permissões genéricas de edição/exclusão. A própria autoria segue as
 * permissões nativas do Node.
 */
final class ForumModerationHooks {

  private const MODERATION_OPS = ['update', 'delete'];

  public function __construct(
    private readonly EmailConfirmationPolicy $emailConfirmationPolicy,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if (!in_array($operation, self::MODERATION_OPS, TRUE) || $account->hasPermission('bypass node access')) {
      return NULL;
    }
    $topicoLegado = $entity instanceof NodeInterface && $entity->bundle() === 'forum';
    $topicoNovo = $entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic';
    $comentario = $entity instanceof CommentInterface && $entity->bundle() === 'comment_forum';
    if (!$topicoLegado && !$topicoNovo && !$comentario) {
      return NULL;
    }
    $autor = $entity->getOwner();
    if ($entity->hasField('field_autor_legado') && !$entity->get('field_autor_legado')->isEmpty()) {
      return AccessResult::forbidden('Conteúdo histórico não é moderado pela fila atual.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['user.roles']);
    }
    $moderando = $autor === NULL || (int) $autor->id() !== (int) $account->id();
    $editor = in_array('content_editor', $account->getRoles(), TRUE);
    if ($moderando && $editor && !$this->emailConfirmationPolicy->isConfirmed($account)) {
      return AccessResult::forbidden('Moderação exige e-mail confirmado.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['user.roles']);
    }
    // The new Node bundle deliberately does not grant broad "edit any" or
    // "delete any" permissions to content_editor. Grant moderation here only
    // after checking the canonical confirmation marker. Own-topic permissions
    // continue through the normal Node access handler.
    if ($topicoNovo && $moderando && $editor) {
      return AccessResult::allowed('Editor confirmado pode moderar tópicos públicos.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['user.roles']);
    }
    return NULL;
  }

}
