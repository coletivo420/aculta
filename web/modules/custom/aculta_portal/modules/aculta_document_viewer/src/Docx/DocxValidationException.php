<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Docx;

/**
 * Pacote DOCX rejeitado pela validação estrutural.
 *
 * O código (`reason`) é estável e pode ser usado em logs e testes. A mensagem
 * não deve ser exibida ao público em produção.
 */
final class DocxValidationException extends \RuntimeException {

  /**
   * Constructs a rejection.
   *
   * @param string $reason
   *   Código estável da rejeição, por exemplo `zip_bomb` ou `missing_part`.
   * @param string $message
   *   Detalhe para log.
   */
  public function __construct(
    private readonly string $reason,
    string $message,
  ) {
    parent::__construct($message);
  }

  /**
   * Returns the stable rejection code.
   */
  public function getReason(): string {
    return $this->reason;
  }

}
