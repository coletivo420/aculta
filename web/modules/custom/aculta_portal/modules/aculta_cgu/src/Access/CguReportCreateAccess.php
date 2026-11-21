<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Access;

use Drupal\aculta_cgu\Service\ReportService;
use Drupal\aculta_cgu\Service\ReportTargetResolver;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Owns the public reporting route access, independently of the visual trigger.
 */
final class CguReportCreateAccess {

  public function __construct(
    private readonly ReportTargetResolver $targets,
    private readonly ReportService $reports,
  ) {}

  public function access(string $entity_type, string $entity_id, AccountInterface $account): AccessResultInterface {
    $target = $this->targets->load($entity_type, $entity_id);
    $result = AccessResult::allowedIf($target !== NULL
      && (!$target instanceof EntityPublishedInterface || $target->isPublished())
      && $this->reports->canReport($target, $account))
      ->addCacheContexts(['user', 'user.permissions', 'domain'])
      ->setCacheMaxAge(0);
    if ($target !== NULL) {
      $result->addCacheableDependency($target);
    }
    return $result;
  }

}
