<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Presentation;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Path\PathMatcherInterface;
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
    private readonly WikiSearchFormBuilder $wikiSearchFormBuilder,
    private readonly PathMatcherInterface $pathMatcher,
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
        'url.path.is_front',
        'domain',
        'languages:language_interface',
        'url.site',
      ])
      ->addCacheableDependency($domain);

    // O Portal decide qual marca o domínio usa e informa só a chave neutra; o asset e a forma da imagem são do tema
    // (ThemeHooks::brandMediaRender). Sem tema que trate a chave, o cabeçalho usa só o título.
    $brandMedia = $purpose === 'wiki' ? ['#brand_key' => 'wiki420', '#alt' => $title] : NULL;

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
        'wiki_search' => $purpose === 'wiki' ? $this->wikiSearchFormBuilder->build() : NULL,
      ],
      $cacheability,
      [
        'institution_bar_account' => $purpose === 'account',
        'show_utility_bar' => $purpose !== 'links',
        'links_page' => $purpose === 'links',
        'show_site_header' => $purpose === 'wiki' || ($purpose === 'main' && $this->pathMatcher->isFrontPage()),
      ],
    );
  }

}
