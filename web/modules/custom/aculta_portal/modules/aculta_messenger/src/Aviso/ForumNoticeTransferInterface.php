<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Transfers response-notice aggregates between public Forum topics. */
interface ForumNoticeTransferInterface {

  /**
   * Transfers complete response notice records and returns the number moved. */
  public function transferTopicResponseNotices(int $sourceNid, int $destinationNid): int;

}
