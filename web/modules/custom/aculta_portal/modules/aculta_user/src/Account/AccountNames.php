<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Account;

use Drupal\aculta_portal\Routing\PublicRouteSlug;

/**
 * Regras de apelido e nome de usuário.
 *
 * O nome da conta vira o apelido inicial; OAuth usa a parte local do e-mail.
 * O apelido mantém a capitalização; o slug público fica em minúsculas.
 * Slugs aceitam de 3 a 40 letras ASCII, números ou hífens e não podem ser
 * reservados nem terminar com o marcador legado `-gr`. O sufixo de exibição
 * `#N` é convertido para `-N` no slug canônico.
 */
final class AccountNames {

  public const MIN = 3;

  public const MAX = 40;

  /**
   * Palavras reservadas, compartilhadas com os formulários de perfil.
   */
  public const RESERVED = [
    'admin', 'u', 'perfil', 'entrar', 'sair', 'cadastro', 'minha-conta', 'atividade', 'configuracoes',
    'dados', 'pedidos', 'seguranca', 'conexoes', 'cursos', 'curso', 'busca', 'carrinho', 'pagamento',
    'user', 'node', 'lms', 'api', 'autores',
    // Memorial coletivo do Fórum; reservado antes da rota /u/{apelido}.
    'lendashistoricas',
  ];

  /**
   * Converte texto sem acento em candidato (sem garantir unicidade).
   */
  public static function slug(string $ascii): string {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii) ?? '');
    $slug = trim($slug, '-');
    return mb_substr($slug, 0, self::MAX);
  }

  /**
   * Indica se o apelido atende às regras de formato e não é reservado.
   */
  public static function isValid(string $apelido): bool {
    if (self::hasHashSuffix($apelido)) {
      return self::isHashSuffixNickname($apelido);
    }
    $slug = self::routeSlugFromDisplay($apelido);
    return $slug !== NULL
      && (bool) preg_match('/^[a-z0-9][a-z0-9-]{' . (self::MIN - 1) . ',' . (self::MAX - 1) . '}$/', $slug)
      && !str_ends_with($slug, '-gr')
      && !in_array($slug, self::RESERVED, TRUE);
  }

  /**
   * Indicates whether a display nickname uses the supported terminal `#N`.
   */
  public static function isHashSuffixNickname(string $apelido): bool {
    if (!preg_match('/^(.+)#((?:[2-9]|[1-9][0-9]+))$/', $apelido, $matches)) {
      return FALSE;
    }
    $base = $matches[1];
    $number = $matches[2];
    $slug = PublicRouteSlug::normalize($base . '-' . $number);
    return self::isValid($base) && self::isCanonicalSlugValid($slug);
  }

  /**
   * Builds a collision label without exceeding the public slug limit.
   */
  public static function withAutomaticSuffix(string $base, int $number): string {
    $suffix = '#' . $number;
    return mb_substr($base, 0, self::MAX - strlen($suffix)) . $suffix;
  }

  /**
   * Normaliza um apelido para o slug minúsculo da rota pública.
   *
   * A capitalização armazenada e apresentada não é alterada. Valores que não
   * inválidos retornam NULL, sem causar erro durante o render.
   */
  public static function publicRouteSlug(string $apelido): ?string {
    return self::isValid($apelido) ? self::routeSlugFromDisplay($apelido) : NULL;
  }

  /**
   * Determines whether a display value ends in the supported `#N` form.
   */
  private static function hasHashSuffix(string $value): bool {
    return preg_match('/^.+#(?:[2-9]|[1-9][0-9]+)$/', $value) === 1;
  }

  /**
   * Converts a display value to its canonical lowercase route slug.
   */
  private static function routeSlugFromDisplay(string $value): ?string {
    if (preg_match('/^(.+)#((?:[2-9]|[1-9][0-9]+))$/', $value, $matches)) {
      return PublicRouteSlug::normalize($matches[1] . '-' . $matches[2]);
    }
    return PublicRouteSlug::normalize($value);
  }

  /**
   * Validates the canonical public slug grammar and reserved names.
   */
  private static function isCanonicalSlugValid(string $slug): bool {
    return strlen($slug) <= self::MAX
      && (bool) preg_match('/^[a-z0-9][a-z0-9-]{' . (self::MIN - 1) . ',' . (self::MAX - 1) . '}$/', $slug)
      && !str_ends_with($slug, '-gr')
      && !in_array($slug, self::RESERVED, TRUE);
  }

  /**
   * Parte local de um e-mail (antes do @), em minúsculas.
   */
  public static function localPart(string $email): string {
    $local = strtolower(explode('@', $email, 2)[0]);
    return mb_substr($local, 0, 60);
  }

}
