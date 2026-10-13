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
   * Retorna os itens públicos de um perfil.
   *
   * @param array{publico: bool, apelido: string, exibir_foto: bool, exibir_bio: bool, exibir_email: bool, exibir_redes: bool} $perfil
   *   Valores do perfil: visibilidade do perfil inteiro e escolha de cada item.
   *
   * @return array<string, bool>
   *   Mapa item => TRUE para os itens que podem aparecer. Vazio se o perfil for privado.
   */
  public function publicItems(array $perfil): array {
    if (empty($perfil['publico']) || trim((string) ($perfil['apelido'] ?? '')) === '') {
      return [];
    }

    $items = ['apelido' => TRUE];
    foreach (['foto' => 'exibir_foto', 'bio' => 'exibir_bio', 'email' => 'exibir_email', 'redes' => 'exibir_redes'] as $item => $flag) {
      if (!empty($perfil[$flag])) {
        $items[$item] = TRUE;
      }
    }
    return $items;
  }

}
