<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Type;

/**
 * Tipo de e-mail registrado por um módulo consumidor (serviço com tag `aculta_mailer.type`).
 *
 * O tipo recebe somente o contexto que o consumidor montou, com escalares. Não consulta entidades nem rotas: URLs
 * chegam prontas no contexto, o que mantém subject e body testáveis sem bootstrap.
 */
interface MailTypeInterface {

  /**
   * Identificador do tipo em snake_case, igual à chave enviada ao Core.
   */
  public function id(): string;

  /**
   * Assunto já traduzido.
   *
   * @param array<string, scalar|null> $context
   *   Contexto da mensagem.
   */
  public function subject(array $context): string;

  /**
   * Corpo em texto simples, já traduzido e com o contexto aplicado.
   *
   * @param array<string, scalar|null> $context
   *   Contexto da mensagem.
   */
  public function body(array $context): string;

}
