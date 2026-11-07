<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\aculta420_v2\Presentation\WikiBrandMediaProps;
use Drupal\ui_patterns_library\StoryDefault;

/**
 * Story do navigation-bar com a apresentação real do purpose Wiki.
 *
 * As props e os slots vêm do DomainPresentationBuilder e do mesmo código de
 * marca usado no cabeçalho de produção: não há valores copiados.
 */
final class NavigationBarWikiStory extends StoryDefault {

  /**
   * {@inheritdoc}
   */
  public function props(): array {
    $regions = $this->presentation()['regions'] ?? [];
    $brandMedia = is_array($regions['brand_media'] ?? NULL) ? $regions['brand_media'] : [];
    $identity = $this->presentation()['identity'] ?? [];

    $themes = \Drupal::service('extension.list.theme');
    $brand = (new WikiBrandMediaProps($themes))->build($brandMedia, $identity);

    return $brand ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    $regions = $this->presentation()['regions'] ?? [];
    return array_filter([
      'wiki_search' => $regions['wiki_search'] ?? NULL,
    ]);
  }

  /**
   * Apresentação real do purpose Wiki, como array do tema.
   *
   * @return array<string, mixed>
   */
  private function presentation(): array {
    $presentation = \Drupal::service('aculta_portal.presentation.domain')->build('wiki');
    return $presentation?->toThemeArray() ?? [];
  }

}
