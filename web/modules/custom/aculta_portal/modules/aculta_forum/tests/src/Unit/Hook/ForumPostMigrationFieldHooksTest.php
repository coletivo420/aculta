<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\aculta_forum\Hook\ForumPostMigrationFieldHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests migration metadata field definitions for public Forum posts.
 *
 * @coversDefaultClass \Drupal\aculta_forum\Hook\ForumPostMigrationFieldHooks
 * @group aculta_forum
 */
#[CoversClass(ForumPostMigrationFieldHooks::class)]
#[Group('aculta_forum')]
final class ForumPostMigrationFieldHooksTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $field_type_manager = $this->createMock(FieldTypePluginManagerInterface::class);
    $field_type_manager->method('getDefaultStorageSettings')->willReturn([]);
    $field_type_manager->method('getDefaultFieldSettings')->willReturn([]);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.field.field_type', $field_type_manager);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * The historical author field targets the legacy author entity and revisions.
   */
  public function testLegacyAuthorFieldIsRevisionableAndReadOnly(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('aculta_forum_post');

    $fields = (new ForumPostMigrationFieldHooks())->entityBaseFieldInfo($entity_type);

    self::assertArrayHasKey('legacy_author', $fields);
    self::assertSame('entity_reference', $fields['legacy_author']->getType());
    self::assertSame('aculta_legado_autor', $fields['legacy_author']->getSetting('target_type'));
    self::assertTrue($fields['legacy_author']->isRevisionable());
    self::assertTrue($fields['legacy_author']->isReadOnly());
  }

  /**
   * This module does not add historical-author fields to other entity types.
   */
  public function testOtherEntityTypesAreUnaffected(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('comment');

    self::assertSame([], (new ForumPostMigrationFieldHooks())->entityBaseFieldInfo($entity_type));
  }

}
