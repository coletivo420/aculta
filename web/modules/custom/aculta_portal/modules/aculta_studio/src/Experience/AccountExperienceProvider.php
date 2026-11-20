<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_studio\Calendar\StudioDashboardCalendarComposer;
use Drupal\aculta_studio\Connections\StudioDashboardConnectionsComposer;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Private account experience: never edit the public/community profile here.
 *
 * The legacy account UI remains at /conta-legada in the ACCOUNT purpose.
 *
 * This provider returns the account presentation as SDC render arrays while
 * keeping identity, links, cache, and authorization decisions in their owners.
 *
 * @phpstan-type StudioMenuBuild array{
 *   '#theme': 'portal_component',
 *   '#name': 'studio-menu',
 *   '#props': array{title: string},
 *   '#slots': array{
 *     sidebar_items: array<int, array<string, mixed>>,
 *     dialog_items: array<int, array<string, mixed>>
 *   }
 * }
 */
final class AccountExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
    private readonly ?EntityTypeManagerInterface $entities = NULL,
    private readonly ?AccountInterface $currentUser = NULL,
    private readonly ?AuthorIdentityResolver $identityResolver = NULL,
    private readonly ?StudioDashboardCalendarComposer $calendarComposer = NULL,
    private readonly ?StudioDashboardConnectionsComposer $connectionsComposer = NULL,
    private readonly ?ConfigFactoryInterface $configFactory = NULL,
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
   *
   * @return array{
   *   '#theme': 'portal_component',
   *   '#name': 'account-experience-dashboard',
   *   '#props': array{
   *     show_identity: bool,
   *     identity_name: string,
   *     identity_email: mixed,
   *     profile_url: mixed,
   *     experiences_title: string,
   *     experiences_description: string,
   *     account_url: string,
   *     account_link_title: string,
   *     account_link_description: string,
   *     account_link_label: string
   *   },
   *   '#slots': array{
   *     menu: StudioMenuBuild,
   *     avatar: mixed,
   *     profile_link: array<string, mixed>,
   *     calendar: array<string, mixed>,
   *     connections: array<string, mixed>,
   *     experiences: array{}
   *   },
   *   '#cache': array{contexts: list<string>, 'max-age': 0}
   *   }
   *   The component render array, including private owner-provided slots.
   */
  public function build(): array {
    $identity = $this->identity();
    $menuItems = $this->studioMenuItems();
    $sidebarMenuItems = array_map(
      static fn (array $item): array => [
        '#theme' => 'portal_component',
        '#name' => 'studio-menu-item',
        '#props' => $item + ['show_icon' => TRUE],
      ],
      $menuItems,
    );
    $dialogMenuItems = array_map(
      static fn (array $item): array => [
        '#theme' => 'portal_component',
        '#name' => 'studio-menu-item',
        '#props' => $item + ['show_icon' => FALSE],
      ],
      $menuItems,
    );
    return [
      '#theme' => 'portal_component',
      '#name' => 'account-experience-dashboard',
      '#props' => [
        'show_identity' => $identity !== [],
        'identity_name' => is_string($identity['name'] ?? NULL) || ($identity['name'] ?? NULL) instanceof \Stringable
          ? (string) $identity['name']
          : '',
        'identity_email' => $identity['email'] ?? NULL,
        'profile_url' => $identity['profile_url'] ?? NULL,
        'experiences_title' => $this->presentationValue('experiences_title', (string) new TranslatableMarkup('Suas experiências')),
        'experiences_description' => $this->presentationValue('experiences_description', (string) new TranslatableMarkup('Acesse as experiências da ACULTA e explore conteúdos, projetos e comunidades.')),
        'account_url' => Url::fromRoute('aculta_studio.account')->toString(),
        'account_link_title' => $this->presentationValue('account_link_title', (string) new TranslatableMarkup('Minha conta')),
        'account_link_description' => $this->presentationValue('account_link_description', (string) new TranslatableMarkup('Dados, segurança e preferências')),
        'account_link_label' => $this->presentationValue('account_link_label', (string) new TranslatableMarkup('Minha Conta')),
      ],
      '#slots' => [
        'menu' => [
          '#theme' => 'portal_component',
          '#name' => 'studio-menu',
          '#props' => [
            'title' => (string) new TranslatableMarkup('Studio Dashboard'),
          ],
          '#slots' => [
            'sidebar_items' => $sidebarMenuItems,
            'dialog_items' => $dialogMenuItems,
          ],
        ],
        'avatar' => $identity['avatar'] ?? [],
        'profile_link' => $this->profileLink(),
        'calendar' => $this->calendarComposer?->compose() ?? [],
        'connections' => $this->connectionsComposer?->compose() ?? [],
        'experiences' => [],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Returns an editorial presentation value without exposing user-owned data.
   */
  private function presentationValue(string $key, string $default): string {
    $value = $this->configFactory?->get('aculta_studio.account_presentation')->get($key);
    return is_string($value) && trim($value) !== '' ? $value : $default;
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
    if (!$access instanceof AccessResultInterface) {
      return [];
    }
    $cacheability = CacheableMetadata::createFromObject($access)
      ->addCacheContexts(['user', 'user.permissions', 'url.site']);
    if (!$access->isAllowed()) {
      $build = [];
      $cacheability->applyTo($build);
      return $build;
    }
    $build = [
      '#type' => 'link',
      '#title' => new TranslatableMarkup('Editar Meu Perfil'),
      '#url' => $url,
      '#attributes' => ['data-portal-ui' => 'studio-profile-entry'],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Builds the current-user identity data from the ACULTA User resolver.
   *
   * @return array<string, mixed>
   *   Name, e-mail, avatar and public profile URL, or an empty array.
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
    if ($identity === NULL) {
      // A Drupal display name may be a private/legal name. Hide identity until
      // the public identity owner is available instead of leaking it.
      return [];
    }
    $identityValues = $identity->toArray();
    $values = [
      'name' => $identityValues['display_name'] ?? '',
      'email' => NULL,
      'avatar' => NULL,
      'profile_url' => NULL,
    ];
    $mail = $user->getEmail();
    if (is_string($mail) && $mail !== '') {
      $values['email'] = $mail;
    }
    if (is_array($identityValues['avatar'] ?? NULL)) {
      $values['avatar'] = $identityValues['avatar'];
    }
    $profileUrl = $identityValues['profile_url'] ?? NULL;
    if (is_string($profileUrl) && $profileUrl !== '') {
      $values['profile_url'] = $profileUrl;
    }
    return $values;
  }

  /**
   * Returns Studio menu items available to the current account.
   *
   * @return array<int, array{title: string, url: string, current: bool, icon: string}>
   *   Menu items for the Studio menu.
   */
  private function studioMenuItems(): array {
    if ($this->currentUser === NULL) {
      return [];
    }
    $candidates = [
      ['Studio Dashboard', '/', TRUE, NULL, 'house'],
      ['Experiências', '/', FALSE, 'suas-experiencias', 'squares-four'],
      ['Meus cursos', 'aculta_lms.student_dashboard', FALSE, NULL, 'graduation-cap'],
      ['Meu Perfil', 'aculta_studio.profile', FALSE, NULL, 'user'],
      ['Minha conta', 'aculta_studio.account', FALSE, NULL, 'gear-six'],
    ];
    $items = [];
    foreach ($candidates as [$title, $target, $current, $fragment, $icon]) {
      try {
        $url = str_starts_with($target, '/') ? Url::fromUserInput($target) : Url::fromRoute($target);
      }
      catch (RouteNotFoundException) {
        continue;
      }
      if (!$url->access($this->currentUser)) {
        continue;
      }
      $href = $url->toString();
      if ($fragment !== NULL) {
        $href .= '#' . $fragment;
      }
      $items[] = ['title' => $title, 'url' => $href, 'current' => $current, 'icon' => $icon];
    }
    return $items;
  }

}
