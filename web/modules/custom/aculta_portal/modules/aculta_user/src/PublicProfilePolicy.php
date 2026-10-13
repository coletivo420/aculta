<?php

declare(strict_types=1);

namespace Drupal\aculta_user;

/**
 * Decide, campo a campo, o que do perfil participante pode aparecer em público.
 *
 * Regras do responsável (2026-10-13, plano docs/portal/ACULTA-USER-PLAN.md, seção B2):
 * - o apelido é público por padrão; o perfil inteiro pode ser tornado privado;
 * - foto, bio, e-mail e redes sociais só aparecem se a própria pessoa escolher exibi-los;
 * - cidade, UF, nome completo, WhatsApp, endereço, CPF e RG nunca são públicos;
 * - badges entram na fase U4 e ainda não têm dado nem campo.
 *
 * Função pura, sem entidades: recebe valores já lidos e devolve só o que pode ser mostrado.
 */
final class PublicProfilePolicy {

  /**
   * Chaves de saída aceitas.
   */
  public const ITEMS = ['apelido', 'foto', 'bio', 'email', 'redes'];

  /**
   * Papéis de autor e editor: o perfil é obrigatoriamente público (regra do responsável, 2026-10-13).
   *
   * Editor de conteúdo (autoria da Revista) e Editor da Wiki420 (autoria de verbetes).
   */
  public const PAPEIS_PUBLICO_OBRIGATORIO = ['content_editor', 'wiki_editor'];

  /**
   * Indica se os papéis da conta tornam o perfil obrigatoriamente público.
   *
   * @param string[] $roles
   *   Papéis da conta (IDs).
   */
  public static function isObrigatorioPublico(array $roles): bool {
    return array_intersect($roles, self::PAPEIS_PUBLICO_OBRIGATORIO) !== [];
  }

  /**
   * Retorna os itens públicos de um perfil.
   *
   * Perfil público (padrão de todos) segue a escolha de cada item. Perfil privado, ou sem escolha, mostra
   * somente foto e apelido. Sem apelido, nada aparece.
   *
   * @param array{publico: bool, obrigatorio?: bool, apelido: string, exibir_foto: bool, exibir_bio: bool, exibir_email: bool, exibir_redes: bool} $perfil
   *   Valores do perfil: visibilidade do perfil inteiro, obrigação de público por papel e escolha de cada item.
   *
   * @return array<string, bool>
   *   Mapa item => TRUE para os itens que podem aparecer. Vazio se não houver apelido.
   */
  public function publicItems(array $perfil): array {
    if (trim((string) ($perfil['apelido'] ?? '')) === '') {
      return [];
    }

    $publico = !empty($perfil['publico']) || !empty($perfil['obrigatorio']);
    if (!$publico) {
      // Perfil privado: somente foto e apelido.
      return ['apelido' => TRUE, 'foto' => TRUE];
    }

    // A foto sempre aparece no perfil público; bio, e-mail e redes dependem da escolha da pessoa.
    $items = ['apelido' => TRUE, 'foto' => TRUE];
    foreach (['bio' => 'exibir_bio', 'email' => 'exibir_email', 'redes' => 'exibir_redes'] as $item => $flag) {
      if (!empty($perfil[$flag])) {
        $items[$item] = TRUE;
      }
    }
    return $items;
  }

}
