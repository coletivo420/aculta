<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Metadata and authorized entry rendering for one Studio experience.
 *
 * Providers must delegate domain authorization and data access to their owner.
 */
interface StudioExperienceProviderInterface {

  public function id(): string;

  public function label(): string;

  public function purpose(): string;

  public function routeName(): string;

  public function available(): bool;

  public function access(AccountInterface $account): AccessResultInterface;

  /**
   * Builds an authorized entrypoint using the owning module's services.
   *
   * @return array<string, mixed>
   *   A Drupal render array.
   */
  public function build(): array;

}
