<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\AcultaMessengerServiceProvider;
use Drupal\aculta_messenger\Hook\PersonalNoticeAdapterHooks;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Drupal\Core\Hook\HookCollectorPass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;

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

}
