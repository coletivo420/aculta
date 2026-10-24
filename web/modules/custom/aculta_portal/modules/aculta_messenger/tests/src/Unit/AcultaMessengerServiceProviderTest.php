<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\AcultaMessengerServiceProvider;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Definition;

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

}
