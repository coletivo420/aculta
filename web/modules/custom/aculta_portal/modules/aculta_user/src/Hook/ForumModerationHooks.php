<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\comment\CommentingStatus;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Acesso do Fórum e isolamento administrativo das discussões contextuais.
 *
 * O moderador pode ocultar, despublicar ou excluir tópicos e comentários.
 * Conteúdo Growroom com autoria histórica fica congelado, mesmo para
 * administradores; conteúdo ativo novo exige e-mail confirmado para moderação.
 *
 * No bundle novo, o editor confirmado também recebe acesso contextual sem
 * permissões genéricas de edição/exclusão. A própria autoria segue as
 * permissões nativas do Node. A fila genérica Core não mistura Comments
 * vinculados ao campo compartilhado, cuja moderação pertence ao contexto.
 */
final class ForumModerationHooks {

  private const MODERATION_OPS = ['update', 'delete'];

  public function __construct(
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly EmailConfirmationPolicy $emailConfirmationPolicy,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if (in_array($operation, ['update', 'delete', 'approve'], TRUE) && $this->isGrowroomHistoric($entity)) {
      return AccessResult::forbidden('O acervo histórico do Growroom é somente leitura.')
        ->addCacheableDependency($entity);
    }
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

  /**
   * Closes replies on historic Growroom topics, including on import.
   */
  #[Hook('entity_presave')]
  public function closeGrowroomHistoricDiscussion(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface
      && $entity->bundle() === 'aculta_forum_topic'
      && $this->hasHistoricAuthor($entity)
      && $entity->hasField('field_aculta_discussion')) {
      $field = $entity->get('field_aculta_discussion');
      $values = $field->getValue() ?: [[]];
      foreach ($values as &$value) {
        $value['status'] = CommentingStatus::Closed->value;
      }
      unset($value);
      $field->setValue($values);
    }
  }

  /**
   * Keeps contextual discussion comments out of Core's general queue.
   */
  #[Hook('form_comment_admin_overview_alter')]
  public function commentAdminOverviewAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!isset($form['comments']['#options']) || !is_array($form['comments']['#options'])) {
      return;
    }
    foreach (array_keys($form['comments']['#options']) as $cid) {
      $comment = $this->entityTypeManager->getStorage('comment')->load($cid);
      if ($comment instanceof CommentInterface
        && ($this->isContextManagedComment($comment) || $this->isGrowroomHistoric($comment))) {
        unset($form['comments']['#options'][$cid]);
      }
    }
    // Core bulk moderation saves directly instead of checking entity access.
    // Validate submitted IDs even when a caller forges a hidden row.
    $form['#validate'][] = [$this, 'validateCommentAdminOverviewSelection'];
  }

  /**
   * Rejects archive comments submitted through the core bulk moderation form.
   */
  public function validateCommentAdminOverviewSelection(array &$form, FormStateInterface $form_state): void {
    $ids = array_filter((array) $form_state->getValue('comments', []));
    if ($ids === []) {
      return;
    }
    $comments = $this->entityTypeManager->getStorage('comment')->loadMultiple($ids);
    foreach ($comments as $comment) {
      if ($comment instanceof CommentInterface && $this->isGrowroomHistoric($comment)) {
        $form_state->setErrorByName('comments', 'O acervo histórico do Growroom não pode ser moderado.');
        return;
      }
      if ($comment instanceof CommentInterface && $this->isContextManagedComment($comment)) {
        $form_state->setErrorByName('comments', 'Comentários de discussões devem ser gerenciados na área própria do contexto.');
        return;
      }
    }
  }

  /**
   * Determines whether an entity is part of the read-only Growroom archive.
   */
  private function isGrowroomHistoric(EntityInterface $entity): bool {
    if ($entity instanceof NodeInterface) {
      return $entity->bundle() === 'aculta_forum_topic'
        && $this->hasHistoricAuthor($entity);
    }
    if (!$entity instanceof CommentInterface || $entity->bundle() !== 'aculta_discussion_post') {
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
   * Checks whether a Comment belongs to a host with contextual discussions.
   */
  private function isContextManagedComment(CommentInterface $comment): bool {
    if ($comment->bundle() !== 'aculta_discussion_post') {
      return FALSE;
    }
    $host = $comment->getCommentedEntity();
    return $host instanceof FieldableEntityInterface
      && $host->hasField('field_aculta_discussion');
  }

  /**
   * Checks the existing field that identifies non-account historic authors.
   */
  private function hasHistoricAuthor(EntityInterface $entity): bool {
    return $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty();
  }

}
