<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Private account experience: never edit the public/community profile here.
 *
 * The legacy account UI remains at /conta-legada in the ACCOUNT purpose.
 */
final class AccountExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
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
      'profile_link' => $this->profileLink(),
      'account_link' => [
        '#theme' => 'item_list',
        '#items' => [
          [
            '#type' => 'link',
            '#title' => new TranslatableMarkup('Acessar Minha Conta'),
            '#url' => Url::fromRoute('aculta_studio.account_data'),
          ],
        ],
        '#attributes' => [
          'data-portal-ui' => 'account-entry-links',
          'aria-label' => new TranslatableMarkup('Minha Conta'),
        ],
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
   * Builds the private editor link only when the owner route is available.
   *
   * @return array<string, mixed>
   *   A link render array, or an empty array when the optional owner is absent.
   */
  private function profileLink(): array {
    if ($this->currentUser === NULL) {
      return [];
    }
    try {
      $url = Url::fromRoute('aculta_studio.profile');
      $access = $url->access($this->currentUser, TRUE);
    }
    catch (RouteNotFoundException) {
      return [];
    }
    if (!$access instanceof AccessResultInterface || !$access->isAllowed()) {
      return [];
    }
    $build = [
      '#type' => 'link',
      '#title' => new TranslatableMarkup('Editar Meu Perfil'),
      '#url' => $url,
      '#attributes' => ['data-portal-ui' => 'studio-profile-entry'],
    ];
    $access->addCacheContexts(['user', 'user.permissions', 'url.site'])->applyTo($build);
    return $build;
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
      $identityValues = $identity->toArray();
      $identityCache = CacheableMetadata::createFromObject($user)
        ->addCacheableDependency($identity)
        ->addCacheContexts(['user', 'user.permissions']);
      $build = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-account-home studio-account-identity'],
        'name' => [
          '#plain_text' => $identityValues['display_name'] ?? '',
        ],
      ];
      if (is_array($identityValues['avatar'] ?? NULL)) {
        $build['avatar'] = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'studio-account-avatar'],
          'image' => $identityValues['avatar'],
        ];
      }
      $profileUrl = $identityValues['profile_url'] ?? NULL;
      if (is_string($profileUrl) && $profileUrl !== '') {
        $build['public_profile_link'] = [
          '#type' => 'link',
          '#title' => new TranslatableMarkup('Ver perfil público'),
          '#url' => Url::fromUri($profileUrl),
          '#attributes' => ['data-portal-ui' => 'studio-profile-public-link'],
        ];
      }
      $identityCache->applyTo($build);
      return $build;
    }
    // A Drupal display name may be a private/legal name. Hide identity until
    // the public identity owner is available instead of leaking that fallback.
    return [];
  }

}
