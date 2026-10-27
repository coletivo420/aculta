<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Last valid shipping postal code: visitor session or private per-user data.
 */
final class ShippingPostalCodeStore {

  private const SESSION_KEY = 'aculta_commerce_melhorenvio.last_postal_code';

  private const USER_DATA_KEY = 'last_postal_code';

  /**
   * Creates the private CEP store.
   */
  public function __construct(
    private readonly RequestStack $requests,
    private readonly AccountProxyInterface $currentUser,
    private readonly UserDataInterface $userData,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CepLookupInterface $cepLookup,
  ) {}

  /**
   * Saves only a valid normalized CEP.
   *
   * @return string|null
   *   The normalized CEP, or NULL when invalid.
   */
  public function save(string $postalCode): ?string {
    $normalized = $this->cepLookup->normalize($postalCode);
    if ($normalized === NULL) {
      return NULL;
    }

    if ($this->currentUser->isAuthenticated() && (int) $this->currentUser->id() > 0) {
      $this->userData->set('aculta_commerce_melhorenvio', (int) $this->currentUser->id(), self::USER_DATA_KEY, $normalized);
    }
    $this->session()?->set(self::SESSION_KEY, $normalized);
    return $normalized;
  }

  /**
   * Returns the last valid CEP, falling back to the account's saved address.
   */
  public function load(): ?string {
    if ($this->currentUser->isAuthenticated() && (int) $this->currentUser->id() > 0) {
      $stored = $this->normalizeStored($this->userData->get(
        'aculta_commerce_melhorenvio',
        (int) $this->currentUser->id(),
        self::USER_DATA_KEY,
      ));
      if ($stored !== NULL) {
        return $stored;
      }

      // The customer profile is the source of truth for the saved address.
      $profileStorage = $this->entityTypeManager->getStorage('profile');
      $profile = $profileStorage instanceof ProfileStorageInterface
        ? $profileStorage->loadByUser($this->currentUser, 'authenticated')
        : NULL;
      if ($profile instanceof ProfileInterface
        && $profile->hasField('field_address')
        && !$profile->get('field_address')->isEmpty()) {
        $value = $profile->get('field_address')->first()->getValue();
        $postalCode = $this->normalizeStored($value['postal_code'] ?? NULL);
        if ($postalCode !== NULL) {
          return $postalCode;
        }
      }
    }

    return $this->normalizeStored($this->session()?->get(self::SESSION_KEY));
  }

  /**
   * Validates a stored value before returning it to the form.
   */
  private function normalizeStored(mixed $value): ?string {
    return is_string($value) ? $this->cepLookup->normalize($value) : NULL;
  }

  /**
   * Returns the active visitor session, when one exists.
   */
  private function session(): ?SessionInterface {
    $request = $this->requests->getCurrentRequest();
    return $request !== NULL && $request->hasSession() ? $request->getSession() : NULL;
  }

}
