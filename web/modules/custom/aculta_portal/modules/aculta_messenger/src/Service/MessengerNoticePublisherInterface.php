<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;

/**
 * Public internal API for publishing notice events to Messenger consumers.
 */
interface MessengerNoticePublisherInterface {

  /**
   * Dispatches an event; consumers must revalidate access and deduplicate it.
   */
  public function publish(MessengerNoticeEvent $event): void;

}
