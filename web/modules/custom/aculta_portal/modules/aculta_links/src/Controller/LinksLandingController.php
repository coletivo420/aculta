<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_links\Service\SocialLinksPagePresenter;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the links-purpose front page when its editorial alias is absent.
 */
final class LinksLandingController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly AliasManagerInterface $aliasManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly DomainPurposeManager $domainPurpose,
    private readonly SocialLinksPagePresenter $pagePresenter,
  ) {}

  /**
   * Builds the editorial page when available, or a safe configured fallback.
   */
  public function view(): array {
    $domain = $this->domainPurpose->getDomain('links');
    if ($domain === NULL) {
      throw new NotFoundHttpException();
    }

    $language = $this->languageManager->getCurrentLanguage()->getId();
    $path = $this->aliasManager->getPathByAlias('/redes-sociais', $language);
    if (preg_match('#^/node/(\d+)$#D', $path, $matches) === 1) {
      $node = $this->entityTypeManager->getStorage('node')->load($matches[1]);
      if ($node instanceof NodeInterface
        && $node->bundle() === 'page'
        && $node->hasField('field_domain_source')
        && (string) $node->get('field_domain_source')->target_id === $domain->id()) {
        $access = $node->access('view', $this->currentUser, TRUE);
        if (!$access->isAllowed()) {
          throw new NotFoundHttpException();
        }
        $build = $this->entityTypeManager->getViewBuilder('node')->view($node, 'full');
        (new CacheableMetadata())
          ->addCacheableDependency($domain)
          ->addCacheableDependency($access)
          ->applyTo($build);
        return $build;
      }
    }

    $links = $this->pagePresenter->buildSiteButtons();
    $projects = $this->pagePresenter->buildProjects();
    $has_projects = isset($projects['#view'])
      && $projects['#view'] instanceof ViewExecutable
      && $projects['#view']->result !== [];
    $build = [
      '#theme' => 'aculta_links_landing',
      '#title' => new TranslatableMarkup('ACULTA'),
      '#description' => '',
      '#links' => $links,
      '#has_links' => $links !== [],
      '#projects' => $projects,
      '#has_projects' => $has_projects,
      '#theme_preset' => 'classic',
    ];
    (new CacheableMetadata())
      ->addCacheContexts(['url.site', 'languages:language_interface'])
      ->addCacheTags(['path_alias_list', 'config:aculta_links.social_links'])
      ->addCacheableDependency($domain)
      ->applyTo($build);
    return $build;
  }

}
