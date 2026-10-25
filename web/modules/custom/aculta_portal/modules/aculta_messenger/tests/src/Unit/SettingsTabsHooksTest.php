<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\SettingsTabsHooks;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests account navigation for shared communication preferences.
 */
#[CoversClass(SettingsTabsHooks::class)]
#[Group('aculta_messenger')]
final class SettingsTabsHooksTest extends UnitTestCase {

  private ?ContainerInterface $previousContainer = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->previousContainer = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->previousContainer instanceof ContainerInterface) {
      \Drupal::setContainer($this->previousContainer);
    }
    else {
      \Drupal::unsetContainer();
    }
    parent::tearDown();
  }

  /**
   * Members without private-message permission can still set notice email modes.
   */
  public function testEmailSettingsTabRequiresPortalAccessOnly(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $permission): bool => $permission === 'access aculta portal',
    );
    $tabs = [];

    (new SettingsTabsHooks($account))->addCommunicationSettings($tabs);

    self::assertSame([
      'title' => 'Notificações por e-mail',
      'route' => 'aculta_messenger.communication_settings',
    ], $tabs['email']);
  }

}
