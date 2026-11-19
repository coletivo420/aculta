<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Presentation;

use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Url;

/**
 * Monta as props da marca de domínio a partir do identificador de marca do Portal.
 *
 * Usado pelo cabeçalho de domínio (PagePresentationHooks) e pelo story do
 * componente navigation-bar, para que ambos usem o mesmo código.
 */
final class WikiBrandMediaProps {

  public function __construct(
    private readonly ExtensionList $themes,
  ) {}

  /**
   * Builds strict SDC props for a supported brand media, or NULL.
   *
   * @param array<string, mixed> $brandMedia
   *   Brand media metadata prepared by ACULTA Portal (#brand_key, #alt).
   * @param array<string, mixed> $identity
   *   Domain identity (home_url, title) from DomainPresentation::toThemeArray().
   *
   * @return array<string, string>|null
   *   Strict SDC props, or NULL for the default branding.
   */
  public function build(array $brandMedia, array $identity): ?array {
    if (($brandMedia['#brand_key'] ?? NULL) !== 'wiki420') {
      return NULL;
    }

    $themePath = $this->themes->getPath('aculta420_v2');
    $assetPath = $themePath . '/assets/logos/wiki420-horizontal-960w.webp';
    if (!is_file(DRUPAL_ROOT . '/' . $assetPath)) {
      return NULL;
    }

    $srcset = [];
    foreach (['480w' => 480, '720w' => 720, '960w' => 960] as $suffix => $width) {
      $srcset[] = Url::fromUri('base:' . $themePath . '/assets/logos/wiki420-horizontal-' . $suffix . '.webp')->toString() . ' ' . $width . 'w';
    }

    return [
      'brand_home_url' => is_string($identity['home_url'] ?? NULL) ? $identity['home_url'] : '/',
      'brand_title' => is_string($identity['title'] ?? NULL) ? $identity['title'] : 'Wiki420',
      'brand_logo_url' => Url::fromUri('base:' . $assetPath)->toString(),
      'brand_logo_srcset' => implode(', ', $srcset),
      'brand_logo_alt' => is_string($brandMedia['#alt'] ?? NULL) ? $brandMedia['#alt'] : '',
    ];
  }

}
