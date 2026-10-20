<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Type;

/**
 * Localiza tipos de e-mail por ID. Os tipos chegam pela tag `aculta_mailer.type`.
 */
final class MailTypeRegistry {

  /**
   * Tipos indexados pelo ID.
   *
   * @var array<string, \Drupal\aculta_mailer\Type\MailTypeInterface>
   */
  private array $types = [];

  /**
   * Indexa os tipos pelo ID; IDs repetidos são erro de configuração.
   *
   * @param iterable<\Drupal\aculta_mailer\Type\MailTypeInterface> $types
   *   Tipos registrados por módulos consumidores.
   */
  public function __construct(iterable $types) {
    foreach ($types as $type) {
      if (isset($this->types[$type->id()])) {
        throw new \LogicException(sprintf('Tipo de e-mail duplicado: %s.', $type->id()));
      }
      $this->types[$type->id()] = $type;
    }
  }

  /**
   * Indica se o tipo está registrado.
   */
  public function has(string $id): bool {
    return isset($this->types[$id]);
  }

  /**
   * Tipo registrado; lança exceção se o ID não existir.
   */
  public function get(string $id): MailTypeInterface {
    if (!isset($this->types[$id])) {
      throw new \InvalidArgumentException(sprintf('Tipo de e-mail não registrado: %s.', $id));
    }
    return $this->types[$id];
  }

}
