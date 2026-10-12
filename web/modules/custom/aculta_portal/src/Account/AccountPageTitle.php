<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Título da aba da Visão geral da Minha conta.
 *
 * A Visão geral é a página inicial do domínio da conta. Nessa rota o Metatag não tem título de página e a aba mostra
 * só o nome do site. As outras seções saem como "Meus pedidos | Conta ACULTA"; a Visão geral sai no mesmo padrão.
 * Chamado por AppearanceHooks::preprocessHtml (um único hook preprocess_html no módulo).
 */
final class AccountPageTitle {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly ConfigFactoryInterface $configs,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Contrato neutro: aculta_head_title = texto do <title>. O tema aplica no preprocess_html (o Metatag roda antes do
   * tema e sobrescreveria um valor definido no módulo).
   */
  public function applyHeadTitle(array &$variables): void {
    if ($this->routeMatch->getRouteName() !== 'aculta_portal.dashboard') {
      return;
    }
    $name = (string) $this->configs->get('system.site')->get('name');
    $variables['aculta_head_title'] = (string) $this->translation->translate('Visão geral') . ' | ' . $name;
  }

}
