<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Trusted lazy builder for discussion reply forms.
 */
class DiscussionReplyLazyBuilder implements TrustedCallbackInterface {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly DiscussionReplyFormBuilder $formBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['renderReplyComposer'];
  }

  /**
   * Render API callback: Builds the discussion reply composer.
   *
   * @param string $entityTypeId
   *   The host entity type ID.
   * @param string $entityId
   *   The host entity ID.
   * @param string|null $parentId
   *   Optional parent comment ID (scalar string or NULL).
   * @param string|null $quote
   *   Optional quote string.
   *
   * @return array
   *   A renderable array containing the composer.
   */
  public function renderReplyComposer(string $entityTypeId, string $entityId, ?string $parentId = NULL, ?string $quote = NULL): array {
    $host = $this->entityTypeManager->getStorage($entityTypeId)->load($entityId);
    if ($host === NULL) {
      return [];
    }

    $parsedParentId = ($parentId !== NULL && $parentId !== '' && ctype_digit($parentId)) ? (int) $parentId : NULL;

    return $this->formBuilder->buildReplyForm($host, $this->currentUser, $parsedParentId, $quote);
  }

}
