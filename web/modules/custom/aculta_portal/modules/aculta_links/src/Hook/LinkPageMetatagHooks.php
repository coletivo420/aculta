<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Hook;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_links\Entity\LinkPage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds metadata to public pages and hides private tools from search.
 */
final class LinkPageMetatagHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Alters Metatag defaults for the public microsite route.
   *
   * @param array $tags
   *   The tag values being prepared by Metatag.
   * @param array $context
   *   The entity context used for token replacement.
   */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$tags, array &$context): void {
    $route_name = $this->routeMatch->getRouteName();
    if (in_array($route_name, [
      'aculta_links.dashboard',
      'aculta_links.page_editor',
      'aculta_links.page_preview',
    ], TRUE)) {
      $tags['robots'] = 'noindex, nofollow';
      unset($tags['canonical_url'], $tags['og_url'], $tags['schema_web_page_url']);
      return;
    }
    if (str_starts_with((string) $route_name, 'entity.aculta_link')) {
      $tags['robots'] = 'noindex, nofollow';
      unset($tags['canonical_url'], $tags['og_url'], $tags['schema_web_page_url']);
      return;
    }

    if ($route_name !== 'aculta_links.page_public') {
      return;
    }

    $slug = $this->routeMatch->getParameter('slug');
    if (!is_string($slug) || $slug === '') {
      return;
    }

    $pages = $this->entityTypeManager->getStorage('aculta_link_page')
      ->loadByProperties(['slug' => $slug, 'status' => TRUE]);
    $page = reset($pages);
    if (!$page instanceof LinkPage) {
      return;
    }

    $title = $page->label();
    $site_name = $this->configFactory->get('system.site')->get('name');
    if (is_string($site_name) && $site_name !== '') {
      $title = new TranslatableMarkup('@page | @site', [
        '@page' => $title,
        '@site' => $site_name,
      ]);
    }
    $tags['title'] = (string) $title;
    $tags['og_title'] = $page->label();
    $tags['og_type'] = 'website';
    $tags['schema_web_page_type'] = 'WebPage';
    $tags['schema_web_page_name'] = $page->label();

    $description = trim(PlainTextOutput::renderFromHtml((string) $page->get('description')->value));
    if ($description !== '') {
      $description = Unicode::truncate($description, 300, TRUE, TRUE);
      $tags['description'] = $description;
      $tags['og_description'] = $description;
      $tags['schema_web_page_description'] = $description;
    }

    $canonical = $this->domainPurposeManager->canonicalRouteUrl(
      'links',
      'aculta_links.page_public',
      ['slug' => $slug],
    );
    $canonical_url = $canonical?->toString();
    if (is_string($canonical_url) && $canonical_url !== '') {
      $tags['canonical_url'] = $canonical_url;
      $tags['og_url'] = $canonical_url;
      $tags['schema_web_page_url'] = $canonical_url;
    }
    else {
      unset($tags['canonical_url'], $tags['og_url'], $tags['schema_web_page_url']);
    }

    // Metatag will use this entity when replacing the configured tag tokens.
    $context['entity'] = $page;
  }

}
