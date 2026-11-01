<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeReadInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ler o tópico zera o contador do aviso do site (ACULTA Fórum, F5.2).
 */
final class ForumAvisoReadSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly ForumNoticeReadInterface $avisos,
  ) {}

  /**
   * Clears the recipient's site notice count on canonical topic visits.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'entity.node.canonical' || $this->currentUser->isAnonymous()) {
      return;
    }
    // Route parameter conversion in Drupal may already have upcast the node
    // ID to an entity before request subscribers run.
    $node = $request->attributes->get('node');
    if ($node instanceof NodeInterface) {
      $node = $node->id();
    }
    if (is_numeric($node) && (int) $node > 0) {
      $this->avisos->marcarLido((int) $this->currentUser->id(), (int) $node);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onRequest', 0]];
  }

}
