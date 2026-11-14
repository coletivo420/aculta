<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewsSourceBase;
use Drupal\views\ViewExecutable;

/**
 * Exposes a Views title as safe renderable slot content.
 */
#[Source(
  id: 'view_title_markup',
  label: new TranslatableMarkup('[View] Title markup'),
  description: new TranslatableMarkup('The escaped display title as slot content.'),
  prop_types: ['slot'],
  context_requirements: ['views:display'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view', label: new TranslatableMarkup('View')),
  ],
)]
final class ViewTitleMarkupSource extends ViewsSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $view = $this->getView();
    if (!$view instanceof ViewExecutable) {
      return [];
    }
    return ['#plain_text' => $view->getTitle()];
  }

}
