<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * A tab of the Studio, provided by the owner of the functional area.
 *
 * The Studio never stores content or permissions of its own: a tab only
 * exposes actions of its owner, and each action keeps the owner's access check.
 */
interface StudioTabInterface extends PluginInspectionInterface {

  /**
   * Whether the owning module is enabled. Optional owners stay out of the Studio.
   */
  public function isAvailable(): bool;

  /**
   * Whether the tab is shown to the account (the owner's permission).
   */
  public function access(AccountInterface $account): AccessResultInterface;

  /**
   * Actions of the tab. Each item has a title, an URL and an access flag.
   *
   * @return array<int, array{title: \Stringable|string, url: \Drupal\Core\Url, access: bool}>
   */
  public function actions(AccountInterface $account): array;

}
