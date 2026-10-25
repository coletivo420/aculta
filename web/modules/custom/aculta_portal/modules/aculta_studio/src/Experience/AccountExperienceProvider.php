<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Private account experience: never edit the public/community profile here.
 *
 * The legacy account UI remains at /conta-legada in the ACCOUNT purpose.
 */
final class AccountExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(private readonly ?AccountDocumentInterface $documents = NULL) {}

  public function id(): string {
    return 'conta';
  }

  public function label(): string {
    return 'Minha Conta';
  }

  public function purpose(): string {
    return 'account';
  }

  public function routeName(): string {
    return 'aculta_studio.account';
  }

  public function available(): bool {
    return TRUE;
  }

  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $items = $this->links();
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-account-home'],
      'description' => [
        '#plain_text' => new TranslatableMarkup('Administre os dados privados e a segurança da sua conta. A interface original da Minha Conta permanece disponível para comparação.'),
      ],
      'sections' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Builds only owner-backed account links; hide unavailable document editor.
   *
   * @return array<int, array<string, mixed>>
   *   Link render arrays.
   */
  private function links(): array {
    $items = [
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Informações cadastrais'),
        '#url' => Url::fromRoute('aculta_studio.account_data'),
      ],
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Endereço'),
        '#url' => Url::fromRoute('aculta_studio.account_address'),
      ],
    ];

    if ($this->documents?->isAvailable()) {
      $items[] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Documentos'),
        '#url' => Url::fromRoute('aculta_studio.account_documents'),
      ];
    }

    return array_merge($items, [
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('E-mail e segurança'),
        '#url' => Url::fromRoute('aculta_studio.account_security'),
      ],
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Senha'),
        '#url' => Url::fromRoute('aculta_studio.account_password'),
      ],
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Contas conectadas'),
        '#url' => Url::fromRoute('aculta_studio.account_connections'),
      ],
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Meus pedidos'),
        '#url' => Url::fromRoute('aculta_studio.account_orders'),
      ],
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Meu apoio'),
        '#url' => Url::fromRoute('aculta_studio.account_support'),
      ],
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Preferências da conta'),
        '#url' => Url::fromRoute('aculta_studio.account_preferences'),
      ],
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Abrir Minha Conta original'),
        '#url' => Url::fromRoute('aculta_portal.dashboard'),
      ],
    ]);
  }

}
