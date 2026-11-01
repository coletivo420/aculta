<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp\EventSubscriber;

use Drupal\simple_oauth_server_metadata\Event\ResourceMetadataEvent;
use Drupal\simple_oauth_server_metadata\Event\ResourceMetadataEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Advertises the least-privilege scope required to access the MCP endpoint.
 */
final class OAuthResourceMetadataSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ResourceMetadataEvents::BUILD => 'onBuild'];
  }

  /**
   * Adds the endpoint-wide scope to Protected Resource Metadata.
   */
  public function onBuild(ResourceMetadataEvent $event): void {
    $scopes = $event->metadata['scopes_supported'] ?? [];
    $scopes[] = 'aculta_mcp_access';
    $event->addMetadataField('scopes_supported', array_values(array_unique($scopes)));
  }

}
