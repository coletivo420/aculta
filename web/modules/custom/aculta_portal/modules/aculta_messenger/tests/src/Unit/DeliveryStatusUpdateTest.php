<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests idempotent schema configuration updates for Mailer delivery statuses.
 */
#[CoversNothing]
#[Group('aculta_messenger')]
final class DeliveryStatusUpdateTest extends UnitTestCase {

  /**
   * The update appends the permanent state once and retains existing options.
   */
  public function testPermanentFailureValueIsAddedIdempotently(): void {
    $values = [
      ['value' => 'pending', 'label' => 'Pendente'],
      ['value' => 'sent', 'label' => 'Enviado'],
      ['value' => 'failed', 'label' => 'Falhou'],
      ['value' => 'cancelled', 'label' => 'Cancelado'],
    ];
    $fieldStorage = $this->createMock(FieldStorageConfigInterface::class);
    $fieldStorage->method('getSetting')->with('allowed_values')->willReturnCallback(static function () use (&$values): array {
      return $values;
    });
    $fieldStorage->expects(self::once())
      ->method('setSetting')
      ->with('allowed_values', self::callback(static function (array $updated) use (&$values): bool {
        $values = $updated;
        return TRUE;
      }))
      ->willReturnSelf();
    $fieldStorage->expects(self::once())->method('save')->willReturn(1);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::exactly(2))
      ->method('load')
      ->with('message.field_aculta_msg_delivery')
      ->willReturn($fieldStorage);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('field_storage_config')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entityTypeManager);

    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10006();
      aculta_messenger_update_10006();
    }
    finally {
      \Drupal::unsetContainer();
    }

    self::assertSame([
      ['value' => 'pending', 'label' => 'Pendente'],
      ['value' => 'sent', 'label' => 'Enviado'],
      ['value' => 'failed', 'label' => 'Falhou'],
      ['value' => 'cancelled', 'label' => 'Cancelado'],
      ['value' => 'failed_permanent', 'label' => 'Falha permanente'],
    ], $values);
  }

}
