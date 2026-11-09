<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\aculta_magazine\Form\StudioContentPopupLayout;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Story do Compositor de Criação de Conteúdo: formulário real de revista no layout do popup.
 */
final class DiscussionContentComposerStory extends StoryDefault implements ContainerFactoryPluginInterface {

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
      'target_id' => 'discussion-content-composer-demo-1',
      'status' => 'editando',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    $node = $this->entityTypeManager->getStorage('node')->create(['type' => 'revista']);
    $form = $this->entityFormBuilder->getForm($node, 'default');
    // Mesmo layout do popup "Nova publicação" do Marley.
    StudioContentPopupLayout::apply($form);
    $html = (string) $this->renderer->renderPlain($form);

    return [
      'form' => [
        '#markup' => Markup::create($html),
        '#attached' => $form['#attached'] ?? [],
      ],
    ];
  }

}
