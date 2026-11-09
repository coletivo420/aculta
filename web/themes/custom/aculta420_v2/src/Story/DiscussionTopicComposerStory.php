<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Story executável para o Compositor de Criação de Tópico.
 *
 * Monta o formulário real do nó aculta_forum_topic (Assunto antes do Comentário).
 */
final class DiscussionTopicComposerStory extends StoryDefault implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

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
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function props(): array {
    return [
      'target_id' => 'discussion-topic-composer-demo-1',
      'status' => 'editando',
      'host_title' => 'Growroom420',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'aculta_forum_topic',
    ]);

    $form = $this->entityFormBuilder->getForm($node);

    $renderedHtml = (string) $this->renderer->renderPlain($form);

    return [
      'messages' => [],
      'form' => [
        '#markup' => Markup::create($renderedHtml),
        '#attached' => $form['#attached'] ?? [],
      ],
    ];
  }

}
