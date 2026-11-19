<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\AccountCoursesManager;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Learning\AccountCourseSummary;
use Drupal\aculta_portal\Learning\AccountCoursesReaderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies the Portal's provider-neutral account-course boundary.
 */
#[Group('aculta_portal')]
final class AccountCoursesManagerTest extends UnitTestCase {

  /**
   * Account pages stay usable without the optional learning provider.
   */
  public function testMissingProviderKeepsAccountAvailable(): void {
    $manager = $this->manager(NULL);
    $account = $this->createMock(AccountInterface::class);
    self::assertSame([], $manager->getCourses($account));
    self::assertSame(0, $manager->countCourses($account));
  }

  /**
   * The manager sorts neutral projections and preserves render/cache metadata.
   */
  public function testReaderProjectsAuthorizedCourseData(): void {
    $account = $this->createMock(AccountInterface::class);
    $reader = $this->createMock(AccountCoursesReaderInterface::class);
    $reader->expects(self::once())->method('getAccountCourses')->with($account)->willReturn([
      new AccountCourseSummary(
        id: '7',
        label: 'Zeta',
        description: '<p>Descrição</p>',
        descriptionFormat: 'basic_html',
        status: AccountCourseSummary::STATUS_IN_PROGRESS,
        score: NULL,
        finished: FALSE,
        url: NULL,
        cacheTags: ['group:7'],
      ),
      new AccountCourseSummary(
        id: '3',
        label: 'Alfa',
        description: '',
        descriptionFormat: '',
        status: AccountCourseSummary::STATUS_COMPLETED,
        score: 98,
        finished: TRUE,
        url: NULL,
        cacheTags: ['group:3'],
      ),
    ]);
    $courses = $this->manager($reader)->getCourses($account);
    self::assertSame(['Alfa', 'Zeta'], array_column($courses, 'label'));
    self::assertSame([], $courses[0]['description']);
    self::assertSame(98, $courses[0]['score']);
    self::assertSame(AccountCourseSummary::STATUS_COMPLETED, $courses[0]['status']);
    self::assertSame(['group:3'], $courses[0]['cache_tags']);
    self::assertSame([
      '#type' => 'processed_text',
      '#text' => '<p>Descrição</p>',
      '#format' => 'basic_html',
    ], $courses[1]['description']);
    self::assertSame(['group:7'], $courses[1]['cache_tags']);
  }

  /**
   * Constructs a manager without an installed LMS container service.
   */
  private function manager(?AccountCoursesReaderInterface $reader): AccountCoursesManager {
    return new AccountCoursesManager(
      $reader,
      new DomainSiteManager(
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
    );
  }

}
