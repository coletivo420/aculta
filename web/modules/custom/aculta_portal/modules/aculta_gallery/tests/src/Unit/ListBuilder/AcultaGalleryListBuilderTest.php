<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_gallery\Unit\ListBuilder;

use Drupal\aculta_gallery\ListBuilder\AcultaGalleryListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the administrative gallery list builder service wiring.
 */
#[CoversClass(AcultaGalleryListBuilder::class)]
#[Group('aculta_gallery')]
final class AcultaGalleryListBuilderTest extends UnitTestCase {

  /**
   * Tests construction with Drupal's path alias manager service contract.
   */
  public function testCreateInstanceUsesPathAliasManagerContract(): void {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('id')->willReturn('media_gallery');

    $storage = $this->createMock(EntityStorageInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getStorage')
      ->with('media_gallery')
      ->willReturn($storage);

    $dateFormatter = $this->createMock(DateFormatterInterface::class);
    $aliasManager = $this->createMock(AliasManagerInterface::class);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('date.formatter', $dateFormatter);
    $container->set('path_alias.manager', $aliasManager);

    $builder = AcultaGalleryListBuilder::createInstance($container, $entityType);

    self::assertInstanceOf(AcultaGalleryListBuilder::class, $builder);
  }

}
