<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\EventSubscriber;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Restricts the public Forum Search API index to its canonical purpose.
 */
final class ForumSearchQuerySubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [SearchApiEvents::QUERY_PRE_EXECUTE => 'onQueryPreExecute'];
  }

  /**
   * Adds a server-side purpose condition to the Forum-only index.
   */
  public function onQueryPreExecute(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    if ($query->getIndex()->id() !== 'aculta_forum') {
      return;
    }

    $domain = $this->domainPurposeManager->getDomain('forum');
    if ($domain === NULL || $this->domainPurposeManager->getCurrentPurpose() !== 'forum') {
      // A positive Node ID cannot match this fail-closed sentinel.
      $query->addCondition('nid', 0);
      return;
    }

    $query->addCondition('forum_domain', (string) $domain->id());
  }

}
