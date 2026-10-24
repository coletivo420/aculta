<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Minimal personal entrypoint; account data and forms remain in their owners.
 *
 * The Studio does not replicate the Portal account shell or write profile data.
 */
final class PersonalExperienceProvider implements StudioExperienceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'pessoal';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Meu Espaço';
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
    return 'aculta_studio.personal';
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
      '#attributes' => ['data-portal-ui' => 'studio-personal-entry'],
      'description' => [
        '#plain_text' => new TranslatableMarkup('Seu espaço pessoal está sendo integrado ao ACULTA Studio. Enquanto isso, continue usando as páginas atuais da conta.'),
      ],
      'links' => [
        '#theme' => 'item_list',
        '#items' => [
          [
            '#type' => 'link',
            '#title' => new TranslatableMarkup('Visão geral da minha conta'),
            // Account front is '/', not the protected technical /minha-conta.
            '#url' => Url::fromRoute('<front>'),
          ],
          [
            '#type' => 'link',
            '#title' => new TranslatableMarkup('Meus dados'),
            '#url' => Url::fromRoute('aculta_portal.my_data'),
          ],
          [
            '#type' => 'link',
            '#title' => new TranslatableMarkup('Segurança'),
            '#url' => Url::fromRoute('aculta_portal.security'),
          ],
          [
            '#type' => 'link',
            '#title' => new TranslatableMarkup('Configurações'),
            '#url' => Url::fromRoute('aculta_portal.account_settings'),
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site'],
      ],
    ];
  }

}
