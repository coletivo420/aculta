<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Controller\CertificateVerificationController;
use Drupal\aculta_lms\Hook\LearningDashboardHooks;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\TrainingManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Tests fail-closed access and isolated empty states for LMS dashboards. */
#[Group('aculta_lms')]
final class LearningDashboardManagerTest extends UnitTestCase {

  /**
   * Verifies the student dashboard stays scoped to the supplied account.
   */
  public function testStudentDashboardContainsOnlyTheSuppliedAccountScope(): void {
    $certificates = $this->createMock(CourseCertificateManagerInterface::class);
    $certificates->expects(self::once())->method('loadForStudent')->with(42)->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $manager = new LearningDashboardManager(
      NULL,
      $certificates,
      $entityTypeManager,
      $this->createMock(TrainingManager::class),
    );

    $dashboard = $manager->studentDashboard(new UserSession(['uid' => 42]));

    self::assertSame([], $dashboard['courses']);
    self::assertSame([], $dashboard['certificates']);
    self::assertContains('user', $dashboard['cache']->getCacheContexts());
  }

  /**
   * Verifies an unrelated or orphan class has no educator dashboard.
   */
  public function testClassWithoutLmsCourseRelationshipIsDeniedAndHasNoMetrics(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::exactly(2))->method('loadByProperties')->with([
      'entity_id' => 'class-1',
      'plugin_id' => 'lms_classes',
    ])->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::exactly(2))->method('getStorage')->with('group_relationship')->willReturn($storage);
    $manager = new LearningDashboardManager(
      NULL,
      $this->createMock(CourseCertificateManagerInterface::class),
      $entityTypeManager,
      $this->createMock(TrainingManager::class),
    );
    $class = $this->createMock(GroupInterface::class);
    $class->method('bundle')->willReturn('lms_class');
    $class->method('id')->willReturn('class-1');
    $class->method('getCacheContexts')->willReturn([]);
    $class->method('getCacheTags')->willReturn([]);
    $class->method('getCacheMaxAge')->willReturn(-1);
    $account = new UserSession(['uid' => 7, 'permissions' => ['administer lms']]);

    self::assertFalse($manager->classDashboardAccess($class, $account)->isAllowed());
    self::assertNull($manager->classDashboard($class, $account));
  }

  /**
   * Verifies only authenticated accounts receive the LMS dashboard link. */
  public function testAccountNavigationHidesDashboardFromAnonymousAccounts(): void {
    $hooks = new LearningDashboardHooks();
    $sections = [];
    $hooks->addStudentDashboard($sections, new UserSession(['uid' => 0]));
    self::assertArrayNotHasKey('aculta_lms.student_dashboard', $sections);

    $hooks->addStudentDashboard($sections, new UserSession(['uid' => 42]));
    self::assertSame('aculta_lms.student_dashboard', $sections['aculta_lms.student_dashboard']['route']);
    self::assertSame('mortarboard', $sections['aculta_lms.student_dashboard']['icon']);
  }

  /**
   * Ensures missing PDF tooling produces a controlled response.
   */
  public function testCertificatePdfRouteFailsClosedWhenPrintEngineIsMissing(): void {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      static fn(): TranslatableMarkup => new TranslatableMarkup('PDF indisponível.'),
    );
    $controller = new CertificateVerificationController(
      $this->createMock(CourseCertificateManagerInterface::class),
      NULL,
      $this->createMock(DateFormatterInterface::class),
      $translation,
      NULL,
    );

    $this->expectException(ServiceUnavailableHttpException::class);
    $controller->pdf('opaque-verification-code');
  }

}
