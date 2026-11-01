<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_studio\Experience\AccountExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the personal experience metadata, access and safe account links.
 */
#[CoversClass(AccountExperienceProvider::class)]
#[Group('aculta_studio')]
final class AccountExperienceProviderTest extends UnitTestCase {

  /**
   * The account entry is registered for the account purpose only.
   */
  public function testRegistryMetadataAndPurpose(): void {
    $provider = new AccountExperienceProvider();
    $manager = new StudioExperienceManager();
    $manager->addProvider($provider);

    self::assertSame('conta', $provider->id());
    self::assertSame('Minha Conta', $provider->label());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_studio.account', $provider->routeName());
    self::assertTrue($provider->available());
    self::assertSame($provider, $manager->get('conta'));

    $account = $this->createMock(AccountInterface::class);
    self::assertFalse($manager->access('conta', 'forum', $account)->isAllowed());
  }

  /**
   * A visitor or a member without Portal permission cannot access the entry.
   */
  public function testRequiresAuthenticationAndPermission(): void {
    $provider = new AccountExperienceProvider();
    $visitor = $this->createMock(AccountInterface::class);
    $visitor->method('isAuthenticated')->willReturn(FALSE);
    $visitor->method('hasPermission')->willReturn(TRUE);
    self::assertFalse($provider->access($visitor)->isAllowed());

    $restricted = $this->createMock(AccountInterface::class);
    $restricted->method('isAuthenticated')->willReturn(TRUE);
    $restricted->method('hasPermission')->willReturn(FALSE);
    self::assertFalse($provider->access($restricted)->isAllowed());

    $member = $this->createMock(AccountInterface::class);
    $member->method('isAuthenticated')->willReturn(TRUE);
    $member->method('hasPermission')->with('access aculta portal')->willReturn(TRUE);
    $access = $provider->access($member);
    self::assertTrue($access->isAllowed());
    self::assertContains('user', $access->getCacheContexts());
  }

  /**
   * The new entry links to existing forms, never to /minha-conta.
   */
  public function testLinksReuseExistingAccountRoutes(): void {
    $document = $this->createMock(AccountDocumentInterface::class);
    $document->method('isAvailable')->willReturn(TRUE);
    $build = (new AccountExperienceProvider($document))->build();

    self::assertSame('account-entry', $build['#attributes']['data-portal-ui']);
    self::assertSame('item_list', $build['sections']['#theme']);
    $routes = array_map(static fn (array $link): string =>
      $link['#url']->getRouteName(), $build['sections']['#items']);
    self::assertSame([
      'aculta_studio.account_data',
      'aculta_studio.account_address',
      'aculta_studio.account_documents',
      'aculta_studio.account_security',
      'aculta_studio.account_password',
      'aculta_studio.account_connections',
      'aculta_studio.account_orders',
      'aculta_studio.account_support',
      'aculta_studio.account_preferences',
      'aculta_portal.dashboard',
    ], $routes);
    self::assertContains('user', $build['#cache']['contexts']);
  }

  /**
   * Official Suite names and canonical routes are kept.
   */
  public function testSuiteLinksUseOfficialNamesAndRoutes(): void {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('wiki420_aculta_org');
    $domain->method('isHttps')->willReturn(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($domain);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('domain')->willReturn($storage);
    $domains = new DomainPurposeManager(
      $entities,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    $items = (new AccountExperienceProvider(NULL, $domains))->build()['suites']['#items'];
    $labels = array_map(static fn (array $item): string => $item['#title']->getUntranslatedString(), $items);
    $routes = array_map(static fn (array $item): string => $item['#url']->getRouteName(), $items);

    self::assertSame([
      'Marley',
      'Ganjalab',
      'ACULTA STUDIO WE SATIVA',
      'ACULTA STUDIO GREEN STASH',
      'Culturas',
    ], $labels);
    self::assertSame([
      'aculta_studio.home',
      'aculta_lms.student_dashboard',
      'aculta_studio.we_sativa',
      'aculta_commerce.green_stash',
      'aculta_events.organizer_dashboard',
    ], $routes);
  }

  /**
   * A missing CPF/CNPJ owner cannot create a broken account link.
   */
  public function testDocumentsHiddenWhenOwnerUnavailable(): void {
    $items = (new AccountExperienceProvider())->build()['sections']['#items'];
    $routeNames = array_map(static fn (array $item): string =>
      $item['#url']->getRouteName(), $items);
    self::assertNotContains('aculta_studio.account_documents', $routeNames);

    $service = $this->createMock(AccountDocumentInterface::class);
    $service->method('isAvailable')->willReturn(FALSE);
    $hidden = (new AccountExperienceProvider($service))->build()['sections']['#items'];
    self::assertCount(count($items), $hidden);
  }

}
