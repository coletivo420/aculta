<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Declares the neutral theme hook consumed by ACULTA420.
 */
final class AuthorCardThemeHooks {

  public function __construct(
    #[Autowire(service: 'extension.list.module')]
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Declares the shared author card template variables.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_user_author_card' => [
        'variables' => [
          'variant' => 'author_minimal',
          'display_name' => NULL,
          'profile_url' => NULL,
          'avatar' => NULL,
          'actions' => [],
          'source_label' => NULL,
        ],
        'template' => 'aculta-user-author-card',
        'path' => $this->moduleList->getPath('aculta_user') . '/templates',
      ],
    ];
  }

}
