<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\NoticePaginationNavigationBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the accessible navigation landmark for notice pagination. */
#[CoversClass(NoticePaginationNavigationBuilder::class)]
#[Group('aculta_messenger')]
final class NoticePaginationNavigationBuilderTest extends UnitTestCase {

  /**
   * Pagination links receive a named navigation landmark.
   */
  public function testBuildAddsNamedNavigationLandmark(): void {
    $links = [['#markup' => 'Avisos seguintes: Wiki']];

    $build = NoticePaginationNavigationBuilder::build($links, 'Paginação dos avisos: Wiki');

    self::assertSame('navigation', $build['#attributes']['role']);
    self::assertSame('Paginação dos avisos: Wiki', $build['#attributes']['aria-label']);
    self::assertSame($links, $build['links']['#items']);
    self::assertSame('item_list', $build['links']['#theme']);
  }

  /**
   * Empty page controls do not produce an empty navigation landmark.
   */
  public function testBuildOmitsNavigationWithoutLinks(): void {
    self::assertSame([], NoticePaginationNavigationBuilder::build([], 'Paginação dos avisos'));
  }

}
