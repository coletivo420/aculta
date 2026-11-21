<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_editor\Unit;

use Drupal\aculta_editor\FluentEmojiCatalog;
use Drupal\aculta_editor\Service\FluentEmojiIconAdapter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Valida o adaptador FluentEmojiIconAdapter entre UI Icons e catálogo.
 */
#[CoversClass(FluentEmojiIconAdapter::class)]
#[Group('aculta_editor')]
class FluentEmojiIconAdapterTest extends UnitTestCase {

  private FluentEmojiIconAdapter $adapter;

  protected function setUp(): void {
    parent::setUp();
    $catalog = new FluentEmojiCatalog();
    $this->adapter = new FluentEmojiIconAdapter($catalog);
  }

  public function testToAndFromIconId(): void {
    $this->assertSame('aculta_fluent_emoji:herb', $this->adapter->toIconId('herb'));
    $this->assertSame('aculta_fluent_emoji:herb', $this->adapter->toIconId('aculta_fluent_emoji:herb'));
    $this->assertNull($this->adapter->toIconId('invalid-icon-id-xyz'));

    $this->assertSame('herb', $this->adapter->fromIconId('aculta_fluent_emoji:herb'));
    $this->assertSame('herb', $this->adapter->fromIconId('herb'));
    $this->assertNull($this->adapter->fromIconId('other_pack:herb'));
  }

  public function testGetUnicode(): void {
    $this->assertSame('🌿', $this->adapter->getUnicode('herb'));
    $this->assertSame('👎🏽', $this->adapter->getUnicode('thumbs-down-medium'));
    $this->assertSame('🌿', $this->adapter->getUnicode('aculta_fluent_emoji:herb'));
    $this->assertSame('✊🏽', $this->adapter->getUnicode('raised-fist-medium'));
    $this->assertSame('👎', $this->adapter->getUnicode('thumbs-down'));
    $this->assertNull($this->adapter->getUnicode('non_existent'));
  }

  public function testIsValid(): void {
    $this->assertTrue($this->adapter->isValid('herb'));
    $this->assertTrue($this->adapter->isValid('thumbs-down-medium'));
    $this->assertTrue($this->adapter->isValid('aculta_fluent_emoji:thumbs-down'));
    $this->assertTrue($this->adapter->isValid('aculta_fluent_emoji:herb'));
    $this->assertFalse($this->adapter->isValid('aculta_phosphor_regular:heart'));
    $this->assertFalse($this->adapter->isValid('../../../etc/passwd'));
  }

  public function testCategoriesAndSearch(): void {
    $categories = $this->adapter->getCategories();
    $this->assertArrayHasKey('cultivo', $categories);
    $this->assertArrayHasKey('herb', $categories['cultivo']);
    $herb = $categories['cultivo']['herb'];
    if (!is_array($herb)) {
      self::fail('Ícone herb ausente.');
    }
    self::assertSame('aculta_fluent_emoji:herb', $herb['icon_full_id']);

    $searchResults = $this->adapter->search('maconha');
    $this->assertArrayHasKey('herb', $searchResults);

    $searchResultsDirect = $this->adapter->search('Erva');
    $this->assertArrayHasKey('herb', $searchResultsDirect);
    $this->assertArrayHasKey('thumbs-down-medium', $this->adapter->search('mão parda'));

    $negativeResults = $this->adapter->search('descurtir');
    $this->assertArrayHasKey('thumbs-down', $negativeResults);
    self::assertSame('aculta_fluent_emoji:thumbs-down', $this->adapter->toIconId('thumbs-down'));
  }

}
