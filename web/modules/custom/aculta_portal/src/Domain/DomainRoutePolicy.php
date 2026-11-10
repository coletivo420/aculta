<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Symfony\Component\Routing\Route;

/**
 * Centralizes route-family ownership rules shared across Portal boundaries.
 */
final class DomainRoutePolicy {

  /**
   * Checks an explicitly shared route's allowed site keys.
   *
   * A shared route must list both its declared owner and the current site key.
   * This keeps the exception narrow and prevents a route from becoming public
   * on arbitrary domains by setting only a boolean option.
   */
  public static function allowsSharedSite(Route $route, string $declaredSiteKey, string $currentSiteKey): bool {
    if ($declaredSiteKey === $currentSiteKey) {
      return TRUE;
    }
    if ($route->getOption('_admin_route')) {
      return FALSE;
    }
    $siteKeys = $route->getOption('_aculta_domain_purposes');
    if (!is_array($siteKeys)) {
      return FALSE;
    }
    $siteKeys = array_values(array_unique(array_filter($siteKeys, 'is_string')));
    return in_array($declaredSiteKey, $siteKeys, TRUE)
      && in_array($currentSiteKey, $siteKeys, TRUE);
  }

  /**
   * Compatibility wrapper for callers using the legacy API name.
   *
   * Remove after RN-04 confirms zero callers. Route options remain persisted
   * until the reversible config migration.
   */
  public static function allowsSharedPurpose(Route $route, string $declaredPurpose, string $currentPurpose): bool {
    return self::allowsSharedSite($route, $declaredPurpose, $currentPurpose);
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
   * Compatibility wrapper for callers using the legacy API name.
   *
   * Remove after RN-04 confirms zero callers.
   */
  public static function purposeForDonationRoute(string $routeName): string {
    return self::siteKeyForDonationRoute($routeName);
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
