<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_learning_contracts\Kernel;

use Drupal\aculta_learning_contracts\Learning\CourseJourney;
use Drupal\aculta_learning_contracts\Learning\CourseJourneyReaderInterface;
use Drupal\aculta_learning_contracts\Learning\StudentLearningSummary;
use Drupal\aculta_learning_contracts\Learning\StudentLearningSummaryReaderInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the contracts module installs without the Portal or the contrib LMS.
 */
#[Group('aculta_learning_contracts')]
#[RunTestsInSeparateProcesses]
final class ContractsInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Installs the module with neither aculta_portal nor lms enabled.
   */
  public function testInstallsWithoutPortalOrLms(): void {
    self::assertTrue(
      $this->container->get('module_installer')->install(['aculta_learning_contracts'], FALSE),
    );

    $module_handler = $this->container->get('module_handler');
    self::assertTrue($module_handler->moduleExists('aculta_learning_contracts'));
    self::assertFalse($module_handler->moduleExists('aculta_portal'));
    self::assertFalse($module_handler->moduleExists('lms'));
    self::assertTrue(interface_exists(CourseJourneyReaderInterface::class));
    self::assertTrue(interface_exists(StudentLearningSummaryReaderInterface::class));
    self::assertTrue(class_exists(CourseJourney::class));
    self::assertTrue(class_exists(StudentLearningSummary::class));
  }

}
