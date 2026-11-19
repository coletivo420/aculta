<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_editor\Unit;

use Drupal\aculta_editor\FluentEmojiCatalog;
use Drupal\aculta_editor\Plugin\CKEditor5Plugin\FluentEmoji;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests container-provided paths for the Fluent Emoji CKEditor plugin.
 */
#[Group('aculta_editor')]
final class FluentEmojiPluginTest extends UnitTestCase {

  /**
   * The plugin receives the Drupal application root through its container factory.
   */
  public function testCreateInjectsApplicationRoot(): void {
    $catalog = new FluentEmojiCatalog();
    $themeExtensionList = $this->createMock(ThemeExtensionList::class);
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::exactly(2))
      ->method('get')
      ->willReturnMap([
        ['aculta_editor.fluent_emoji_catalog', $catalog],
        ['extension.list.theme', $themeExtensionList],
      ]);
    $container->expects(self::once())
      ->method('getParameter')
      ->with('app.root')
      ->willReturn('/drupal-root');

    $plugin = FluentEmoji::create($container, [], 'fluent_emoji', []);
    $appRoot = new \ReflectionProperty(FluentEmoji::class, 'appRoot');

    self::assertSame('/drupal-root', $appRoot->getValue($plugin));
  }

}
