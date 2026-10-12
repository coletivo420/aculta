<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Presentation;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Builds the single neutral presentation contract consumed by ACULTA420.
 */
final class DomainPresentationBuilder {

  /**
   * Presentation labels are centralized here; Domain labels remain functional.
   *
   * @var array<string, array{title: string, short_title: string}>
   */
  private const PURPOSE_IDENTITIES = [
    'main' => [
      'title' => 'ACULTA',
      'short_title' => 'ACULTA',
    ],
    'account' => [
      'title' => 'Minha conta',
      'short_title' => 'Conta',
    ],
    'support' => [
      'title' => 'Apoio',
      'short_title' => 'Apoio',
    ],
    'magazine' => [
      'title' => 'Observatório da Maconha Coletivo 420',
      'short_title' => 'Coletivo 420',
    ],
    'wiki' => [
      'title' => 'Wiki420',
      'short_title' => 'Wiki420',
    ],
    'shop' => [
      'title' => 'Loja',
      'short_title' => 'Loja',
    ],
    'courses' => [
      'title' => 'Cursos',
      'short_title' => 'Cursos',
    ],
    'forum' => [
      'title' => 'Fórum',
      'short_title' => 'Fórum',
    ],
    'links' => [
      'title' => 'Redes sociais da ACULTA',
      'short_title' => 'Redes sociais',
    ],
  ];

  public function __construct(
    private readonly DomainPurposeManager $domainPurpose,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Builds presentation for the active Domain purpose.
   */
  public function buildCurrent(): ?DomainPresentation {
    $purpose = $this->domainPurpose->getCurrentPurpose();
    return $purpose !== NULL ? $this->build($purpose) : NULL;
  }

  /**
   * Builds a presentation contract for one stable purpose.
   */
  public function build(string $purpose): ?DomainPresentation {
    $definition = self::PURPOSE_IDENTITIES[$purpose] ?? NULL;
    if ($definition === NULL) {
      return NULL;
    }

    $domain = $this->domainPurpose->getDomain($purpose);
    $homeUrl = $this->domainPurpose->pathUrl($purpose, '/');
    if ($domain === NULL || $homeUrl === NULL) {
      return NULL;
    }

    $title = (string) $this->translation->translate($definition['title']);
    $shortTitle = (string) $this->translation->translate($definition['short_title']);

    $cacheability = (new CacheableMetadata())
      ->addCacheContexts([
        'domain',
        'languages:language_interface',
        'url.site',
      ])
      ->addCacheableDependency($domain);

    // Purpose-specific asset choice belongs to the Portal; the theme only
    // receives an access-safe render array and does not inspect host/purpose.
    $brandMedia = NULL;
    if ($purpose === 'wiki') {
      $logoDir = 'themes/custom/aculta420/assets/branding/wiki420/web/';
      $variants = ['480w' => 480, '720w' => 720, '960w' => 960];
      if (is_file(DRUPAL_ROOT . '/' . $logoDir . 'wiki420-horizontal-960w.webp')) {
        // The header shows the logo at most 18rem wide; the browser picks the density.
        $srcset = [];
        foreach ($variants as $suffix => $width) {
          $srcset[] = Url::fromUri('base:' . $logoDir . 'wiki420-horizontal-' . $suffix . '.webp')->toString() . ' ' . $width . 'w';
        }
        $brandMedia = [
          '#theme' => 'image',
          '#uri' => Url::fromUri('base:' . $logoDir . 'wiki420-horizontal-960w.webp')->toString(),
          '#alt' => $title,
          '#width' => 960,
          '#height' => 307,
          '#attributes' => [
            'data-portal-ui' => 'domain-brand-image',
            'srcset' => implode(', ', $srcset),
            'sizes' => '18rem',
            // The logo is above the fold and is the header's main visual: no lazy loading.
            'loading' => 'eager',
            'fetchpriority' => 'high',
          ],
        ];
      }
    }

    return new DomainPresentation(
      [
        'purpose' => $purpose,
        'title' => $title,
        'short_title' => $shortTitle,
        'home_url' => $homeUrl->toString(),
        'logo_alt' => $title,
      ],
      [
        'brand_media' => $brandMedia,
        'navigation' => NULL,
        'actions' => NULL,
      ],
      $cacheability,
    );
  }

}
