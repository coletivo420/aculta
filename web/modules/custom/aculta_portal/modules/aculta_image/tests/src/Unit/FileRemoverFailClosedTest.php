<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\FileRemover;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests fail-closed deletion when references exist or cannot be verified.
 */
#[Group('aculta_image')]
final class FileRemoverFailClosedTest extends UnitTestCase {

  /**
   * Does not delete a file reported in use by File API.
   */
  public function testFileApiUsagePreventsDeletion(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn('42');
    $usage = $this->createMock(FileUsageInterface::class);
    $usage->expects(self::once())
      ->method('listUsage')
      ->with($file)
      ->willReturn(['node' => ['7' => ['count' => 1]]]);
    $database = $this->createMock(Connection::class);
    $database->expects(self::never())->method('select');
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects(self::once())->method('notice');

    $remover = $this->remover($usage, $database, $entities, $logger);

    self::assertFalse($remover->delete($file, 'test_in_use'));
  }

  /**
   * Does not delete a file referenced by Entity Usage.
   */
  public function testEntityUsagePreventsDeletion(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn('42');
    $usage = $this->emptyFileUsage($file);
    $result = $this->createMock(StatementInterface::class);
    $result->method('fetchField')->willReturn('42');
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($result);
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('select')->with('entity_usage', 'eu')->willReturn($query);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects(self::once())->method('notice');

    $remover = $this->remover($usage, $database, $entities, $logger);

    self::assertFalse($remover->delete($file, 'test_entity_usage'));
  }

  /**
   * Propagates reference-check failures before any file storage deletion.
   */
  public function testReferenceCheckFailureDoesNotDelete(): void {
    $file = $this->createMock(FileInterface::class);
    $usage = $this->emptyFileUsage($file);
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())
      ->method('select')
      ->with('entity_usage', 'eu')
      ->willThrowException(new \RuntimeException('reference storage unavailable'));
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $logger = $this->createMock(LoggerChannelInterface::class);

    $remover = $this->remover($usage, $database, $entities, $logger);

    $this->expectException(\RuntimeException::class);
    $remover->delete($file, 'test_unverifiable');
  }

  /**
   * Creates a File API usage mock that reports no references.
   */
  private function emptyFileUsage(FileInterface $file): FileUsageInterface {
    $usage = $this->createMock(FileUsageInterface::class);
    $usage->expects(self::once())->method('listUsage')->with($file)->willReturn([]);
    return $usage;
  }

  /**
   * Creates the remover with the supplied reference and deletion boundaries.
   */
  private function remover(
    FileUsageInterface $usage,
    Connection $database,
    EntityTypeManagerInterface $entities,
    LoggerChannelInterface $logger,
  ): FileRemover {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('aculta_image')->willReturn($logger);

    return new FileRemover(
      $usage,
      $entities,
      $database,
      $this->createMock(FileSystemInterface::class),
      $logger_factory,
    );
  }

}
