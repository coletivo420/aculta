<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\user\UserInterface;

/**
 * Contrato neutro do documento (CPF ou CNPJ) da pessoa em Meus dados > Outros Dados.
 *
 * O Portal não depende do submódulo que o implementa; a implementação é opcional.
 * Sem implementação registrada, a aba não aparece. A implementação faz a validação, a cifra e a gravação;
 * o Portal só recebe texto e estado. O documento é gravado uma única vez e não pode ser alterado depois.
 */
interface AccountDocumentInterface {

  /** TRUE quando é possível ler um documento salvo de forma cifrada neste ambiente. */
  public function isAvailable(): bool;

  /** TRUE quando as chaves e os campos necessários para uma nova gravação estão disponíveis. */
  public function canSave(): bool;

  /**
   * Valida a entrada (com ou sem pontuação) e devolve o valor canônico, ou NULL quando é inválida.
   * Nunca inclui a entrada em mensagens.
   */
  public function canonicalize(string $input): ?string;

  /**
   * Documento salvo do titular, mascarado (5 primeiros caracteres e '*' no restante), ou NULL quando não há nada salvo.
   * O valor completo nunca sai do serviço.
   *
   * @throws \RuntimeException
   *   Quando o valor salvo não pode ser lido neste ambiente.
   */
  public function savedDocument(UserInterface $account): ?string;

  /**
   * Grava o valor canônico uma única vez.
   *
   * @throws \LogicException
   *   Quando já existe documento salvo para o titular.
   * @throws \RuntimeException
   *   Quando a gravação não está disponível neste ambiente.
   */
  public function save(UserInterface $account, string $canonical): void;

}
