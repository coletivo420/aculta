<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Analytics;

use Drupal\Core\Entity\EntityInterface;

/**
 * Classificação analítica estável de uma discussão.
 *
 * O escopo é uma propriedade do conteúdo e do contexto, nunca da conta que
 * executa a consulta (cron, administrador ou visitante). Apenas
 * `public_forum` autoriza a inclusão em indicadores explicitamente públicos.
 */
final readonly class ForumAnalyticsScope {

  /**
   * Tópico independente do Fórum público, sem Group restrito.
   */
  public const PUBLIC_FORUM = 'public_forum';

  /**
   * Tópico do Fórum restrito por Group direto ou herdado da categoria.
   */
  public const RESTRICTED_FORUM = 'restricted_forum';

  /**
   * Discussão contextual de publicação da Revista.
   */
  public const PUBLICATION = 'publication';

  /**
   * Discussão contextual de verbete da Wiki.
   */
  public const WIKI = 'wiki';

  /**
   * Discussão contextual de curso ou turma do LMS.
   */
  public const LMS = 'lms';

  /**
   * Escopo não determinável ou fora do Fórum.
   */
  public const UNKNOWN = 'unknown';

  /**
   * Escopos aceitos pelo resolvedor.
   */
  public const SCOPES = [
    self::PUBLIC_FORUM,
    self::RESTRICTED_FORUM,
    self::PUBLICATION,
    self::WIKI,
    self::LMS,
    self::UNKNOWN,
  ];

  public function __construct(
    public string $scope,
    public ?EntityInterface $host = NULL,
    public ?string $reason = NULL,
  ) {
    if (!in_array($scope, self::SCOPES, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Escopo analítico inválido: %s.', $scope));
    }
  }

  /**
   * Indica se o conteúdo pode alimentar indicadores públicos do Fórum.
   */
  public function isPublicForum(): bool {
    return $this->scope === self::PUBLIC_FORUM;
  }

  /**
   * Indica escopo conhecido, ainda que não público.
   */
  public function isKnown(): bool {
    return $this->scope !== self::UNKNOWN;
  }

}
