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

  /**
   * Returns the stable experience identifier.
   *
   * @return string
   *   Machine name of the experience, unique within the registry.
   */
  public function id(): string;

  /**
   * Returns the translatable label shown in the Studio hub.
   *
   * @return string
   *   Human-readable experience name.
   */
  public function label(): string;

  /**
   * Returns the domain purpose where this experience is offered.
   *
   * @return string
   *   Purpose identifier, such as ACCOUNT or MAIN.
   */
  public function purpose(): string;

  /**
   * Returns the route that opens this experience.
   *
   * @return string
   *   Name of the Drupal route.
   */
  public function routeName(): string;

  /**
   * Reports whether the experience is currently enabled.
   *
   * @return bool
   *   TRUE when the experience can be offered.
   */
  public function available(): bool;

  /**
   * Checks whether the given account may open this experience.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting the experience.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result, including its cacheability.
   */
  public function access(AccountInterface $account): AccessResultInterface;

  /**
   * Builds an authorized entrypoint using the owning module's services.
   *
   * @return array<string, mixed>
   *   A Drupal render array.
   */
  public function build(): array;

}
