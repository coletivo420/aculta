<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Hook;

use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Renderiza os e-mails do ACULTA Mailer a partir do tipo registrado.
 */
final class MailHooks {

  public function __construct(
    private readonly MailTypeRegistry $types,
    private readonly MailTemplateRenderer $renderer,
  ) {}

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if (!$this->types->has($key)) {
      return;
    }
    $type = $this->types->get($key);
    $context = $params['context'] ?? [];
    $rendered = $this->renderer->render($type, $context);
    $message['subject'] = $rendered['subject'];
    $message['body'][] = $rendered['body'];
    if ($rendered['html']) {
      // Corpo HTML restrito: o envio o usa na parte HTML; a parte de texto segue pelo format().
      $message['aculta_html_body'] = $rendered['body'];
    }
    if ($rendered['layout']) {
      // Com layout, o cabeçalho e o rodapé da marca já estão no corpo: o envelope não repete o rodapé.
      $message['aculta_layout'] = TRUE;
    }
    if (isset($params['correlation_id'])) {
      // Cabeçalho próprio para rastrear a mensagem do Drupal até o provedor (ver docs/ARCHITECTURE.md).
      $message['headers']['X-Aculta-Mail-ID'] = (string) $params['correlation_id'];
    }
    if (!empty($params['priority'])) {
      // Prioridade definida pela política (1 alta a 5 baixa); RFC 2156.
      $message['headers']['X-Priority'] = (string) (int) $params['priority'];
    }
    if (!empty($params['transport'])) {
      // Escolhe o transporte desta mensagem; lido e removido pelo MultiTransport do mailer_transport.
      $message['headers']['X-Transport'] = (string) $params['transport'];
    }
  }

}
