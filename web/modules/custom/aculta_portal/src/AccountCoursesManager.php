<?php

declare(strict_types=1);

namespace Drupal\aculta_portal;

use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
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
    private readonly DomainSiteManager $siteManager,
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
   * Conta os cursos autorizados da conta.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Conta consultada.
   *
   * @return int
   *   Quantidade de cursos.
   */
  public function countCourses(AccountInterface $account): int {
    return count($this->getCourses($account));
  }

  /**
   * URL do catálogo de cursos, quando o domínio de cursos existe.
   *
   * @return \Drupal\Core\Url|null
   *   URL do catálogo ou NULL quando não disponível.
   */
  public function catalogUrl(): ?Url {
    return $this->siteManager->routeUrl(SiteKey::Courses, 'aculta_portal.courses_home');
  }

}
