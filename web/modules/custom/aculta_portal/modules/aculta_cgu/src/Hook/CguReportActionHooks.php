<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Hook;

use Drupal\aculta_cgu\Service\ReportService;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;

/**
 * Offers the same report action for every CGU-supported content entity.
 *
 * Consumers may place the render array inside an action bar; all report
 * writes, policy checks and data retention remain with aculta_cgu.
 */
final class CguReportActionHooks {

  public function __construct(
    private readonly ReportService $reports,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Adds the CGU action to full content displays and discussion replies.
   *
   * @param array<string, mixed> $build
   *   Access-checked entity render array.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof ContentEntityInterface
      || $this->currentUser->isAnonymous()
      || $entity->isNew()
      || ($entity instanceof EntityPublishedInterface && !$entity->isPublished())
      || !in_array((string) ($build['#view_mode'] ?? ''), ['full', 'default'], TRUE)) {
      return;
    }

    // Exclude own CGU entities and unrelated configuration/commerce entities.
    // This whitelist is deliberately extensible for future CGU owners.
    if (!in_array($entity->getEntityTypeId(), [
      'node', 'comment', 'aculta_forum_post', 'aculta_event',
    ], TRUE) || !$this->reports->canReport($entity, $this->currentUser)) {
      return;
    }

    $id = (string) $entity->id();
    $type = $entity->getEntityTypeId();
    $route = Url::fromRoute('aculta_cgu.report_create', ['entity_type' => $type, 'entity_id' => $id]);
    $generated = $route->toString(TRUE);
    $action = [
      '#theme' => 'portal_component',
      '#name' => 'cgu-report-button',
      '#props' => [
        'url' => $generated->getGeneratedUrl(),
        'dialog_id' => 'aculta-cgu-report-' . $type . '-' . $id,
        'label' => (string) t('Denunciar'),
        'with_dialog' => TRUE,
        'load_on_open' => TRUE,
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'domain', 'languages:language_interface'],
        'max-age' => 0,
      ],
      '#weight' => 80,
    ];
    CacheableMetadata::createFromRenderArray($action)
      ->addCacheableDependency($entity)
      ->addCacheableDependency($generated)
      ->applyTo($action);
    $build['aculta_cgu_report_action'] = $action;
  }

}
