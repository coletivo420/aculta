<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Account;

/**
 * Regras de apelido e de nome de usuário (plano aculta_user, seção B1 e decisões de cadastro).
 *
 * - O nome de usuário da conta vira o apelido inicial (a pessoa pode trocar depois).
 * - Conta OAuth usa a parte local do e-mail recebido, sem o domínio (ex.: fulano.silva@gmail.com -> fulano.silva).
 * - Apelido: minúsculas, sem acento, letras, números e hífen, de 3 a 40 caracteres, único e fora das palavras reservadas.
 */
final class AccountNames {

  public const MIN = 3;

  public const MAX = 40;

  /** Palavras que não podem ser apelido (mesma lista do formulário de perfil). */
  public const RESERVED = [
    'admin', 'u', 'perfil', 'entrar', 'sair', 'cadastro', 'minha-conta', 'atividade', 'configuracoes',
    'dados', 'pedidos', 'seguranca', 'conexoes', 'cursos', 'curso', 'busca', 'carrinho', 'pagamento',
    'user', 'node', 'lms', 'api', 'autores',
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
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{' . (self::MIN - 1) . ',' . (self::MAX - 1) . '}$/', $apelido)
      && !in_array($apelido, self::RESERVED, TRUE);
  }

  /**
   * Parte local de um e-mail (antes do @), em minúsculas.
   */
  public static function localPart(string $email): string {
    $local = strtolower(explode('@', $email, 2)[0]);
    return mb_substr($local, 0, 60);
  }

}
