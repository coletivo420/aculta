<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Adds the contextual proposal link to eligible public Wiki entries.
 */
final class WikiProposalLinkHooks {

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly EmailConfirmationPolicy $emailConfirmationPolicy,
  ) {}

  /**
   * Adds a contribution action only when direct editing is unavailable.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $viewMode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'wiki_entry' || $viewMode !== 'full') {
      return;
    }

    $permission = AccessResult::allowedIfHasPermission($this->currentUser, 'submit wiki proposals');
    $confirmed = AccessResult::allowedIf($this->emailConfirmationPolicy->isConfirmed($this->currentUser))
      ->cachePerUser();
    $domain = AccessResult::allowedIf($this->domainPurposes->getCurrentPurpose() === 'wiki')
      ->addCacheContexts(['domain']);
    $view = $entity->access('view', $this->currentUser, TRUE);
    $updateResult = $entity->access('update', $this->currentUser, TRUE);
    $update = AccessResult::allowedIf(!$updateResult->isAllowed())
      ->addCacheableDependency($updateResult);
    $published = AccessResult::allowedIf($entity->isPublished())
      ->addCacheableDependency($entity);
    $access = $permission->andIf($confirmed)->andIf($domain)->andIf($view)->andIf($update)->andIf($published);

    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user.permissions', 'domain'])
      ->addCacheableDependency($entity)
      ->addCacheableDependency($permission)
      ->addCacheableDependency($confirmed)
      ->addCacheableDependency($domain)
      ->addCacheableDependency($view)
      ->addCacheableDependency($update)
      ->addCacheableDependency($published);
    $cacheability->applyTo($build);

    if (!$access->isAllowed()) {
      return;
    }

    $build['wiki_proposal_link'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['wiki-proposal-link']],
      'link' => [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Sugerir uma alteração'),
        '#url' => Url::fromRoute('aculta_wiki.proposal_submit', ['node' => $entity->id()]),
        '#access' => $access,
        '#attributes' => ['class' => ['button', 'button--secondary']],
      ],
      '#weight' => 100,
      '#cache' => [
        'contexts' => ['user.permissions', 'domain'],
        'tags' => $entity->getCacheTags(),
      ],
    ];
  }

}
