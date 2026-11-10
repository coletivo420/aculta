<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_editor\Kernel;

use Drupal\aculta_editor\Plugin\Filter\FluentEmojiFilter;
use Drupal\aculta_editor\Service\FluentEmojiAssetResolverInterface;
use Drupal\filter\FilterPluginCollection;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Valida a criação e resolução de assets do FluentEmojiFilter via container.
 */
#[CoversClass(FluentEmojiFilter::class)]
#[Group('aculta_editor')]
final class FluentEmojiFilterContainerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'filter',
    'aculta_editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'filter']);
  }

  /**
   * O plugin manager cria o filtro resolvendo FluentEmojiAssetResolverInterface.
   */
  public function testPluginCreationFromContainer(): void {
    $this->assertTrue($this->container->has('aculta_editor.fluent_emoji_asset_resolver'));
    $resolver = $this->container->get('aculta_editor.fluent_emoji_asset_resolver');
    $this->assertInstanceOf(FluentEmojiAssetResolverInterface::class, $resolver);

    /** @var \Drupal\filter\FilterPluginManager $filterManager */
    $filterManager = $this->container->get('plugin.manager.filter');
    $plugin = $filterManager->createInstance('fluent_emoji');

    $this->assertInstanceOf(FluentEmojiFilter::class, $plugin);

    $transformed = $plugin->transform('<p>Texto com 🌿</p><script>console.log("🌿");</script>');
    $this->assertStringContainsString('herb.svg', $transformed);
    $this->assertStringContainsString('<script>console.log("🌿");</script>', $transformed);
  }

}
