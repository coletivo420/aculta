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
 * Moderation removal of a topic: the topic stays open and shows the automatic moderation message.
 */
final class TopicModerationRemoveForm extends ConfirmFormBase {

  /**
   * Creates the form with the deletion service and the current route.
   */
  public function __construct(
    protected readonly TopicAuthorDeletion $deletion,
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
    return 'aculta_forum_topic_moderation_remove_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Remover o conteúdo deste tópico pela moderação?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('O tópico permanece aberto e exibe a mensagem de remoção pela moderação.');
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
      $this->deletion->markRemovedByModeration($topic);
      $this->messenger()->addStatus($this->t('Conteúdo removido pela moderação.'));
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
