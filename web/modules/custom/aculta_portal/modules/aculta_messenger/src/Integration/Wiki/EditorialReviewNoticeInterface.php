<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki;

/**
 * Public contract for the Wiki editorial review mailbox notice.
 *
 * The Wiki owns the editorial fact and persists it in its transactional event
 * queue. The Messenger owns the administrative recipients, the email type and
 * the delivery coordination, and revalidates the revision before queueing.
 */
interface EditorialReviewNoticeInterface {

  /**
   * Queues the administrative review email for the current revision.
   *
   * @param string $entryUuid
   *   UUID of the wiki_entry node that entered review.
   * @param int $revisionId
   *   Revision ID that must still be the default revision in review.
   *
   * @return int
   *   Number of recipients newly queued for delivery.
   */
  public function notifyReview(string $entryUuid, int $revisionId): int;

}
