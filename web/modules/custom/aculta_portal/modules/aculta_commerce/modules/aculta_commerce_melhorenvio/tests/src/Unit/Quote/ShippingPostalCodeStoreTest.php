<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Quote\ShippingPostalCodeStore;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Persists the last valid CEP for anonymous and authenticated shoppers.
 */
#[CoversClass(ShippingPostalCodeStore::class)]
#[Group('aculta_commerce_melhorenvio')]
final class ShippingPostalCodeStoreTest extends UnitTestCase {

  /**
   * Creates a store with the requested account and saved profile state.
   */
  private function store(
    bool $authenticated = FALSE,
    mixed $accountPostalCode = NULL,
    ?Request $request = NULL,
  ): ShippingPostalCodeStore {
    $requests = new RequestStack();
    if ($request !== NULL) {
      $requests->push($request);
    }

    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('id')->willReturn($authenticated ? '42' : '0');

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturn($accountPostalCode);
    $userData->method('set');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    if ($authenticated && $accountPostalCode === NULL) {
      $fieldItem = $this->createMock(FieldItemInterface::class);
      $fieldItem->method('getValue')->willReturn(['postal_code' => '74003-010']);
      $fieldList = $this->createMock(FieldItemListInterface::class);
      $fieldList->method('isEmpty')->willReturn(FALSE);
      $fieldList->method('first')->willReturn($fieldItem);
      $profile = $this->createMock(ProfileInterface::class);
      $profile->method('hasField')->with('field_address')->willReturn(TRUE);
      $profile->method('get')->with('field_address')->willReturn($fieldList);
      $storage = $this->createMock(ProfileStorageInterface::class);
      $storage->method('loadByUser')->willReturn($profile);
      $entityTypeManager->method('getStorage')->with('profile')->willReturn($storage);
    }

    $cepLookup = $this->createMock(CepLookupInterface::class);
    $cepLookup->method('normalize')->willReturnCallback(static function (string $value): ?string {
      if (preg_match('/^\s*(\d{5})\s*-?\s*(\d{3})\s*$/D', $value, $matches) !== 1) {
        return NULL;
      }
      return $matches[1] . $matches[2];
    });

    return new ShippingPostalCodeStore($requests, $account, $userData, $entityTypeManager, $cepLookup);
  }

  /**
   * Creates a request with an isolated in-memory session.
   */
  private function request(): Request {
    $request = new Request();
    $request->setSession(new Session(new MockArraySessionStorage()));
    return $request;
  }

  /**
   * Anonymous CEPs remain only in the visitor session.
   */
  public function testAnonymousLastPostalCodeUsesOnlyTheSession(): void {
    $request = $this->request();
    $store = $this->store(request: $request);

    self::assertSame('01310100', $store->save('01310-100'));
    self::assertSame('01310100', $store->load());
  }

  /**
   * Authenticated CEPs use the private user data store.
   */
  public function testAuthenticatedLastPostalCodeIsPrivateUserData(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('42');
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::once())->method('set')->with('aculta_commerce_melhorenvio', 42, 'last_postal_code', '01310100');
    $userData->method('get')->willReturn('01310100');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $cepLookup = $this->createMock(CepLookupInterface::class);
    $cepLookup->method('normalize')->willReturn('01310100');
    $store = new ShippingPostalCodeStore(new RequestStack(), $account, $userData, $entityTypeManager, $cepLookup);

    self::assertSame('01310100', $store->save('01310-100'));
    self::assertSame('01310100', $store->load());
  }

  /**
   * A saved account address supplies the initial CEP when no preference exists.
   */
  public function testAuthenticatedAccountAddressPrefillsWhenNoLastPostalCodeExists(): void {
    self::assertSame('74003010', $this->store(authenticated: TRUE)->load());
  }

  /**
   * Invalid CEPs are rejected before any account data is written.
   */
  public function testInvalidPostalCodeIsNeverSaved(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::never())->method('set');
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn('42');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $cepLookup = $this->createMock(CepLookupInterface::class);
    $cepLookup->method('normalize')->willReturn(NULL);
    $store = new ShippingPostalCodeStore(new RequestStack(), $account, $userData, $entityTypeManager, $cepLookup);

    self::assertNull($store->save('CEP 01310-100'));
  }

}
