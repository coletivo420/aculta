<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Acknowledges pending email counts on existing Forum notice aggregates.
 */
interface ForumNoticeCounterInterface {

  /**
   * Acknowledges events represented by one delivered email.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int $nid
   *   Forum topic node ID.
   * @param string $type
   *   Notice type.
   * @param int $sentCount
   *   Number of events acknowledged.
   */
  public function acknowledgeEmailCount(int $uid, int $nid, string $type, int $sentCount): void;

}
