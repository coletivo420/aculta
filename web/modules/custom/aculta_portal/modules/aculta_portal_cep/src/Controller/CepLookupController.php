<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Controller;

use Drupal\aculta_portal_cep\Lookup\CepLookupService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the Portal's AJAX endpoint for CEP lookups.
 *
 * The shared lookup service handles sources, caching, and address mapping.
 * This controller enforces the AJAX request contract and response headers.
 */
final class CepLookupController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'aculta_portal_cep.lookup')]
    private readonly CepLookupService $lookupService,
  ) {}

  /**
   * Returns address data for a valid AJAX lookup request.
   *
   * @param string $cep
   *   CEP path argument.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON address data or a not-found response.
   */
  public function lookup(string $cep, Request $request): JsonResponse {
    if (!$request->isXmlHttpRequest()) {
      throw new AccessDeniedHttpException();
    }
    $address = $this->lookupService->lookup($cep);
    if ($address === NULL) {
      $response = new JsonResponse(
        [
          'ok' => FALSE,
          'message' => (string) $this->t('Não encontramos este CEP. Confira o número ou preencha o endereço manualmente.'),
        ],
        404,
      );
      $response->headers->set('Cache-Control', 'private, no-store');
      return $response;
    }
    $response = new JsonResponse(['ok' => TRUE, 'data' => $address]);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('Vary', 'X-Requested-With, Cookie');
    return $response;
  }

}
