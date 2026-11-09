<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Story do popup Criar tópico: formulário real do nó aculta_forum_topic.
 */
final class DiscussionTopicCreateDialogStory extends StoryDefault implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFormBuilderInterface $entityFormBuilder,
    private readonly RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function props(): array {
    return [
      'id' => 'aculta-topic-create-dialog',
      'trigger_label' => 'Criar tópico',
      'title' => 'Criar tópico',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    $node = $this->entityTypeManager->getStorage('node')->create(['type' => 'aculta_forum_topic']);
    $form = $this->entityFormBuilder->getForm($node);
    $html = (string) $this->renderer->renderPlain($form);

    return [
      'form' => [
        '#markup' => Markup::create($html),
        '#attached' => $form['#attached'] ?? [],
      ],
    ];
  }

}
