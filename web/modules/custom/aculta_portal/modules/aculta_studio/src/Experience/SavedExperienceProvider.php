<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\aculta_studio\Saved\SavedContentService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides entrypoint to personal saved content, following, and recommendations.
 */
final class SavedExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
    private readonly SavedContentService $savedContent,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'salvos';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Meus Salvos';
  }

  /**
   * {@inheritdoc}
   */
  public function purpose(): string {
    return 'account';
  }

  /**
   * {@inheritdoc}
   */
  public function routeName(): string {
    return 'aculta_studio.salvos';
  }

  /**
   * {@inheritdoc}
   */
  public function available(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-saved-summary'],
      'description' => [
        '#plain_text' => new TranslatableMarkup('Acesse seus conteúdos salvos, itens que você está seguindo e recomendações personalizadas da comunidade.'),
      ],
      'link' => [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Ver meus salvos e recomendações'),
        '#url' => Url::fromRoute('aculta_studio.salvos'),
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['flagging_list'],
      ],
    ];
  }

}
