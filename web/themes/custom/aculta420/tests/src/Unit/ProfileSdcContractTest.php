<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Static SDC contracts for the future private own-profile Studio presenter.
 *
 * Runtime discovery/rendering, User ownership and cache remain integration gates.
 */
#[CoversNothing]
#[Group('aculta420')]
final class ProfileSdcContractTest extends UnitTestCase {

  private const COMPONENTS = [
    'patterns/profile-editor' => ['intro', 'identity', 'biography', 'social', 'actions'],
    'content/profile-identity' => ['avatar', 'public_link', 'actions'],
    'content/profile-bio' => ['body', 'actions'],
    'content/profile-social' => ['items', 'actions'],
  ];

  /**
   * Every visual component has strict props, slots, CSS and static stories.
   */
  public function testMetadataAssetsAndStories(): void {
    foreach (self::COMPONENTS as $directory => $slots) {
      $path = $this->root() . '/components/' . $directory;
      $name = basename($directory);
      $metadata = Yaml::decode((string) file_get_contents($path . '/' . $name . '.component.yml'));
      self::assertSame('experimental', $metadata['status']);
      self::assertSame('Identity', $metadata['group']);
      self::assertSame('object', $metadata['props']['type']);
      self::assertIsArray($metadata['props']['properties']);
      self::assertSame($slots, array_keys($metadata['slots']));
      self::assertArrayHasKey($name . '.css', $metadata['assets']['css']['component']);
      self::assertFileExists($path . '/' . $name . '.twig');
      self::assertFileExists($path . '/' . $name . '.css');

      $stories = glob($path . '/*.story.yml');
      self::assertIsArray($stories);
      self::assertGreaterThanOrEqual(2, count($stories));
      foreach ($stories as $story) {
        $fixture = Yaml::decode((string) file_get_contents($story));
        self::assertSame('aculta420:' . $name, $fixture['component']);
        self::assertArrayHasKey('name', $fixture);
        self::assertArrayHasKey('description', $fixture);
      }
    }
  }

  /**
   * Named props must be strings; renderables stay in slots, never props.
   */
  public function testPropsAndSlotsHaveAnExplicitBoundary(): void {
    foreach (self::COMPONENTS as $directory => $slots) {
      $name = basename($directory);
      $metadata = Yaml::decode((string) file_get_contents($this->root() . '/components/' . $directory . '/' . $name . '.component.yml'));
      foreach ($metadata['props']['properties'] as $property => $definition) {
        self::assertContains($definition['type'], ['string', ['string', 'null']],
          sprintf('Only scalar text props are permitted: %s.%s.', $name, $property));
      }
      foreach ($slots as $slot) {
        self::assertArrayHasKey('description', $metadata['slots'][$slot]);
      }
    }
  }

  /**
   * SDC templates cannot load data, decide ownership or invent form handlers.
   */
  public function testTwigIsPresentationOnly(): void {
    foreach (self::COMPONENTS as $directory => $slots) {
      $name = basename($directory);
      $template = (string) file_get_contents($this->root() . '/components/' . $directory . '/' . $name . '.twig');
      $css = (string) file_get_contents($this->root() . '/components/' . $directory . '/' . $name . '.css');
      self::assertStringContainsString('aculta-profile-', $template);
      self::assertStringContainsString('var(--aculta-', $css);
      foreach (['drupal_entity(', 'drupal_view(', 'drupal_field(', '|raw', '<form', 'create_url(', 'path('] as $forbidden) {
        self::assertStringNotContainsString($forbidden, $template,
          sprintf('%s cannot contain "%s".', $name, $forbidden));
      }
    }
  }

  private function root(): string {
    return dirname(__DIR__, 3);
  }

}
