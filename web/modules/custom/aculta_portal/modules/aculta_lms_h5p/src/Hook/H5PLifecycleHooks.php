<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Hook;

use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Handles H5P lifecycle hooks through Drupal's object-oriented hook API. */
final class H5PLifecycleHooks {

  public function __construct(
    #[Autowire(service: 'aculta_lms_h5p.framework')]
    private readonly H5PDrupal $framework,
    #[Autowire(service: 'database')]
    private readonly Connection $database,
  ) {}

  /** Implements hook_cron(). */
  #[Hook('cron')]
  public function cron(): void {
    $this->framework->fetchLibrariesMetadata();
    $this->framework->removeOldLogEvents();
  }

  /** Implements hook_user_delete(). */
  #[Hook('user_delete')]
  public function userDelete(EntityInterface $entity): void {
    $this->database->delete('h5p_points')
      ->condition('uid', $entity->id())
      ->execute();
    $this->database->delete('h5p_content_user_data')
      ->condition('user_id', $entity->id())
      ->execute();
  }

}
