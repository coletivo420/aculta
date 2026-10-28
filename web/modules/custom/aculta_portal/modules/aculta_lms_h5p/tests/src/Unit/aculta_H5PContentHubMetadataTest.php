<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the Content Hub metadata methods of the H5P framework adapter.
 */
#[CoversClass(aculta_H5PDrupal::class)]
final class aculta_H5PContentHubMetadataTest extends UnitTestCase {

  /**
   * Stores and reads the metadata payload per language.
   */
  public function testStoresAndReadsMetadataCache(): void {
    $adapter = $this->adapterWithState($this->inMemoryState());

    self::assertNull(
      $adapter->getContentHubMetadataCache('pt-br'),
      'An empty cache returns an explicit NULL instead of a silent stub result.',
    );

    $payload = '{"libraries":[]}';
    self::assertSame($payload, $adapter->replaceContentHubMetadataCache($payload, 'pt-br'));
    self::assertSame($payload, $adapter->getContentHubMetadataCache('pt-br'));
    self::assertNull($adapter->getContentHubMetadataCache('en'));

    $metadata = new class implements \JsonSerializable {

      /**
       * {@inheritdoc}
       */
      public function jsonSerialize(): array {
        return ['libraries' => [['name' => 'H5P.Example']]];
      }

    };
    self::assertSame(
      '{"libraries":[{"name":"H5P.Example"}]}',
      $adapter->replaceContentHubMetadataCache($metadata, 'EN'),
    );
    self::assertSame(
      '{"libraries":[{"name":"H5P.Example"}]}',
      $adapter->getContentHubMetadataCache('EN'),
    );
  }

  /**
   * Stores an RFC 7231 marker and clears it with NULL.
   */
  public function testStoresAndClearsCheckedTimestamp(): void {
    $adapter = $this->adapterWithState($this->inMemoryState());
    $timestamp = 1_792_800_000;
    $expected = gmdate('D, d M Y H:i:s \G\M\T', $timestamp);

    self::assertTrue($adapter->setContentHubMetadataChecked($timestamp, 'pt-br'));
    self::assertSame($expected, $adapter->getContentHubMetadataChecked('pt-br'));
    self::assertTrue($adapter->setContentHubMetadataChecked(NULL, 'pt-br'));
    self::assertNull($adapter->getContentHubMetadataChecked('pt-br'));
  }

  /**
   * Rejects an unparseable marker instead of returning NULL silently.
   */
  public function testRejectsInvalidCheckedTimestamp(): void {
    $adapter = $this->adapterWithState($this->inMemoryState());

    self::assertFalse($adapter->setContentHubMetadataChecked('not-a-date', 'en'));
    self::assertNull($adapter->getContentHubMetadataChecked('en'));
  }

  /**
   * Creates an in-memory State API double.
   */
  private function inMemoryState(): StateInterface {
    $values = [];
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(
      static function (string $key, mixed $default = NULL) use (&$values): mixed {
        return $values[$key] ?? $default;
      },
    );
    $state->method('set')->willReturnCallback(
      static function (string $key, mixed $value) use (&$values): void {
        $values[$key] = $value;
      },
    );
    $state->method('delete')->willReturnCallback(
      static function (string $key) use (&$values): void {
        unset($values[$key]);
      },
    );
    return $state;
  }

  /**
   * Creates the adapter without its constructor and injects the state double.
   */
  private function adapterWithState(StateInterface $state): aculta_H5PDrupal {
    $adapter = (new \ReflectionClass(aculta_H5PDrupal::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(aculta_H5PDrupal::class, 'state'))->setValue($adapter, $state);
    return $adapter;
  }

}
