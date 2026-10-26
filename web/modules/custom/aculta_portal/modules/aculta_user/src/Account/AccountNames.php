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
 * reservados nem terminar com o marcador legado `-gr`.
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
    // O slug é minúsculo; o apelido armazenado mantém sua capitalização.
    $slug = PublicRouteSlug::normalize($apelido);
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{' . (self::MIN - 1) . ',' . (self::MAX - 1) . '}$/', $slug)
      && !str_ends_with($slug, '-gr')
      && !in_array($slug, self::RESERVED, TRUE);
  }

  /**
   * Normaliza um apelido para o slug minúsculo da rota pública.
   *
   * A capitalização armazenada e apresentada não é alterada. Valores que não
   * inválidos retornam NULL, sem causar erro durante o render.
   */
  public static function publicRouteSlug(string $apelido): ?string {
    $slug = PublicRouteSlug::normalize($apelido);
    return self::isValid($slug) ? $slug : NULL;
  }

  /**
   * Parte local de um e-mail (antes do @), em minúsculas.
   */
  public static function localPart(string $email): string {
    $local = strtolower(explode('@', $email, 2)[0]);
    return mb_substr($local, 0, 60);
  }

}
