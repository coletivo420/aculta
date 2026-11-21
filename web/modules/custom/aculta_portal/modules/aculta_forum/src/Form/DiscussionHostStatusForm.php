<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\comment\CommentingStatus;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moderation open/close of the discussion of a consumer host (Wiki, Revista, LMS, comments).
 */
final class DiscussionHostStatusForm extends ConfirmFormBase {

  /**
   * Creates the form with the current route.
   */
  public function __construct(protected readonly RouteMatchInterface $hostRoute) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('current_route_match'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_discussion_host_status_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->isOpen() ? $this->t('Fechar a discussão deste conteúdo?') : $this->t('Reabrir a discussão deste conteúdo?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Com a discussão fechada, o conteúdo continua visível, mas não recebe novas respostas.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.node.canonical', ['node' => $this->host()?->id()]);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $host = $this->host();
    if ($host instanceof NodeInterface && $host->hasField('field_aculta_discussion')) {
      $status = $this->isOpen() ? CommentingStatus::Closed->value : CommentingStatus::Open->value;
      $host->get('field_aculta_discussion')->status = $status;
      $host->save();
      $this->messenger()->addStatus($status === CommentingStatus::Closed->value ? $this->t('Discussão fechada pela moderação.') : $this->t('Discussão reaberta.'));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Whether the host discussion is currently open.
   */
  private function isOpen(): bool {
    $host = $this->host();
    if (!$host instanceof NodeInterface || !$host->hasField('field_aculta_discussion')) {
      return FALSE;
    }
    $value = $host->get('field_aculta_discussion')->status;
    return (int) (is_scalar($value) ? $value : 0) === CommentingStatus::Open->value;
  }

  /**
   * The host node from the route.
   */
  private function host(): ?NodeInterface {
    $node = $this->hostRoute->getParameter('node');
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
