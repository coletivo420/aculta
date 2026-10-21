<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Visitor;

/**
 * Identificador pseudonimo de visitante para limites de frequência.
 *
 * O IP nunca é guardado em claro. Usa HMAC com o salt do site e um escopo, para que
 * a mesma pessoa tenha chaves diferentes em limites diferentes. A chave só fica na
 * tabela de controle de frequência do Core, pela janela do limite.
 */
final readonly class VisitorKey {

  public function __construct(
    private string $salt,
  ) {}

  /**
   * Chave pseudonima de um visitante dentro de um escopo.
   *
   * @param string $ip
   *   Endereço do cliente, usado só para compor o HMAC.
   * @param string $scope
   *   Escopo do limite, por exemplo `client` ou `node:42`.
   */
  public function identifier(string $ip, string $scope): string {
    return hash_hmac('sha256', $scope . '|' . $ip, $this->salt);
  }

}
