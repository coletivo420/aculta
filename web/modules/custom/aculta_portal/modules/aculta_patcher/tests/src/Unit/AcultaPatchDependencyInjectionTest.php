<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_patcher\Unit;

use Drupal\aculta_patcher\Entity\AcultaPatchListBuilder;
use Drupal\aculta_patcher\Form\AcultaPatchStatusForm;
use Drupal\aculta_patcher\PatchManifestImporter;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests container construction for the patch list builder and status form.
 */
final class AcultaPatchDependencyInjectionTest extends UnitTestCase {

  public function testListBuilderReceivesImporterAndStorage(): void {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('id')->willReturn('aculta_patch');
    $storage = $this->createMock(EntityStorageInterface::class);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::once())->method('getStorage')->with('aculta_patch')->willReturn($storage);
    $importer = (new \ReflectionClass(PatchManifestImporter::class))->newInstanceWithoutConstructor();
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    $container->set('aculta_patcher.importer', $importer);

    $builder = AcultaPatchListBuilder::createInstance($container, $entityType);
    $property = new \ReflectionProperty(AcultaPatchListBuilder::class, 'importer');

    self::assertInstanceOf(AcultaPatchListBuilder::class, $builder);
    self::assertSame($importer, $property->getValue($builder));
  }

  public function testStatusFormReceivesEntityTypeManager(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);

    $form = AcultaPatchStatusForm::create($container);
    $property = new \ReflectionProperty(AcultaPatchStatusForm::class, 'entityTypeManager');

    self::assertSame($manager, $property->getValue($form));
  }

}
