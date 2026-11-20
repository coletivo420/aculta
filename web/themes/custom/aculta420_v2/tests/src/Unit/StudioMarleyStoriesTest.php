<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Ensures Marley UI Patterns stories compose existing SDCs safely.
 *
 * These are structural tests; runtime ACL and browser acceptance remain
 * separate and require an isolated local environment.
 */
final class StudioMarleyStoriesTest extends UnitTestCase {

  private string $themeDir;

  protected function setUp(): void {
    parent::setUp();
    $this->themeDir = dirname(__DIR__, 3);
  }

  /**
   * Checks the new SDC's stable slot contract and wrapper composition.
   */
  public function testMarleyWorkspaceComponent(): void {
    $folder = $this->themeDir . '/components/studio-marley-workspace/';
    $meta = Yaml::parseFile($folder . 'studio-marley-workspace.component.yml');
    self::assertSame('Studio', $meta['group']);
    self::assertSame(['title', 'area'], $meta['props']['required']);
    foreach (['navigation', 'actions', 'summary', 'filters', 'primary', 'aside', 'footer'] as $slot) {
      self::assertArrayHasKey($slot, $meta['slots']);
    }
    $twig = (string) file_get_contents($folder . 'studio-marley-workspace.twig');
    self::assertStringContainsString("include('aculta420_v2:studio-shell'", $twig);
    self::assertStringContainsString('aria-labelledby=', $twig);
    self::assertStringNotContainsString('<main', $twig, 'The Studio page already owns the document main landmark.');
    self::assertFileExists($folder . 'studio-marley-workspace.css');
  }

  /**
   * Validates all Marley stories are discoverable and demonstrative only.
   */
  public function testMarleyPageStories(): void {
    $dir = $this->themeDir . '/components/studio-marley-workspace/';
    $names = ['forum-fila', 'forum-caso', 'forum-vazia', 'revista-fluxo', 'galerias'];
    $ids = [];
    foreach ($names as $name) {
      $file = $dir . 'studio-marley-workspace.' . $name . '.story.yml';
      self::assertFileExists($file);
      $story = Yaml::parseFile($file);
      self::assertSame('aculta420_v2:studio-marley-workspace', $story['component']);
      self::assertContains($story['props']['area'], ['forum', 'revista', 'galerias']);
      self::assertNotEmpty($story['slots']['primary']['template']);
      self::assertSame('inline_template', $story['slots']['primary']['type']);
      self::assertStringContainsString('ações desativadas', $story['props']['preview_note']);
      $ids[] = $story['props']['dom_id'];
      $raw = (string) file_get_contents($file);
      self::assertStringNotContainsString('/painel-administrativo/', $raw);
      self::assertStringNotContainsString('/admin/', $raw);
      self::assertStringNotContainsString('type="submit"', $raw);
    }
    self::assertCount(count($ids), array_unique($ids));
  }

  /**
   * The Discourse-like controls are previews, not live mutation endpoints.
   */
  public function testModerationPopupStoriesAreDisabled(): void {
    $folder = $this->themeDir . '/components/popup/';
    foreach (['marley-moderar-topico', 'marley-decidir-denuncia', 'marley-moderar-resposta'] as $name) {
      $story = Yaml::parseFile($folder . 'popup.' . $name . '.story.yml');
      self::assertSame('aculta420_v2:popup', $story['component']);
      self::assertSame('inline_template', $story['slots']['content']['type']);
      $template = $story['slots']['content']['template'];
      self::assertStringContainsString("aculta420_v2:moderation-submit", $template);
      self::assertStringContainsString("disabled: 'disabled'", $template);
      self::assertStringNotContainsString('/painel-administrativo/', $template);
      self::assertStringNotContainsString('method="post"', $template);
    }
  }

  /**
   * Reuses the existing table/filter/metric primitives and local icon pack.
   */
  public function testCompositionsAndIconsExist(): void {
    foreach ([
      'components/studio-listing-table/studio-listing-table.marley-queue.story.yml',
      'components/filter-chips/filter-chips.marley-review.story.yml',
      'components/statistics-moderation-summary/statistics-moderation-summary.marley-forum.story.yml',
    ] as $path) {
      self::assertIsArray(Yaml::parseFile($this->themeDir . '/' . $path));
    }

    foreach ([
      'flag', 'shield-check', 'check-circle', 'eye-slash',
      'push-pin-simple', 'lock', 'archive', 'users', 'prohibit',
      'arrows-left-right', 'arrows-split', 'git-merge',
      'clock-countdown', 'chat-circle-text', 'article', 'images-square',
      'arrows-clockwise', 'list-checks', 'pencil-simple',
      'plus', 'arrow-u-up-left', 'magnifying-glass',
    ] as $icon) {
      self::assertFileExists($this->themeDir . '/assets/icons/phosphor/regular/' . $icon . '.svg');
    }
  }

}
