<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Symfony\Component\Routing\Route;

/**
 * Centralizes route-family ownership rules shared across Portal boundaries.
 */
final class DomainRoutePolicy {

  /**
   * Canonical route metadata names.
   */
  private const SITE_KEY_OPTION = '_aculta_site_key';

  private const SITE_KEYS_OPTION = '_aculta_site_keys';

  private const CANONICAL_SITE_KEY_OPTION = '_aculta_cross_domain_canonical_site_key';

  /**
   * Route option names removed by the site-key cutover.
   *
   * These are rejected if found on a route; they are never read as fallback.
   */
  private const REMOVED_PURPOSE_OPTIONS = [
    '_aculta_domain_purpose',
    '_aculta_domain_purposes',
    '_aculta_cross_domain_canonical_purpose',
  ];

  /**
   * Returns the route's declared site key.
   */
  public static function declaredSiteKey(Route $route): ?string {
    if (self::hasInvalidSiteKeyOptions($route)) {
      return NULL;
    }
    $value = $route->getOption(self::SITE_KEY_OPTION);
    return self::isSiteKey($value) ? $value : NULL;
  }

  /**
   * Returns the canonical target site key.
   */
  public static function canonicalSiteKey(Route $route): ?string {
    if (self::hasInvalidSiteKeyOptions($route)) {
      return NULL;
    }
    $value = $route->getOption(self::CANONICAL_SITE_KEY_OPTION);
    return self::isSiteKey($value) ? $value : NULL;
  }

  /**
   * Returns TRUE when removed or malformed site-key metadata is present.
   */
  public static function hasInvalidSiteKeyOptions(Route $route): bool {
    $options = $route->getOptions();
    foreach (self::REMOVED_PURPOSE_OPTIONS as $option) {
      if (array_key_exists($option, $options)) {
        return TRUE;
      }
    }
    foreach ([self::SITE_KEY_OPTION, self::CANONICAL_SITE_KEY_OPTION] as $option) {
      $value = $route->getOption($option);
      if ($value !== NULL && !self::isSiteKey($value)) {
        return TRUE;
      }
    }
    $siteKeys = $route->getOption(self::SITE_KEYS_OPTION);
    return $siteKeys !== NULL
      && (!is_array($siteKeys)
        || !array_is_list($siteKeys)
        || array_filter($siteKeys, [self::class, 'isSiteKey']) !== $siteKeys);
  }

  /**
   * Checks an explicitly shared route's allowed site keys.
   *
   * A shared route must list both its declared owner and the current site key.
   * This keeps the exception narrow and prevents a route from becoming public
   * on arbitrary domains by setting only a boolean option.
   */
  public static function allowsSharedSite(Route $route, string $declaredSiteKey, string $currentSiteKey): bool {
    if (!self::isSiteKey($declaredSiteKey)
      || !self::isSiteKey($currentSiteKey)
      || self::hasInvalidSiteKeyOptions($route)) {
      return FALSE;
    }
    if ($declaredSiteKey === $currentSiteKey) {
      return TRUE;
    }
    if ($route->getOption('_admin_route')) {
      return FALSE;
    }
    $siteKeys = $route->getOption(self::SITE_KEYS_OPTION);
    if (!is_array($siteKeys) || array_filter($siteKeys, [self::class, 'isSiteKey']) !== $siteKeys) {
      return FALSE;
    }
    $siteKeys = array_values(array_unique(array_filter($siteKeys, 'is_string')));
    return in_array($declaredSiteKey, $siteKeys, TRUE)
      && in_array($currentSiteKey, $siteKeys, TRUE);
  }

  /**
   * Returns TRUE for central transaction routes.
   *
   * This includes cart, checkout, payment return and notification routes.
   * Donation routes are handled by isDonationFlowRouteName().
   */
  public static function isCentralTransactionRouteName(string $routeName): bool {
    return str_starts_with($routeName, 'commerce_cart.')
      || str_starts_with($routeName, 'commerce_checkout.')
      || str_starts_with($routeName, 'commerce_payment.checkout.')
      || $routeName === 'commerce_payment.notify';
  }

  /**
   * Returns TRUE for routes in the donation flow.
   *
   * The responsible person decided on 2026-10-17 that the donation and
   * subscription flow belongs to SUPPORT. Only gateway routes belong to MAIN.
   */
  public static function isDonationFlowRouteName(string $routeName): bool {
    return str_starts_with($routeName, 'aculta_donation.');
  }

  /**
   * Returns the owning site key for a donation route.
   *
   * Donation selection routes belong to SUPPORT. The Commerce checkout
   * portion belongs to MAIN.
   */
  public static function siteKeyForDonationRoute(string $routeName): string {
    return $routeName === 'aculta_donation.donation_controller_formPage' ? 'main' : 'support';
  }

  /**
   * Validates one of the stable Portal site keys.
   */
  private static function isSiteKey(mixed $value): bool {
    return is_string($value) && SiteKey::tryFrom($value) !== NULL;
  }

  /**
   * Returns TRUE for private transaction routes that must not be indexed.
   */
  public static function isTransactionalSeoRouteName(string $routeName): bool {
    return self::isCentralTransactionRouteName($routeName)
      || self::isDonationFlowRouteName($routeName)
      || str_starts_with($routeName, 'commerce_payment.')
      || str_starts_with($routeName, 'entity.commerce_order.');
  }

}
