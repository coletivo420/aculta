<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Aviso\AvisoService;

/**
 * Acrescenta "Avisos/Mensagens" à navegação de Minha conta (conta.aculta) (ACULTA Messenger, F5.2).
 *
 * Usa os pontos de extensão do shell da conta do Portal (aculta_portal_account_sections e
 * aculta_portal_account_parent_sections). O contador de avisos não lidos aparece como badge.
 */
final class AccountSectionsHooks {

  public function __construct(
    private readonly AvisoService $avisos,
  ) {}

  /**
   * Acrescenta o item de avisos às seções de Minha conta.
   *
   * @param array $sections
   *   Seções da conta, por chave de rota.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Conta cuja navegação está sendo montada.
   */
  #[Hook('aculta_portal_account_sections_alter')]
  public function sectionsAlter(array &$sections, AccountInterface $account): void {
    if ($account->isAnonymous()) {
      return;
    }
    $sections['aculta_messenger.avisos_raiz'] = [
      'title' => (string) t('Avisos/Mensagens'),
      'route' => 'aculta_messenger.avisos_raiz',
      'icon' => 'bell',
      'badge_count' => $this->avisos->naoLidosTotal((int) $account->id()),
    ];
  }

  /**
   * Define a seção pai da central de avisos.
   *
   * @param array $parents
   *   Mapeamento de seções para suas seções pai.
   */
  #[Hook('aculta_portal_account_parent_sections_alter')]
  public function parentSectionsAlter(array &$parents): void {
    // A aba da central fica destacada no item Avisos/Mensagens.
    $parents['aculta_messenger.avisos'] = 'aculta_messenger.avisos_raiz';
  }

}
