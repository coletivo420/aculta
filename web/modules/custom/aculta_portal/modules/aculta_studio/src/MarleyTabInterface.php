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
interface MarleyTabInterface extends PluginInspectionInterface {

  /**
   * Whether the owning module is enabled. Optional owners stay out of the Studio.
   */
  public function isAvailable(): bool;

  /**
   * Whether the tab is shown to the account (the owner's permission).
   */
  public function access(AccountInterface $account): AccessResultInterface;

  /**
   * The sections of the area: one entry per item of the left submenu. Each section has its own tabs, and
   * each tab lists its actions. Shape:
   *   ['<section id>' => ['label' => ..., 'weight' => 0, 'tabs' => [
   *       '<tab id>' => ['label' => ..., 'weight' => 0, 'actions' => [
   *           ['title' => ..., 'url' => Url, 'access' => bool]]]]]]
   * The presenter removes every item the account cannot use.
   *
   * @return array<string, array{label: \Stringable|string, weight?: int, tabs: array<string, array{label: \Stringable|string, weight?: int, actions: array<int, array{title: \Stringable|string, url: \Drupal\Core\Url, access: bool}>}>}>
   */
  public function sections(AccountInterface $account): array;

}

