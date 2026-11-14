<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Presentation;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
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
  private const SITE_IDENTITIES = [
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
      'title' => 'Revista 420',
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
    'eventos' => [
      'title' => 'Eventos da ACULTA',
      'short_title' => 'Eventos',
    ],
  ];

  public function __construct(
    private readonly DomainSiteManager $siteManager,
    private readonly TranslationInterface $translation,
    private readonly ?WikiSearchFormBuilderInterface $wikiSearchFormBuilder,
    private readonly PathMatcherInterface $pathMatcher,
  ) {}

  /**
   * Builds presentation for the active Domain purpose.
   */
  public function buildCurrent(): ?DomainPresentation {
    $siteKey = $this->siteManager->getCurrentSiteKey()?->value;
    return $siteKey !== NULL ? $this->build($siteKey) : NULL;
  }

  /**
   * Builds a presentation contract for one stable purpose.
   */
  public function build(string $siteKey): ?DomainPresentation {
    $definition = self::SITE_IDENTITIES[$siteKey] ?? NULL;
    if ($definition === NULL) {
      return NULL;
    }

    $domain = $this->siteManager->getDomain($siteKey);
    $homeUrl = $this->siteManager->pathUrl($siteKey, '/');
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

    // O Portal escolhe a marca e informa uma chave neutra. O asset e a forma
    // da imagem pertencem ao tema (ThemeHooks::brandMediaRender). Sem tema que
    // trate a chave, o cabeçalho usa só o título.
    $brandMedia = $siteKey === 'wiki' ? ['#brand_key' => 'wiki420', '#alt' => $title] : NULL;

    $showcase = $siteKey === 'main' && $this->pathMatcher->isFrontPage()
      ? $this->buildShowcase($cacheability)
      : NULL;
    $domainSites = $this->buildDomainSites($siteKey, $cacheability);

    return new DomainPresentation(
      [
        'site_key' => $siteKey,
        'title' => $title,
        'short_title' => $shortTitle,
        'home_url' => $homeUrl->toString(),
        'logo_alt' => $title,
      ],
      [
        'brand_media' => $brandMedia,
        'navigation' => NULL,
        'actions' => NULL,
        'wiki_search' => $siteKey === 'wiki' && $this->wikiSearchFormBuilder !== NULL
          ? $this->wikiSearchFormBuilder->build()
          : NULL,
        'showcase' => $showcase,
      ],
      $cacheability,
      [
        'institution_bar_account' => $siteKey === 'account',
        'show_utility_bar' => $siteKey !== 'links',
        'links_page' => $siteKey === 'links',
        // Domain Header (navigation-bar) só existe no site principal e na Wiki.
        // O tema apenas renderiza estes flags e não resolve o site key.
        'show_site_header' => in_array($siteKey, ['main', 'wiki'], TRUE),
        // Menu do purpose principal: só aparece no próprio cabeçalho principal.
        'show_site_menu' => $siteKey === 'main',
      ],
      $domainSites,
    );
  }

  /**
   * Builds the cross-site navigation contract from enabled Domain entities.
   *
   * @return array
   *   Published sites with environment-specific absolute URLs.
   */
  private function buildDomainSites(
    string $currentSiteKey,
    CacheableMetadata $cacheability,
  ): array {
    $sites = [];
    foreach (array_keys(self::SITE_IDENTITIES) as $siteKey) {
      $domain = $this->siteManager->getDomain($siteKey);
      $url = $this->siteManager->pathUrl($siteKey, '/');
      if ($domain === NULL || !$domain->status() || $url === NULL) {
        continue;
      }
      $cacheability->addCacheableDependency($domain);
      $sites[] = [
        'site_key' => $siteKey,
        'title' => (string) $this->translation->translate(
          self::SITE_IDENTITIES[$siteKey]['title'],
        ),
        'url' => $url->toString(),
        'current' => $siteKey === $currentSiteKey,
      ];
    }
    return $sites;
  }

  /**
   * Vitrine da home MAIN, com um link por site key que tenha Domain ativo.
   *
   * Cada subdomínio responde somente no próprio host; a vitrine apenas aponta
   * para ele. DomainSiteManager resolve URLs no ambiente atual.
   * Site keys sem Domain ativo são omitidos.
   *
   * @return list<array{
   *   site_key: string,
   *   title: string,
   *   url: string
   *   }>
   *   Vínculos da vitrine com os domínios ativos.
   */
  private function buildShowcase(CacheableMetadata $cacheability): array {
    $items = [];
    foreach (array_keys(self::SITE_IDENTITIES) as $siteKey) {
      if ($siteKey === 'main') {
        continue;
      }
      $domain = $this->siteManager->getDomain($siteKey);
      $url = $this->siteManager->pathUrl($siteKey, '/');
      if ($domain === NULL || $url === NULL) {
        continue;
      }
      $cacheability->addCacheableDependency($domain);
      $items[] = [
        'site_key' => $siteKey,
        'title' => (string) $this->translation->translate(self::SITE_IDENTITIES[$siteKey]['title']),
        'url' => $url->toString(),
      ];
    }
    return $items;
  }

}
