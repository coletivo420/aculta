<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Marks Forum topic notices as read after the recipient opens the topic.
 */
interface ForumNoticeReadInterface {

  /**
   * Clears the site unread count for a topic the recipient can access.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int $nid
   *   Forum topic node ID.
   */
  public function marcarLido(int $uid, int $nid): void;

}
