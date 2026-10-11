<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Remove o ID do usuário da URL gerada para as listas do painel (/pedidos e /pagamentos).
 *
 * As rotas do Commerce recebem o usuário como parâmetro; sem este processador, o Drupal o colocaria na query
 * (`/pedidos?user=1`). O painel usa sempre a sessão, então o endereço gerado fica limpo.
 */
final class AccountListPathProcessor implements OutboundPathProcessorInterface {

  private const PATHS = ['/pedidos', '/pagamentos'];

  public function processOutbound($path, &$options = [], ?Request $request = NULL, $bubbleable_metadata = NULL): string {
    if (in_array($path, self::PATHS, TRUE)) {
      unset($options['query']['user']);
    }
    return $path;
  }

}
