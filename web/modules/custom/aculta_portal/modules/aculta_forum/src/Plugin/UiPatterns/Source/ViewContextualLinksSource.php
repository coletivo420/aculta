<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\contextual\ContextualLinksSerializer;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewsSourceBase;
use Drupal\views\ContextualLinksHelper;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps the core Views contextual-link placeholder in a component slot.
 */
#[Source(
  id: 'view_contextual_links',
  label: new TranslatableMarkup('[View] Contextual links'),
  description: new TranslatableMarkup('The administrative contextual links for this display.'),
  prop_types: ['slot'],
  context_requirements: ['views:display'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view', label: new TranslatableMarkup('View')),
  ],
)]
final class ViewContextualLinksSource extends ViewsSourceBase {

  protected ContextualLinksHelper $contextualLinks;

  protected ContextualLinksSerializer $serializer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $base = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $base->contextualLinks = $container->get(ContextualLinksHelper::class);
    $base->serializer = $container->get(ContextualLinksSerializer::class);
    return $base;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $view = $this->getView();
    if (!$view instanceof ViewExecutable || !$this->moduleHandler->moduleExists('contextual')) {
      return [];
    }
    $this->addCacheableDependency($view->storage);
    $this->addCacheContexts(['user.permissions']);
    if (!$this->currentUser->hasPermission('access contextual links')) {
      return [];
    }

    $element = [
      '#view_id' => $view->storage->id(),
      '#view_display_show_admin_links' => $view->getShowAdminLinks(),
      '#view_display_plugin_id' => $view->display_handler->getPluginId(),
    ];
    $this->contextualLinks->addLinks($element, 'view', $view->current_display);
    $links = $element['#contextual_links'] ?? [];
    if ($links === []) {
      return [];
    }

    return [
      '#type' => 'contextual_links_placeholder',
      '#id' => $this->serializer->linksToId($links),
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

}
