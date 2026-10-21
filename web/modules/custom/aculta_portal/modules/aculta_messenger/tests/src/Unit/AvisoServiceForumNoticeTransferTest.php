<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests transferring complete response notice aggregates between topics.
 */
final class AvisoServiceForumNoticeTransferTest extends UnitTestCase {

  /**
   * Merges the source aggregate into an existing destination record.
   */
  public function testTransfersWholeAggregateAndDeletesSource(): void {
    $sourceValues = [
      'uid' => 7,
      'contador_site' => 3,
      'contador_email' => 2,
      'ultimo_comentario' => 42,
    ];
    $targetValues = [
      'contador_site' => 5,
      'contador_email' => 4,
      'ultimo_comentario' => 40,
    ];
    $source = $this->notice($sourceValues, FALSE);
    $target = $this->notice($targetValues, TRUE);
    $source->expects(self::once())->method('delete');
    $target->expects(self::once())->method('save');

    $sourceQuery = $this->query([101]);
    $targetQuery = $this->query([202]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->expects(self::exactly(2))->method('getQuery')
      ->willReturnOnConsecutiveCalls($sourceQuery, $targetQuery);
    $noticeStorage->expects(self::once())->method('loadMultiple')->with([101])
      ->willReturn([101 => $source]);
    $noticeStorage->expects(self::once())->method('load')->with(202)->willReturn($target);

    $destination = $this->createMock(NodeInterface::class);
    $destination->method('isPublished')->willReturn(TRUE);
    $destination->method('label')->willReturn('Tópico destino');
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->expects(self::once())->method('load')->with(20)->willReturn($destination);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['aculta_aviso', $noticeStorage],
    ]);
    $policy = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $policy->method('supportsTopic')->willReturn(TRUE);
    $policy->method('canViewTopic')->with($destination, 7)->willReturn(TRUE);

    $service = new AvisoService($entityTypeManager, $this->createMock(FlagServiceInterface::class), $policy);

    self::assertSame(1, $service->transferTopicResponseNotices(10, 20));
    self::assertSame(8, $targetValues['contador_site']);
    self::assertSame(6, $targetValues['contador_email']);
    self::assertSame(42, $targetValues['ultimo_comentario']);
  }

  /**
   * Creates a notice entity mock with field values.
   *
   * @param array<string, int> $values
   *   Mutable notice field values.
   * @param bool $expectSet
   *   Whether the test expects the entity to be updated.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The configured notice entity mock.
   */
  private function notice(array &$values, bool $expectSet): ContentEntityInterface {
    $notice = $this->createMock(ContentEntityInterface::class);
    $notice->method('get')->willReturnCallback(static function (string $name) use (&$values): FieldItemListInterface {
      return new TransferTestFieldItemList($values[$name] ?? 0);
    });
    $set = $expectSet ? $notice->expects(self::exactly(4)) : $notice->expects(self::never());
    $set->method('set')->willReturnCallback(
      static function (string $name, mixed $value) use (&$values): void {
        $values[$name] = is_numeric($value) ? (int) $value : $value;
      },
    );
    return $notice;
  }

  /**
   * Returns a fluent entity query mock.
   */
  private function query(array $ids): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    foreach (['accessCheck', 'condition', 'range'] as $method) {
      $query->method($method)->willReturnSelf();
    }
    $query->method('execute')->willReturn($ids);
    return $query;
  }

}

/**
 * Minimal field list for values read by AvisoService.
 */
final class TransferTestFieldItemList extends FieldItemList {

  public function __construct(private readonly mixed $testValue) {}

  /**
   * {@inheritdoc}
   */
  public function __get($property_name): mixed {
    return $this->testValue;
  }

}
