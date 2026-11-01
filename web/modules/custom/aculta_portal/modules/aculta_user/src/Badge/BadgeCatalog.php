<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Badge;

/**
 * Identificadores estáveis das conquistas existentes.
 *
 * Automáticas: concedidas por regra e nunca revogadas. Manuais: concedidas e revogadas só pelo administrador.
 */
final class BadgeCatalog {

  public const APOIADOR = 'apoiador';
  public const ASSOCIADO = 'associado';
  public const FUNDADORE = 'fundadore';
  public const PRIMEIRO_COMENTARIO = 'primeiro_comentario_forum';
  public const PRIMEIRA_EDICAO_WIKI = 'primeira_edicao_wiki';
  public const PRIMEIRO_CURSO = 'primeiro_curso';
  public const PERFIL_BASICO = 'perfil_basico';

  public const AUTOMATICA = 'automatica';
  public const MANUAL = 'manual';

  /** @return array<string, array{label: string, origem: string}> */
  public static function all(): array {
    return [
      self::FUNDADORE => ['label' => 'Fundadore', 'origem' => self::MANUAL],
      self::APOIADOR => ['label' => 'Apoiadore', 'origem' => self::AUTOMATICA],
      self::ASSOCIADO => ['label' => 'Associado/a Antiproibicionista', 'origem' => self::MANUAL],
      self::PRIMEIRO_COMENTARIO => ['label' => 'Primeiro comentário no Fórum', 'origem' => self::AUTOMATICA],
      self::PRIMEIRA_EDICAO_WIKI => ['label' => 'Primeira edição na Wiki', 'origem' => self::AUTOMATICA],
      self::PRIMEIRO_CURSO => ['label' => 'Primeiro curso realizado', 'origem' => self::AUTOMATICA],
      self::PERFIL_BASICO => ['label' => 'Perfil básico completo', 'origem' => self::AUTOMATICA],
    ];
  }

  public static function isManual(string $badge): bool {
    return (self::all()[$badge]['origem'] ?? '') === self::MANUAL;
  }

}
