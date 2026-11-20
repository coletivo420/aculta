<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\comment\CommentInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_forum\Hook\DiscussionModerationEditHooks;
use Drupal\Core\Url;
use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ações de moderação de um comentário, carregadas no modal de controles por HTMX.
 */
final class DiscussionCommentModerationForm extends FormBase {

  public function __construct(
    protected readonly RouteMatchInterface $modRoute,
    protected readonly KeyValueFactoryInterface $modKeyValue,
    protected readonly LoggerChannelFactoryInterface $modLogger,
    protected readonly AccountProxyInterface $modModerator,
    protected readonly DateFormatterInterface $modDates,
    protected readonly EntityTypeManagerInterface $modEntities,
    protected readonly DiscussionReactionManager $modReactions,
    protected readonly DiscussionModerationEditHooks $modEdits,
  ) {}

  /**
   * Creates an instance from the service container.
   *
   * @param ContainerInterface $container
   *   Dependency injection container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('keyvalue'),
      $container->get('logger.factory'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('aculta_forum.discussion_reaction_manager'),
      $container->get(DiscussionModerationEditHooks::class),
    );
  }

  /**
   * Provides the getFormId operation.
   */
  public function getFormId(): string {
    return 'aculta_forum_comment_moderation_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $comment = $this->modRoute->getParameter('comment');
    if (!$comment instanceof CommentInterface) {
      return $form;
    }
    $cid = (int) $comment->id();

    // Ação concluída: o modal só confirma e a página recarrega para mostrar o novo estado do comentário.
    if ($form_state->get('acao_feita')) {
      $raw_cid = $form_state->get('acao_cid');
      $feitoCid = is_numeric($raw_cid) ? (int) $raw_cid : 0;
      $raw_acao = $form_state->get('acao');
      $feitoAcao = is_scalar($raw_acao) ? (string) $raw_acao : '';
      $form['feito'] = ['#markup' => '<p class="comment-admin-controls__done" data-moderation-done data-cid="' . $feitoCid . '" data-acao="' . htmlspecialchars($feitoAcao, ENT_QUOTES, 'UTF-8') . '">' . $this->t('Ação concluída.') . '</p>'];
      return $form;
    }

    $tombstone = $this->modKeyValue->get('aculta_forum_comment_tombstones')->get((string) $cid);
    $state = match (TRUE) {
      $tombstone === 'moderacao' => (string) $this->t('Removido pela moderação'),
      (bool) $tombstone => (string) $this->t('Excluído pelo autor'),
      $comment->isPublished() => (string) $this->t('Publicado'),
      default => (string) $this->t('Despublicado'),
    };

    $form['info'] = [
      '#theme' => 'portal_component',
      '#name' => 'comment-admin-controls',
      '#slots' => [
        'log' => $this->moderationEditLog($cid),
      ],
      '#props' => [
        'author_name' => (string) $comment->getAuthorName(),
        'state_label' => $state,
        'created_label' => $this->modDates->format((int) $comment->getCreatedTime(), 'short'),
        'reactions_total' => $this->reactionTotal($cid),
        'reply_count' => $this->countReplies($comment),
      ],
    ];

    $form['acoes'] = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'comment-admin-controls-buttons']];
    $form['acoes']['despublicar_ou_republicar'] = [
      '#type' => 'submit',
      '#value' => $comment->isPublished() ? $this->t('Despublicar') : $this->t('Republicar'),
      '#acao' => $comment->isPublished() ? 'despublicar' : 'republicar',
    ];
    // Edição administrativa: a moderação edita o comentário pelo formulário padrão; a marca é gravada ao salvar.
    $form['acoes']['editar_moderacao'] = [
      '#type' => 'link',
      '#title' => $this->t('Editar pela moderação'),
      '#url' => Url::fromRoute('entity.comment.edit_form', ['comment' => $cid]),
    ];
    if ($tombstone === 'moderacao') {
      $form['acoes']['restaurar'] = [
        '#type' => 'submit',
        '#value' => $this->t('Restaurar comentário'),
        '#acao' => 'restaurar',
      ];
    }
    elseif (!$tombstone) {
      $form['acoes']['remover'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remover pela moderação'),
        '#acao' => 'remover',
      ];
    }
    // Exclusão física só depois de marcar que é definitiva.
    $form['acoes']['confirmar_exclusao'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Entendo que a exclusão é definitiva.'),
    ];
    $form['acoes']['excluir'] = ['#type' => 'submit', '#value' => $this->t('Excluir de vez'), '#acao' => 'excluir'];

    // Envio por HTMX para o mesmo endereço: o corpo do popup é trocado pelo resultado.
    if (!isset($form['#attributes']) || !is_array($form['#attributes'])) {
      $form['#attributes'] = [];
    }
    $form['#attributes']['data-hx-post'] = Url::fromRoute('aculta_forum.comment_moderation', ['comment' => $cid])->toString();
    $form['#attributes']['data-hx-target'] = '#aculta-admin-dialog-body';
    $form['#attributes']['data-hx-swap'] = 'innerHTML';
    $form['#attributes']['class'] = array_merge((array) ($form['#attributes']['class'] ?? []), ['comment-admin-controls__form']);
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($this->acaoDoBotao($form_state) === 'excluir' && !$form_state->getValue('confirmar_exclusao')) {
      $form_state->setErrorByName('confirmar_exclusao', $this->t('Confirme que a exclusão é definitiva.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $comment = $this->modRoute->getParameter('comment');
    if (!$comment instanceof CommentInterface) {
      return;
    }
    $cid = (int) $comment->id();
    $acao = $this->acaoDoBotao($form_state);
    $tombstones = $this->modKeyValue->get('aculta_forum_comment_tombstones');

    switch ($acao) {
      case 'despublicar':
        $comment->setUnpublished()->save();
        break;

      case 'republicar':
        $comment->setPublished()->save();
        break;

      case 'remover':
        $tombstones->set((string) $cid, 'moderacao');
        break;

      case 'restaurar':
        $tombstones->delete((string) $cid);
        break;

      case 'excluir':
        $tombstones->delete((string) $cid);
        $comment->delete();
        $form_state->set('acao_excluir', TRUE);
        break;
    }

    $this->modLogger->get('aculta_forum.moderation')->notice('Ação @acao no comentário @cid pelo usuário @uid.', [
      '@acao' => $acao,
      '@cid' => $cid,
      '@uid' => $this->modModerator->id(),
    ]);
    $form_state->set('acao_feita', TRUE);
    $form_state->set('acao_cid', $cid);
    $form_state->set('acao', $acao);
    $form_state->setRebuild();
  }

  /**
   * Ação do botão acionado (propriedade #acao de cada submit).
   */
  private function acaoDoBotao(FormStateInterface $form_state): string {
    $trigger = $form_state->getTriggeringElement();
    $acao = is_array($trigger) ? ($trigger['#acao'] ?? '') : '';
    return is_scalar($acao) ? (string) $acao : '';
  }

  /**
   * Número de respostas diretas do comentário (publicadas ou não).
   */
  private function countReplies(CommentInterface $comment): int {
    return (int) $this->modEntities->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('pid', $comment->id())
      ->count()
      ->execute();
  }

  /**
   * Total de reações recebidas pelo comentário.
   */
  private function reactionTotal(int $cid): int {
    $reactions = $this->modReactions->loadReactions('comment', $cid, NULL);
    return (int) $reactions['total_count'];
  }

  /**
   * Administrative log of moderator edits (date and moderator), shown only in the admin controls.
   *
   * @return array<string, mixed>
   *   Render array; empty when nobody moderated the reply.
   */
  private function moderationEditLog(int $cid): array {
    $entries = $this->modEdits->editLog($cid);
    if ($entries === []) {
      return [];
    }
    $items = [];
    foreach ($entries as $entry) {
      $user = $this->modEntities->getStorage('user')->load($entry['uid']);
      $name = $user !== NULL ? (string) $user->getDisplayName() : (string) $this->t('Usuário removido');
      $items[] = (string) $this->t('@name editou em @date', [
        '@name' => $name,
        '@date' => $this->modDates->format($entry['time'], 'short'),
      ]);
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

}
