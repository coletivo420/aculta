<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Type;

/**
 * Tipo de e-mail registrado por um módulo consumidor (serviço com tag `aculta_mailer.type`).
 *
 * Declara as variáveis que modelos (Config Entity `aculta_mailer_template`) podem usar, e o texto padrão com
 * placeholders `{{ variavel }}`. Só variáveis declaradas são substituídas. URLs e textos chegam prontos no contexto:
 * o tipo não consulta entidades nem rotas.
 */
interface MailTypeInterface {

  /**
   * Identificador do tipo em snake_case, igual à chave enviada ao Core.
   */
  public function id(): string;

  /**
   * Variáveis disponíveis para o modelo, por nome.
   *
   * @return array<string, array{label: string, sample: string}>
   *   Rótulo para o editor e valor de exemplo para a prévia.
   */
  public function variables(): array;

  /**
   * Assunto padrão, com placeholders.
   */
  public function defaultSubject(): string;

  /**
   * Corpo padrão em texto simples, com placeholders.
   */
  public function defaultBody(): string;

}
