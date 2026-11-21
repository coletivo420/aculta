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
 * Moderation unpublish/republish of a topic (Discourse parity: despublicar). Unpublished topics are hidden from the public.
 */
final class TopicModerationPublishForm extends ConfirmFormBase {

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
    return 'aculta_forum_topic_moderation_publish_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->topic()?->isPublished() ? $this->t('Despublicar este tópico?') : $this->t('Republicar este tópico?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Um tópico despublicado sai das listagens públicas; a moderação pode republicá-lo.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    // A frontend command returns to Marley, never to a Drupal admin page.
    $routeName = $this->topicRoute->getRouteName();
    if (is_string($routeName) && str_starts_with($routeName, 'aculta_forum.marley_topic_')) {
      return Url::fromRoute('aculta_cgu.forum_marley_queue');
    }
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
      $topic->isPublished() ? $topic->setUnpublished() : $topic->setPublished();
      $topic->save();
      $this->messenger()->addStatus($topic->isPublished() ? $this->t('Tópico republicado.') : $this->t('Tópico despublicado pela moderação.'));
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
