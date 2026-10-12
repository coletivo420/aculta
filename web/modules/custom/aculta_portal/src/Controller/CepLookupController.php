<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\cep_autocomplete\Service\ViaCepClient;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Consulta de CEP do Portal (substitui o endpoint do módulo contrib no mesmo caminho).
 *
 * Só responde a requisição AJAX (X-Requested-With), e devolve apenas os campos do endereço: não expõe a resposta
 * bruta do ViaCEP. A consulta usa o cliente do módulo contrib (cache do serviço).
 */
final class CepLookupController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'cep_autocomplete.viacep_client')]
    private readonly ViaCepClient $client,
  ) {}

  public function lookup(string $cep, Request $request): JsonResponse {
    if (!$request->isXmlHttpRequest()) {
      throw new AccessDeniedHttpException();
    }
    try {
      $raw = $this->client->lookup($cep);
    }
    catch (\Throwable) {
      return new JsonResponse(['ok' => FALSE, 'message' => 'Lookup failed'], 400);
    }
    $response = new JsonResponse([
      'ok' => TRUE,
      'data' => [
        'postal_code' => preg_replace('/\D+/', '', $raw['cep'] ?? $cep),
        'administrative_area' => $raw['uf'] ?? '',
        'locality' => $raw['localidade'] ?? '',
        'address_line1' => $raw['logradouro'] ?? '',
        'address_line2' => trim(($raw['bairro'] ?? '') . '  ' . ($raw['complemento'] ?? '')),
      ],
    ]);
    $response->setMaxAge(86400);
    $response->setSharedMaxAge(86400);
    return $response;
  }

}
