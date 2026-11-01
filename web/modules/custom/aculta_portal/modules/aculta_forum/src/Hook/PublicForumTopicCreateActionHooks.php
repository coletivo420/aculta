<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds the public topic creation action to the new Growroom index.
 */
final class PublicForumTopicCreateActionHooks {

  public function __construct(
    #[Autowire(service: 'access_manager')]
    private readonly AccessManagerInterface $accessManager,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Adds a create link only when the current account can create this bundle.
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    if ($view->id() !== 'aculta_forum_public_topics'
      || $view->current_display !== 'page_discussions'
      || $this->domainPurposeManager->getCurrentPurpose() !== 'forum') {
      return;
    }

    $access = $this->accessManager->checkNamedRoute(
      'aculta_forum.topic_add',
      [],
      NULL,
      TRUE,
    );
    if (!$access instanceof AccessResultInterface || !$access instanceof CacheableDependencyInterface) {
      return;
    }
    // Carry allowed and denied access metadata onto the View. In particular,
    // page caches must vary when the create permission or participation state
    // changes, even when the action is absent.
    $cache = CacheableMetadata::createFromRenderArray($view->element)
      ->addCacheableDependency($access);
    $cache->applyTo($view->element);
    if (!$access->isAllowed()) {
      return;
    }

    $view->attachment_before['aculta_forum_create_topic'] = [
      '#type' => 'link',
      '#title' => new TranslatableMarkup('Criar tópico'),
      '#url' => Url::fromRoute('aculta_forum.topic_add'),
      '#attributes' => ['data-portal-ui' => 'button-primary'],
    ];
    CacheableMetadata::createFromObject($access)
      ->applyTo($view->attachment_before['aculta_forum_create_topic']);
  }

}
