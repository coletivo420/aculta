<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\aculta420\Hook\ThemeHooks;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ACULTA420 theme attachment behavior for shared Messenger components.
 */
#[CoversClass(ThemeHooks::class)]
#[Group('aculta420')]
final class ThemeHooksTest extends UnitTestCase {

  /**
   * Attaches notice grouping styles wherever the shared header can render.
   */
  public function testAttachesMessengerNoticeStylesToEveryPage(): void {
    $hooks = new ThemeHooks(
      $this->createMock(PathMatcherInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(RouteMatchInterface::class),
      $this->createMock(ExtensionList::class),
    );
    $attachments = [];

    $hooks->attachMessengerNoticeStyles($attachments);

    self::assertSame(
      ['aculta420/messenger-notices'],
      $attachments['#attached']['library'],
    );
  }

}
