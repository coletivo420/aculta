<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Learning\LearnerDashboardComposer;
use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers the learner dashboard's theme-neutral Studio fidelity scope.
 */
#[CoversClass(LearnerDashboardComposer::class)]
#[Group('aculta_studio')]
final class LearnerDashboardComposerTest extends UnitTestCase {

  public function testDashboardRootCarriesOnlyTheSemanticStudioScope(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $certificates = $this->createMock(CourseCertificateManagerInterface::class);
    $certificates->expects(self::once())->method('loadForStudent')->willReturn([]);
    $dashboards = new LearningDashboardManager(
      NULL,
      $certificates,
      $this->createMock(EntityTypeManagerInterface::class),
      NULL,
    );
    $composer = new LearnerDashboardComposer(
      $dashboards,
      $account,
      $this->createMock(DateFormatterInterface::class),
      $this->getStringTranslationStub(),
    );

    $build = $composer->build();

    self::assertSame(['data-portal-ui' => 'studio-fidelity-scope'], $build['#attributes']);
    self::assertArrayHasKey('#cache', $build);
  }

}
