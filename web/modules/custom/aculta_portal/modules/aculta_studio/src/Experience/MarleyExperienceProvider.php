<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\aculta_studio\MarleyPresenter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Exposes Marley metadata to Studio without replacing MarleyTabManager.
 *
 * Marley remains responsible for its editor, navigation and action policies.
 */
final class MarleyExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
    private readonly MarleyPresenter $presenter,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'marley';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'ACULTA Studio Marley';
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
    return 'aculta_studio.home';
  }

  /**
   * {@inheritdoc}
   */
  public function available(): bool {
    // The editorial plugin manager is provided by Studio itself. Optional
    // owner modules are checked by MarleyPresenter::visibleTabs().
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $base = AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->cachePerUser();

    if (!$base->isAllowed()) {
      return $base;
    }

    // visibleTabs() filters owner availability, access and usable actions,
    // but its present API does not expose the collected cacheability. Never
    // cache the result of this dynamic discovery across requests.
    $areas = AccessResult::allowedIf($this->presenter->visibleTabs($account) !== [])
      ->cachePerUser()
      ->setCacheMaxAge(0);

    return $base->andIf($areas);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if (!$this->access($this->currentUser)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    // This provider is a metadata/entry bridge. The editorial workspace is
    // still rendered exclusively by MarleyController on its existing routes.
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-marley-entry'],
      'link' => [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Abrir Marley'),
        '#url' => Url::fromRoute($this->routeName()),
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site'],
        'max-age' => 0,
      ],
    ];
  }

}
