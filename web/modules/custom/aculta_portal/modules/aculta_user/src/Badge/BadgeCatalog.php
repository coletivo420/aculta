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

  // Marcos de curtidas no mesmo item (Discourse Nice/Good/Great). Concedidos só pelo aculta_forum.
  public const TOPICO_LEGAL_FORUM = 'topico_legal_forum';
  public const TOPICO_BOM_FORUM = 'topico_bom_forum';
  public const TOPICO_EXCELENTE_FORUM = 'topico_excelente_forum';
  public const RESPOSTA_LEGAL_FORUM = 'resposta_legal_forum';
  public const RESPOSTA_BOA_FORUM = 'resposta_boa_forum';
  public const RESPOSTA_EXCELENTE_FORUM = 'resposta_excelente_forum';

  public const AUTOMATICA = 'automatica';
  public const MANUAL = 'manual';

  /**
   * Retorna as conquistas existentes com rótulo e origem.
   *
   * @return array<string, array{label: string, origem: string}>
   *   Conquistas indexadas pelo identificador estável.
   */
  public static function all(): array {
    return [
      self::FUNDADORE => ['label' => 'Fundadore', 'origem' => self::MANUAL],
      self::APOIADOR => ['label' => 'Apoiadore', 'origem' => self::AUTOMATICA],
      self::ASSOCIADO => ['label' => 'Associado/a Antiproibicionista', 'origem' => self::MANUAL],
      self::PRIMEIRO_COMENTARIO => ['label' => 'Primeiro comentário no Fórum', 'origem' => self::AUTOMATICA],
      self::PRIMEIRA_EDICAO_WIKI => ['label' => 'Primeira edição na Wiki', 'origem' => self::AUTOMATICA],
      self::PRIMEIRO_CURSO => ['label' => 'Primeiro curso realizado', 'origem' => self::AUTOMATICA],
      self::PERFIL_BASICO => ['label' => 'Perfil básico completo', 'origem' => self::AUTOMATICA],
      self::TOPICO_LEGAL_FORUM => ['label' => 'Tópico legal (10 curtidas)', 'origem' => self::AUTOMATICA],
      self::TOPICO_BOM_FORUM => ['label' => 'Tópico bom (25 curtidas)', 'origem' => self::AUTOMATICA],
      self::TOPICO_EXCELENTE_FORUM => ['label' => 'Tópico excelente (50 curtidas)', 'origem' => self::AUTOMATICA],
      self::RESPOSTA_LEGAL_FORUM => ['label' => 'Resposta legal (10 curtidas)', 'origem' => self::AUTOMATICA],
      self::RESPOSTA_BOA_FORUM => ['label' => 'Resposta boa (25 curtidas)', 'origem' => self::AUTOMATICA],
      self::RESPOSTA_EXCELENTE_FORUM => ['label' => 'Resposta excelente (50 curtidas)', 'origem' => self::AUTOMATICA],
    ];
  }

  /**
   * Informa se a conquista é concedida e revogada manualmente.
   *
   * @param string $badge
   *   Identificador da conquista.
   *
   * @return bool
   *   VERDADEIRO se a conquista for manual.
   */
  public static function isManual(string $badge): bool {
    return (self::all()[$badge]['origem'] ?? '') === self::MANUAL;
  }

}
