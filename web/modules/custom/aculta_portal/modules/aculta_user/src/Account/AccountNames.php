<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Account;

use Drupal\aculta_portal\Routing\PublicRouteSlug;

/**
 * Regras de apelido e de nome de usuário (plano aculta_user, seção B1 e decisões de cadastro).
 *
 * - O nome de usuário da conta vira o apelido inicial (a pessoa pode trocar depois).
 * - Conta OAuth usa a parte local do e-mail recebido, sem o domínio (ex.: fulano.silva@gmail.com -> fulano.silva).
 * - Apelido armazenado preserva a capitalização escolhida; seu segmento de URL é minúsculo.
 * - O segmento público (slug) usa letras minúsculas, números e hífen, de 3 a 40 caracteres, único e fora das palavras
 *   reservadas. A regra de minúsculas vale só para o slug, não para o apelido digitado.
 */
final class AccountNames {

  public const MIN = 3;

  public const MAX = 40;

  /** Palavras que não podem ser apelido (mesma lista do formulário de perfil). */
  public const RESERVED = [
    'admin', 'u', 'perfil', 'entrar', 'sair', 'cadastro', 'minha-conta', 'atividade', 'configuracoes',
    'dados', 'pedidos', 'seguranca', 'conexoes', 'cursos', 'curso', 'busca', 'carrinho', 'pagamento',
    'user', 'node', 'lms', 'api', 'autores',
    // Memorial coletivo do Fórum (ACULTA Fórum, F-D07); reservado antes da rota /u/{apelido}.
    'lendashistoricas',
  ];

  /**
   * Converte um texto já sem acento em apelido candidato (sem garantir unicidade).
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
    // A regra vale para o segmento de URL (slug). O apelido guardado preserva a capitalização escolhida.
    $slug = PublicRouteSlug::normalize($apelido);
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{' . (self::MIN - 1) . ',' . (self::MAX - 1) . '}$/', $slug)
      && !in_array($slug, self::RESERVED, TRUE);
  }

  /**
   * Normaliza um apelido para o segmento minúsculo da rota pública.
   *
   * A capitalização armazenada e apresentada não é alterada. Valores que não
   * podem formar uma rota válida retornam NULL, em vez de lançar erro no render.
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
