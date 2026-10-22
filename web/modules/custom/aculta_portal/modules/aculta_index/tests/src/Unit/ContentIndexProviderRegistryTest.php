<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\Tests\UnitTestCase;

/**
 * Tests provider registration and stable identity.
 *
 * @group aculta_index
 */
final class ContentIndexProviderRegistryTest extends UnitTestCase {

  /**
   * Tests providers register by stable ID.
   */
  public function testRegistersProvidersByStableId(): void {
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $provider->method('getProviderId')->willReturn('magazine');
    $provider->method('getSupportedEntityTypes')->willReturn(['node' => ['revista']]);

    $registry = new ContentIndexProviderRegistry([$provider]);

    self::assertTrue($registry->has('magazine'));
    self::assertSame($provider, $registry->get('magazine'));
    self::assertSame(['magazine' => $provider], $registry->all());
  }

  /**
   * Tests duplicate provider IDs are rejected.
   */
  public function testRejectsDuplicateProviderIds(): void {
    $first = $this->provider('magazine');
    $second = $this->provider('magazine');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Duplicate ACULTA Content Index provider ID: magazine.');
    new ContentIndexProviderRegistry([$first, $second]);
  }

  /**
   * Tests invalid provider IDs are rejected.
   */
  public function testRejectsInvalidProviderIds(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Index provider IDs must be lowercase machine names.');
    new ContentIndexProviderRegistry([$this->provider('Magazine')]);
  }

  /**
   * Tests invalid supported bundle declarations are rejected.
   */
  public function testInvalidSupportedEntityTypesAreRejected(): void {
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $provider->method('getProviderId')->willReturn('magazine');
    $provider->method('getSupportedEntityTypes')->willReturn(['node' => []]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Provider magazine declares an invalid entity type or bundle list.');
    new ContentIndexProviderRegistry([$provider]);
  }

  /**
   * Tests an entity bundle has exactly one projection owner.
   */
  public function testRejectsOverlappingBundleOwners(): void {
    $first = $this->createMock(ContentIndexProviderInterface::class);
    $first->method('getProviderId')->willReturn('magazine');
    $first->method('getSupportedEntityTypes')->willReturn(['node' => ['revista']]);
    $second = $this->createMock(ContentIndexProviderInterface::class);
    $second->method('getProviderId')->willReturn('custom');
    $second->method('getSupportedEntityTypes')->willReturn(['node' => ['revista']]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Entity bundle node:revista is declared by both providers magazine and custom.');
    new ContentIndexProviderRegistry([$first, $second]);
  }

  /**
   * Tests unknown providers fail closed.
   */
  public function testUnknownProviderFailsClosed(): void {
    $registry = new ContentIndexProviderRegistry();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown ACULTA Content Index provider: missing.');
    $registry->get('missing');
  }

  /**
   * Creates a provider mock with the given ID.
   */
  private function provider(string $id): ContentIndexProviderInterface {
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $provider->method('getProviderId')->willReturn($id);
    $provider->method('getSupportedEntityTypes')->willReturn([]);
    return $provider;
  }

}
