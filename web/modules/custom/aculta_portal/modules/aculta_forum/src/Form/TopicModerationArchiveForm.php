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
 * Moderation archive/unarchive of a topic (Discourse parity: arquivar). Archived topics are read-only.
 */
final class TopicModerationArchiveForm extends ConfirmFormBase {

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
    return 'aculta_forum_topic_moderation_archive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->state->isArchived((int) $this->topic()?->id()) ? $this->t('Desarquivar este tópico?') : $this->t('Arquivar este tópico?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Um tópico arquivado fica somente para leitura e não recebe novas respostas.');
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
      $archived = $this->state->isArchived((int) $topic->id());
      $this->state->setArchived($topic, !$archived);
      $this->messenger()->addStatus($archived ? $this->t('Tópico desarquivado.') : $this->t('Tópico arquivado.'));
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
