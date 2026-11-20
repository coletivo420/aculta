<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moderation pin/unpin of a topic (Discourse parity: fixar). Uses the node sticky flag.
 */
final class TopicModerationPinForm extends ConfirmFormBase {

  /**
   * Creates the form with the current route.
   */
  public function __construct(protected readonly RouteMatchInterface $topicRoute) {}

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
    return 'aculta_forum_topic_moderation_pin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->isPinned() ? $this->t('Desafixar este tópico?') : $this->t('Fixar este tópico?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Um tópico fixado exibe o selo "Fixado" e pode ser destacado nas listagens.');
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
      $pinned = !$this->isPinned();
      $topic->setSticky($pinned);
      $topic->save();
      $this->messenger()->addStatus($pinned ? $this->t('Tópico fixado.') : $this->t('Tópico desafixado.'));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Whether the topic is pinned.
   */
  private function isPinned(): bool {
    return (bool) $this->topic()?->isSticky();
  }

  /**
   * The topic from the route.
   */
  private function topic(): ?NodeInterface {
    $node = $this->topicRoute->getParameter('node');
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
