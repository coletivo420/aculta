<?php

declare(strict_types=1);

namespace Drupal\aculta_portal;

use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Provider-neutral facade for the account's learning section.
 *
 * The educational provider owns Group/LMS access and progress. The Portal
 * adapts its DTOs for the existing account presenter and remains available when
 * no learning provider is installed.
 */
final class AccountCoursesManager {

  public function __construct(
    private readonly ?AccountCoursesReaderInterface $reader,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Returns authorized course projections for the account presenter.
   *
   * @return list<array<string, mixed>>
   *   Provider-neutral course records in the legacy presenter shape.
   */
  public function getCourses(AccountInterface $account): array {
    $items = [];
    foreach ($this->reader?->getAccountCourses($account) ?? [] as $course) {
      if (!$course instanceof AccountCourseSummary) {
        continue;
      }
      $description = [];
      if ($course->description !== '' && $course->descriptionFormat !== '') {
        $description = [
          '#type' => 'processed_text',
          '#text' => $course->description,
          '#format' => $course->descriptionFormat,
        ];
      }
      $items[] = [
        'id' => $course->id,
        'label' => $course->label,
        'description' => $description,
        'status' => $course->status,
        'score' => $course->score,
        'finished' => $course->finished,
        'url' => $course->url,
        'cache_tags' => $course->cacheTags,
      ];
    }
    usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
    return $items;
  }

  /**
   *
   */
  public function countCourses(AccountInterface $account): int {
    return count($this->getCourses($account));
  }

  /**
   *
   */
  public function catalogUrl(): ?Url {
    return $this->domainPurposeManager->routeUrl('courses', 'aculta_portal.courses_home');
  }

}
