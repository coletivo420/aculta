<?php

declare(strict_types=1);

namespace Drupal\aculta_user\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Forum\RestrictedForumGrants;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Responde 404 para páginas de fórum restrito sem acesso (ACULTA Fórum, F9, F-D14).
 *
 * Visitante, conta sem o realm e bots recebem 404, e não 403: o conteúdo não aparece nem é confirmado. A decisão
 * do responsável é "bots recebem 404 nos restritos".
 */
final class RestrictedForumNotFoundSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly RestrictedForumGrants $grants,
  ) {}

  /**
   * Converte o 403 de página restrita em 404.
   */
  public function onException(ExceptionEvent $event): void {
    if (!$event->getThrowable() instanceof AccessDeniedHttpException) {
      return;
    }
    $request = $event->getRequest();
    $entidade = $request->attributes->get('node') ?? $request->attributes->get('taxonomy_term');
    if (!$entidade instanceof NodeInterface && !$entidade instanceof TermInterface) {
      return;
    }
    $exigidos = $entidade instanceof NodeInterface
      ? $this->grants->realmsDoTopico($entidade)
      : ($entidade->bundle() === 'forums' ? $this->grants->realmsDoNivel($entidade) : []);
    if ($exigidos === []) {
      return;
    }
    $event->setThrowable(new NotFoundHttpException('Página não encontrada.', $event->getThrowable()));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 75]];
  }

}
