<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_portal\Hook\ComponentFallbackHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ensures neutral fallback components preserve their caller attributes.
 */
#[CoversClass(ComponentFallbackHooks::class)]
#[Group('aculta_portal')]
final class ComponentFallbackHooksTest extends UnitTestCase {

  /**
   * Preserves route-level id, behavior markers and design tokens.
   */
  public function testFallbackPreservesAttributeArray(): void {
    $attributes = [
      'id' => 'aculta-studio-profile-editor',
      'data-aculta-focus' => '',
      'data-portal-ui' => 'studio-profile-editor',
    ];
    $variables = [
      'name' => 'profile-editor',
      'props' => [],
      'slots' => ['intro' => ['#plain_text' => 'Perfil']],
      'component' => NULL,
      'attributes' => $attributes,
    ];

    (new ComponentFallbackHooks())->preprocessPortalComponent($variables);

    self::assertSame($attributes, $variables['component']['#attributes']);
    self::assertSame($variables['slots']['intro'], $variables['component']['intro']);
  }

  /**
   * Converts Core's Attribute wrapper to the Render API array format.
   */
  public function testFallbackConvertsAttributeObject(): void {
    $attributes = new Attribute(['id' => 'component', 'data-aculta-focus' => '']);
    $variables = [
      'name' => 'empty-state',
      'props' => [],
      'slots' => [],
      'component' => NULL,
      'attributes' => $attributes,
    ];

    (new ComponentFallbackHooks())->preprocessPortalComponent($variables);

    self::assertSame($attributes->toArray(), $variables['component']['#attributes']);
  }

}
