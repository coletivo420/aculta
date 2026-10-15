<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Native Private Message pages that no longer exist: messages are read and sent only in the multidomain bar.
 *
 * Form actions (delete, clear history, ban) still finish on the inbox URL; that URL now sends the member to
 * the account home instead of a page of their own.
 */
final class NativePrivateMessageController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_portal.domain_purpose'));
  }

  /** The inbox page is gone: redirect to the account home (purpose account). */
  public function redirectToAccount(): Response {
    // The account home is the root of the account purpose (as the account shell links it), not /minha-conta.
    $target = $this->domainPurposeManager->pathUrl('account', '/');
    return new TrustedRedirectResponse($target?->setAbsolute()->toString() ?? '/', 302);
  }

  /** Native conversation pages are not served; the drawer is the only conversation view. */
  public function gone(): never {
    throw new NotFoundHttpException();
  }

}
