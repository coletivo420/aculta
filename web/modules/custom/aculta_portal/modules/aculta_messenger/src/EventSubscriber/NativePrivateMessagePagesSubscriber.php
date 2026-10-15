<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes the native Private Message pages from the member experience.
 *
 * The inbox page redirects to the account home; the conversation page answers 404. Form routes and the
 * new-message page keep working, because the profile action still uses the new-message page.
 */
final class NativePrivateMessagePagesSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    // After the Portal's domain and account routing (-2049/-2050), so the purpose set there is kept.
    return [RoutingEvents::ALTER => ['onAlterRoutes', -2060]];
  }

  public function onAlterRoutes(RouteBuildEvent $event): void {
    $collection = $event->getRouteCollection();
    $inbox = $collection->get('private_message.private_message_page');
    if ($inbox !== NULL) {
      $inbox->setDefault('_controller', '\Drupal\aculta_messenger\Controller\NativePrivateMessageController::redirectToAccount');
    }
    $thread = $collection->get('entity.private_message_thread.canonical');
    if ($thread !== NULL) {
      $thread->setDefault('_controller', '\Drupal\aculta_messenger\Controller\NativePrivateMessageController::gone');
      $thread->setDefault('_entity_view', NULL);
      // The controller always answers 404, so the entity access check (which would answer 403) is not needed.
      $thread->setRequirements(['_access' => 'TRUE']);
    }
  }

}
