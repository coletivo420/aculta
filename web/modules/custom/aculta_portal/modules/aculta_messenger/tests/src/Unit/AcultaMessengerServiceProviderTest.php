<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\AcultaMessengerServiceProvider;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
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

}
