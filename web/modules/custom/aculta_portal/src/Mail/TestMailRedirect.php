<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Mail;

use Drupal\Core\Site\Settings;

/**
 * Redireciona os e-mails do ambiente de testes para um endereço interno de teste.
 *
 * Ativo só quando $settings['aculta_mail_redirect'] (destino interno) e $settings['aculta_mail_redirect_for']
 * (lista de endereços de contas de teste) estão definidos em settings.local.php, bloco do ambiente de teste, não
 * versionado. Só as mensagens endereçadas a esses endereços são redirecionadas; as demais seguem normalmente.
 * O destinatário original é registrado no assunto e no cabeçalho X-Aculta-Destino-Original.
 */
class TestMailRedirect {

  public function __construct(private readonly Settings $settings) {}

  /**
   * Endereço de redirecionamento, ou NULL quando o recurso está desativado.
   */
  public function destino(): ?string {
    $destino = trim((string) $this->settings->get('aculta_mail_redirect', ''));
    return $destino !== '' ? $destino : NULL;
  }

  /**
   * Endereços de contas de teste cujas mensagens são redirecionadas (minúsculas).
   *
   * @return string[]
   */
  public function enderecosDeTeste(): array {
    $lista = $this->settings->get('aculta_mail_redirect_for', []);
    return is_array($lista) ? array_map(static fn ($e): string => strtolower(trim((string) $e)), $lista) : [];
  }

  /**
   * Reescreve a mensagem para o endereço de teste, quando o destinatário está na lista de contas de teste.
   *
   * @param array<string, mixed> $message
   */
  public function aplicar(array &$message): void {
    $destino = $this->destino();
    if ($destino === NULL || empty($message['to'])) {
      return;
    }
    $original = (string) $message['to'];
    if (!in_array(strtolower(trim($original)), $this->enderecosDeTeste(), TRUE)) {
      return;
    }
    $message['to'] = $destino;
    unset($message['headers']['Cc'], $message['headers']['Bcc']);
    $message['subject'] = '[TESTE para ' . $original . '] ' . ($message['subject'] ?? '');
    $message['headers']['X-Aculta-Destino-Original'] = $original;
  }

}
