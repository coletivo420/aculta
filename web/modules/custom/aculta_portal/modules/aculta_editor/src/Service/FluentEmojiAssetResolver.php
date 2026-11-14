<?php

declare(strict_types=1);

namespace Drupal\aculta_editor\Service;

use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Resolve os assets do Fluent Emoji através do tema ativo sem acoplamento fixo.
 */
final class FluentEmojiAssetResolver implements FluentEmojiAssetResolverInterface {

  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAssetsUrl(): string {
    $activeTheme = $this->themeManager->getActiveTheme();
    $themePath = $activeTheme->getPath();
    return base_path() . $themePath . '/assets/icons/fluent-emoji/color';
  }

}
