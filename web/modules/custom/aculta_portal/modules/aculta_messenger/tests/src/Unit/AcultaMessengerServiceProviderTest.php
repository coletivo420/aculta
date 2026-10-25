<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\AcultaMessengerServiceProvider;
use Drupal\aculta_messenger\Aviso\PersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Hook\PersonalNoticeAdapterHooks;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Drupal\Core\Hook\HookCollectorPass;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests optional domain integrations in the Messenger service container.
 */
#[Group('aculta_messenger')]
final class AcultaMessengerServiceProviderTest extends TestCase {

  /**
   * The central container remains valid when all domain adapters are absent.
   */
  public function testOptionalAdaptersAreNotRegisteredWithoutProducers(): void {
    $container = new ContainerBuilder();

    (new AcultaMessengerServiceProvider())->alter($container);

    self::assertFalse($container->hasDefinition('aculta_messenger.lms_notice_manager'));
    self::assertFalse($container->hasDefinition('aculta_messenger.wiki_notice_manager'));
    self::assertFalse($container->hasDefinition('aculta_messenger.commerce_notice_manager'));
    self::assertFalse($container->hasDefinition('aculta_messenger.donation_notice_manager'));
    self::assertTrue($container->hasDefinition(PersonalNoticeAdapterHooks::class));
    $argument = $container->getDefinition(PersonalNoticeAdapterHooks::class)->getArgument(0);
    self::assertInstanceOf(ServiceLocatorArgument::class, $argument);
    self::assertSame([], array_keys($argument->getValues()));
    $logger = $container->getDefinition(PersonalNoticeAdapterHooks::class)->getArgument(1);
    self::assertInstanceOf(Reference::class, $logger);
    self::assertSame('logger.channel.aculta_messenger', (string) $logger);
  }

  /**
   * Each enabled domain module registers only its central consumer.
   */
  public function testAdaptersFollowProducerServicePresence(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('aculta_wiki.proposal_decision', new Definition());

    (new AcultaMessengerServiceProvider())->alter($container);

    self::assertTrue($container->hasDefinition('aculta_messenger.wiki_notice_manager'));
    self::assertTrue($container->hasDefinition('aculta_messenger.wiki_notice_event_subscriber'));
    self::assertFalse($container->hasDefinition('aculta_messenger.lms_notice_manager'));
    self::assertFalse($container->hasDefinition('aculta_messenger.commerce_notice_manager'));
  }

  /**
   * Donation services register the shared kernel consumer only when enabled. */
  public function testDonationAdapterFollowsProducerServicePresence(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('Drupal\\aculta_donation\\Hook\\DonationNoticeEventPublisher', new Definition());

    (new AcultaMessengerServiceProvider())->alter($container);

    self::assertTrue($container->hasDefinition('aculta_messenger.donation_notice_manager'));
    self::assertTrue($container->hasDefinition('aculta_messenger.donation_notice_event_consumer'));
    self::assertFalse($container->hasDefinition('aculta_messenger.lms_notice_manager'));
  }

  /**
   * Read-state dispatch uses only the optional adapters compiled into the site.
   */
  public function testReadStateLocatorContainsOnlyEnabledAdapters(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('aculta_messenger.notice_read_state', new Definition());
    $container->setDefinition('aculta_wiki.proposal_decision', new Definition());

    (new AcultaMessengerServiceProvider())->alter($container);

    $argument = $container->getDefinition('aculta_messenger.notice_read_state')->getArgument(1);
    self::assertInstanceOf(ServiceLocatorArgument::class, $argument);
    self::assertSame(['wiki'], array_keys($argument->getValues()));
    self::assertSame('aculta_messenger.wiki_notice_manager', (string) $argument->getValues()['wiki']);
  }

  /**
   * The core friendship adapter serves both site notices and read actions.
   */
  public function testFriendshipAdapterIsRegisteredForListAndReadState(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('aculta_messenger.friendship_notice_manager', new Definition());
    $container->setDefinition('aculta_messenger.notice_read_state', new Definition());

    (new AcultaMessengerServiceProvider())->alter($container);

    $readAdapters = $container->getDefinition('aculta_messenger.notice_read_state')->getArgument(1);
    $listHook = $container->getDefinition(PersonalNoticeAdapterHooks::class)->getArgument(0);
    self::assertInstanceOf(ServiceLocatorArgument::class, $readAdapters);
    self::assertInstanceOf(ServiceLocatorArgument::class, $listHook);
    self::assertSame(['friendship'], array_keys($readAdapters->getValues()));
    self::assertSame(['friendship'], array_keys($listHook->getValues()));
    self::assertSame('aculta_messenger.friendship_notice_manager', (string) $readAdapters->getValues()['friendship']);
  }

  /**
   * Drupal discovers central notice hooks and wires only enabled adapters.
   */
  public function testHookCollectorDiscoversSharedAdapterHooks(): void {
    $container = new ContainerBuilder();
    $modulePath = dirname(__DIR__, 3) . '/aculta_messenger.info.yml';
    $container->setParameter('container.modules', [
      'aculta_messenger' => ['pathname' => $modulePath],
    ]);
    foreach ([
      'aculta_lms.class_activity_deadline_manager',
      'aculta_wiki.proposal_decision',
      'aculta_commerce.checkout_provider',
      'Drupal\\aculta_donation\\Hook\\DonationNoticeEventPublisher',
    ] as $producer) {
      $container->setDefinition($producer, new Definition());
    }

    $previousConfiguration = FileCacheFactory::getConfiguration();
    $previousPrefix = FileCacheFactory::getPrefix();
    FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => TRUE]);
    FileCacheFactory::setPrefix('aculta_messenger_unit_test');
    try {
      (new AcultaMessengerServiceProvider())->alter($container);
      (new HookCollectorPass())->process($container);
    }
    finally {
      FileCacheFactory::setConfiguration($previousConfiguration);
      FileCacheFactory::setPrefix($previousPrefix);
    }

    $hookList = $container->getParameter('.hook_data')['hook_list'];
    foreach ([
      'aculta_messenger_personal_notices_alter' => 'noticesAlter',
      'aculta_messenger_personal_notice_count_alter' => 'unreadCountAlter',
    ] as $hook => $method) {
      self::assertArrayHasKey($hook, $hookList);
      self::assertArrayHasKey(PersonalNoticeAdapterHooks::class . '::' . $method, $hookList[$hook]);
    }
    $argument = $container->getDefinition(PersonalNoticeAdapterHooks::class)->getArgument(0);
    self::assertInstanceOf(ServiceLocatorArgument::class, $argument);
    self::assertSame(['lms', 'wiki', 'commerce', 'donation'], array_keys($argument->getValues()));
  }

  /**
   * Discovered hooks dispatch through Drupal's ModuleHandler alter API.
   */
  public function testDiscoveredHooksDispatchThroughModuleHandler(): void {
    $container = new ContainerBuilder();
    $modulePath = dirname(__DIR__, 3);
    $container->setParameter('container.modules', [
      'aculta_messenger' => ['pathname' => $modulePath . '/aculta_messenger.info.yml'],
    ]);
    $container->setDefinition(PersonalNoticeAdapterHooks::class, new Definition(PersonalNoticeAdapterHooks::class));

    $previousConfiguration = FileCacheFactory::getConfiguration();
    $previousPrefix = FileCacheFactory::getPrefix();
    FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => TRUE]);
    FileCacheFactory::setPrefix('aculta_messenger_hook_dispatch_test');
    try {
      (new HookCollectorPass())->process($container);
    }
    finally {
      FileCacheFactory::setConfiguration($previousConfiguration);
      FileCacheFactory::setPrefix($previousPrefix);
    }
    $hookData = $container->getParameter('.hook_data');
    self::assertSame(
      [PersonalNoticeAdapterHooks::class . '::noticesAlter' => 'aculta_messenger'],
      $hookData['hook_list']['aculta_messenger_personal_notices_alter'],
    );

    $account = $this->createMock(AccountInterface::class);
    $lms = $this->createMock(PersonalNoticeAdapterInterface::class);
    $lms->expects(self::never())->method('addPersonalNotices');
    $lms->expects(self::once())->method('unreadCount')->with(42)->willReturn(2);
    $wiki = $this->createMock(PersonalNoticeAdapterInterface::class);
    $wiki->expects(self::once())
      ->method('addPersonalNotices')
      ->willReturnCallback(static function (array &$items): void {
        $items[] = 'wiki';
      });
    $wiki->expects(self::once())->method('unreadCount')->with(42)->willReturn(3);
    $dispatcher = new PersonalNoticeAdapterHooks(['lms' => $lms, 'wiki' => $wiki]);
    $callableResolver = $this->createMock(CallableResolver::class);
    $callableResolver->expects(self::exactly(2))
      ->method('getCallableFromDefinition')
      ->willReturnCallback(static function (string $definition) use ($dispatcher): array {
        [$class, $method] = explode('::', $definition, 2);
        self::assertSame(PersonalNoticeAdapterHooks::class, $class);
        return [$dispatcher, $method];
      });

    $keyValue = new KeyValueMemoryFactory();
    foreach ($hookData as $key => $value) {
      $keyValue->get('hook_data')->set($key, $value);
    }
    $modules = [
      'aculta_messenger' => [
        'type' => 'module',
        'pathname' => $modulePath . '/aculta_messenger.info.yml',
        'filename' => NULL,
      ],
    ];
    $moduleHandler = new ModuleHandler(
      DRUPAL_ROOT,
      $modules,
      $keyValue,
      $callableResolver,
      new NullBackend('bootstrap'),
    );
    self::assertTrue($moduleHandler->hasImplementations('aculta_messenger_personal_notices_alter'));
    $items = [];
    $count = 0;
    $uid = 42;
    $filters = ['origem' => 'wiki'];

    $moduleHandler->alter('aculta_messenger_personal_notices', $items, $account, $filters);
    $moduleHandler->alter('aculta_messenger_personal_notice_count', $count, $uid);

    self::assertSame(['wiki'], $items);
    self::assertSame(5, $count);
  }

}
