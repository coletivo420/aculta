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
 * Endpoint AJAX de consulta de CEP do Portal. Só responde a requisições AJAX (X-Requested-With).
 *
 * A consulta em si (fontes, cache e mapeamento) está em CepLookupService, compartilhado com os módulos PHP. Este
 * controlador só cuida do HTTP: formato da resposta, cabeçalhos de cache e a verificação AJAX.
 */
final class CepLookupController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'aculta_portal_cep.lookup')]
    private readonly CepLookupService $lookupService,
  ) {}

  public function lookup(string $cep, Request $request): JsonResponse {
    if (!$request->isXmlHttpRequest()) {
      throw new AccessDeniedHttpException();
    }
    $address = $this->lookupService->lookup($cep);
    if ($address === NULL) {
      $response = new JsonResponse(['ok' => FALSE, 'message' => (string) $this->t('Não encontramos este CEP. Confira o número ou preencha o endereço manualmente.')], 404);
      $response->headers->set('Cache-Control', 'private, no-store');
      return $response;
    }
    $response = new JsonResponse(['ok' => TRUE, 'data' => $address]);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('Vary', 'X-Requested-With, Cookie');
    return $response;
  }

}
