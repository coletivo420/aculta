<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;

/**
 * Private account experience: never edit the public/community profile here.
 *
 * The legacy account UI remains at /conta-legada in the ACCOUNT purpose.
 */
final class AccountExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
    private readonly ?AccountDocumentInterface $documents = NULL,
    private readonly ?DomainPurposeManager $domainPurposes = NULL,
    private readonly ?EntityTypeManagerInterface $entities = NULL,
    private readonly ?AccountInterface $currentUser = NULL,
    private readonly ?AuthorIdentityResolver $identityResolver = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'conta';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Minha Conta';
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
    return 'aculta_studio.account';
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
    $items = $this->links();
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'account-entry'],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => 'Studio Dashboard',
      ],
      'description' => [
        '#plain_text' => new TranslatableMarkup('Acesse seus dados, segurança e experiências ACULTA.'),
      ],
      'identity' => $this->identity(),
      'profile_link' => [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Editar Meu Perfil'),
        '#url' => Url::fromRoute('aculta_studio.profile'),
        '#attributes' => ['data-portal-ui' => 'studio-profile-entry'],
      ],
      'sections' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      'suites_heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Experiências',
      ],
      'suites' => [
        '#theme' => 'item_list',
        '#items' => [],
        '#attributes' => ['data-portal-ui' => 'account-experiences'],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Builds current-user identity from the ACULTA User identity resolver.
   */
  private function identity(): array {
    if ($this->entities === NULL || $this->currentUser === NULL || !$this->currentUser->id()) {
      return [];
    }
    $user = $this->entities->getStorage('user')->load($this->currentUser->id());
    if ($user === NULL) {
      return [];
    }
    $identity = $this->identityResolver?->resolve($user, 'profile_full');
    if ($identity !== NULL) {
      return [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-account-identity'],
        'name' => [
          '#plain_text' => $identity->toArray()['display_name'] ?? '',
        ],
        '#cache' => [
          'contexts' => ['user', 'user.permissions'],
          'tags' => array_merge($user->getCacheTags(), $identity->getCacheTags()),
          'max-age' => 0,
        ],
      ];
    }
    // A Drupal display name may be a private/legal name. Hide identity until
    // the public identity owner is available instead of leaking that fallback.
    return [];
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
