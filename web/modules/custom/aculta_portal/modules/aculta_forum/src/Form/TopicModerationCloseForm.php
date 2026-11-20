<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Service\TopicAuthorDeletion;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moderation close/reopen of a topic: a closed topic stays open to read and shows a closed message.
 */
final class TopicModerationCloseForm extends ConfirmFormBase {

  /**
   * Creates the form with the topic state service and the current route.
   */
  public function __construct(
    protected readonly TopicAuthorDeletion $state,
    protected readonly RouteMatchInterface $topicRoute,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.topic_author_deletion'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_topic_moderation_close_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $topic = $this->topic();
    $closed = $topic instanceof NodeInterface && $this->state->isClosed((int) $topic->id());
    return $closed
      ? $this->t('Reabrir este tópico?')
      : $this->t('Fechar este tópico?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Um tópico fechado permanece visível para leitura e exibe a mensagem de tópico fechado.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.node.canonical', ['node' => $this->topic()?->id()]);
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
    $topic = $this->topic();
    if ($topic instanceof NodeInterface) {
      $closed = $this->state->isClosed((int) $topic->id());
      $this->state->setClosed($topic, !$closed);
      $this->messenger()->addStatus($closed ? $this->t('Tópico reaberto.') : $this->t('Tópico fechado pela moderação.'));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * The topic from the route (the {node} parameter).
   */
  private function topic(): ?NodeInterface {
    $node = $this->topicRoute->getParameter('node');
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
