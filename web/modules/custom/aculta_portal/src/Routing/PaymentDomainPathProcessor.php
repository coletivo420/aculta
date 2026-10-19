<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Routing;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * URLs de retorno e de notificação do pagamento sempre no domínio principal (decisão de 2026-10-17).
 *
 * O fluxo de apoio e assinatura roda no apoio, mas o Mercado Pago só fala com o MAIN: a notificação é servidor a
 * servidor, e o retorno do navegador volta ao MAIN, onde a sessão compartilhada (cookie_domain do ambiente) continua
 * válida. Caminhos de pagamento gerados com "absolute" recebem o endereço do domínio principal.
 */
final class PaymentDomainPathProcessor implements OutboundPathProcessorInterface {

  /** Padrões (sem o host) dos caminhos que recebem o domínio principal. */
  private const CAMINHOS_DE_PAGAMENTO = [
    '#^/integracoes/pagamentos/[^/]+/notificacao$#',
    '#^/checkout/[^/]+/[^/]+/(return|cancel)$#',
    '#^/commerce-mercado-pago/checkout-return$#',
  ];

  public function __construct(private readonly DomainPurposeManager $purposes) {}

  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!self::ehCaminhoDePagamento($path)) {
      return $path;
    }
    $principal = $this->purposes->getDomain('main')?->getPath();
    if ($principal === NULL) {
      return $path;
    }
    $options['absolute'] = TRUE;
    $options['base_url'] = rtrim($principal, '/');
    return $path;
  }

  private static function ehCaminhoDePagamento(string $path): bool {
    foreach (self::CAMINHOS_DE_PAGAMENTO as $padrao) {
      if (preg_match($padrao, $path) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
