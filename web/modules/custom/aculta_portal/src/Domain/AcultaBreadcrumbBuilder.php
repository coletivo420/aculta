<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Builds concise, domain-aware breadcrumbs for public ACULTA experiences. */
final class AcultaBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /** Purposes with a real public experience in this phase. */
  private const PUBLIC_PURPOSES = ['main', 'account', 'support', 'magazine'];

  /** Routes whose paths or titles may contain tokens or technical identifiers. */
  private const HIDDEN_ROUTES = [
    'system.403',
    'system.404',
    'user.logout',
    'user.logout.confirm',
    'user.reset',
    'user.reset.form',
    'user.reset.login',
    'entity.user.edit_form',
    'commerce_payment.notify',
    'social_auth_google.redirect_to_google',
    'social_auth_google.callback',
  ];

  public function __construct(
    private readonly DomainPurposeManager $domainPurpose,
    private readonly RequestStack $requestStack,
    private readonly TitleResolverInterface $titleResolver,
    private readonly PathMatcherInterface $pathMatcher,
  ) {}

  /** {@inheritdoc} */
  public function applies(RouteMatchInterface $route_match): bool {
    $route = $route_match->getRouteObject();
    // Claro keeps its own breadcrumb behavior.
    if (!$route || $route->getOption('_admin_route')) {
      return FALSE;
    }
    return $this->domainPurpose->getCurrentPurpose() !== NULL;
  }

  /** {@inheritdoc} */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = (new Breadcrumb())
      ->addCacheContexts(['route', 'url.path', 'domain']);
    $route = $route_match->getRouteObject();
    $purpose = $this->domainPurpose->getCurrentPurpose();
    if (!$route || !$purpose || !in_array($purpose, self::PUBLIC_PURPOSES, TRUE)
      || in_array($route_match->getRouteName(), self::HIDDEN_ROUTES, TRUE)
      || $route_match->getRouteName() === 'user.login_status.http'
      // A Visão geral da conta é a página inicial do domínio, mas leva a raiz "Minha conta" como as outras seções.
      || ($this->isFrontPage() && $route_match->getRouteName() !== 'aculta_portal.dashboard')) {
      return $breadcrumb;
    }

    $root = $this->root($purpose);
    $root_url = $this->domainPurpose->pathUrl($purpose, '/');
    if (!$root_url) {
      return $breadcrumb;
    }
    $breadcrumb->addLink(Link::fromTextAndUrl($root, $root_url));

    // Only add a parent where the site has a real, stable hierarchy.
    if ($purpose === 'account' && $route_match->getRouteName() === 'aculta_portal.my_data_address') {
      $parent_url = $this->domainPurpose->routeUrl('account', 'aculta_portal.my_data');
      if ($parent_url) {
        $breadcrumb->addLink(Link::fromTextAndUrl($this->t('Meus dados'), $parent_url));
      }
    }
    elseif ($purpose === 'account' && $route_match->getRouteName() === 'change_mail_page.change_mail') {
      $parent_url = $this->domainPurpose->routeUrl('account', 'aculta_portal.security');
      if ($parent_url) {
        $breadcrumb->addLink(Link::fromTextAndUrl($this->t('Segurança'), $parent_url));
      }
    }
    elseif ($purpose === 'magazine') {
      $term = $route_match->getParameter('taxonomy_term');
      if ($term instanceof TermInterface && in_array($term->bundle(), ['editorial_author', 'editorial_category'], TRUE)) {
        foreach ($this->taxonomyParents($term) as $parent) {
          $url = $parent->toUrl('canonical');
          $breadcrumb->addLink(Link::fromTextAndUrl($parent->label(), $url));
          $breadcrumb->addCacheableDependency($parent);
        }
      }
    }

    $breadcrumb->addCacheableDependency($route);
    $domain = $this->domainPurpose->getDomain($purpose);
    if ($domain) {
      $breadcrumb->addCacheableDependency($domain);
    }
    foreach ($route_match->getParameters()->all() as $parameter) {
      if ($parameter instanceof NodeInterface || $parameter instanceof TermInterface) {
        $breadcrumb->addCacheableDependency($parameter);
      }
    }
    return $breadcrumb;
  }

  /** Returns the safely resolved title for the current, non-technical page. */
  public function currentTitle(RouteMatchInterface $route_match): ?string {
    $route = $route_match->getRouteObject();
    $purpose = $this->domainPurpose->getCurrentPurpose();
    if (!$route || !$purpose || !in_array($purpose, self::PUBLIC_PURPOSES, TRUE)
      || in_array($route_match->getRouteName(), self::HIDDEN_ROUTES, TRUE)
      || $route_match->getRouteName() === 'user.login_status.http'
      || ($this->isFrontPage() && $route_match->getRouteName() !== 'aculta_portal.dashboard')) {
      return NULL;
    }

    $entity = $route_match->getParameter('node') ?? $route_match->getParameter('taxonomy_term');
    if ($entity instanceof NodeInterface || $entity instanceof TermInterface) {
      return trim(strip_tags((string) $entity->label())) ?: NULL;
    }
    $title = $this->titleResolver->getTitle($this->requestStack->getCurrentRequest(), $route);
    if (is_array($title) || !is_scalar($title) && !$title instanceof \Stringable) {
      return NULL;
    }
    $title = trim(strip_tags((string) $title));
    return $title !== '' ? $title : NULL;
  }

  /** Gets real taxonomy ancestors from the existing parent relationship. */
  private function taxonomyParents(TermInterface $term): array {
    $parents = [];
    $seen = [];
    while ($term->hasField('parent') && !$term->get('parent')->isEmpty()) {
      $parent = $term->get('parent')->entity;
      if (!$parent instanceof TermInterface || isset($seen[$parent->id()])) {
        break;
      }
      $seen[$parent->id()] = TRUE;
      array_unshift($parents, $parent);
      $term = $parent;
    }
    return $parents;
  }

  /** Supplies each public domain's own root label. */
  private function root(string $purpose): string {
    return match ($purpose) {
      'account' => (string) $this->t('Minha conta'),
      'support' => (string) $this->t('Apoio'),
      'magazine' => (string) $this->t('Observatório da Maconha Coletivo 420'),
      default => (string) $this->t('Início'),
    };
  }

  private function isFrontPage(): bool {
    return $this->pathMatcher->isFrontPage();
  }

}
