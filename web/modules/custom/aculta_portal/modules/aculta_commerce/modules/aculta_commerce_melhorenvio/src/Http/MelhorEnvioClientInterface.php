<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

/**
 * Contrato do cliente REST do Melhor Envio.
 *
 * Os consumidores (cotação, simulador) dependem desta interface. Os testes trocam o cliente por mock; a chamada real
 * exige credencial válida no ambiente ativo.
 */
interface MelhorEnvioClientInterface {

  /**
   * URL de consentimento para o administrador autorizar o app.
   *
   * @param string $state
   *   Valor anti-CSRF devolvido pelo Melhor Envio no retorno.
   *
   * @return string
   *   URL completa de autorização no ambiente ativo.
   */
  public function authorizationUrl(string $state): string;

  /**
   * Troca o código de autorização por tokens e os guarda no ambiente ativo.
   *
   * @param string $code
   *   Código devolvido pelo Melhor Envio no retorno da autorização.
   */
  public function exchangeAuthorizationCode(string $code): void;

  /**
   * Requisição autenticada à API.
   *
   * @param string $method
   *   Método HTTP, por exemplo GET ou POST.
   * @param string $path
   *   Caminho da API, começando por barra.
   * @param array<string, mixed>|null $json
   *   Corpo JSON da requisição, ou NULL quando não há corpo.
   * @param bool $idempotent
   *   TRUE somente para operações repetíveis sem efeito colateral. Habilita retentativas.
   *
   * @return array<mixed>
   *   JSON decodificado da resposta.
   *
   * @throws \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException
   *   Credencial ausente, falha de rede ou resposta de erro da API.
   */
  public function request(string $method, string $path, ?array $json = NULL, bool $idempotent = FALSE): array;

  /**
   * Indica se as duas chaves do ambiente ativo existem. Não chama a API.
   */
  public function hasCredentials(): bool;

}
