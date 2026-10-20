<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\Mail;

use Drupal\aculta_mailer\Entity\MailTransport;
use Drupal\aculta_mailer\Api\PublicUrlInterface;
use Drupal\aculta_mailer\Delivery\MailPayloadRegistry;
use Drupal\aculta_mailer\Delivery\ProviderMessageRegistry;
use Drupal\aculta_mailer\Delivery\SmtpFailureRegistry;
use Drupal\aculta_mailer\Log\SingleUseLinkMail;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\aculta_mailer\Mail\LegacyMessageConverter;
use Drupal\aculta_mailer\Transport\MailTransportSender;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin de envio do Core pela camada de envio E-Mails ACULTA (F15).
 *
 * Não é o padrão do site: `system.mail` continua no contrib até a troca, depois da equivalência comprovada.
 */
#[Mail(
  id: 'aculta_mailer',
  label: new TranslatableMarkup('Camada de envio E-Mails ACULTA'),
  description: new TranslatableMarkup('Envia pelos transportes aculta_mailer_transport.'),
)]
final class AcultaMailerMail implements MailInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly MailTransportSender $sender,
    private readonly LegacyMessageConverter $converter,
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
    private readonly RendererInterface $renderer,
    private readonly SmtpFailureRegistry $failures,
    private readonly ProviderMessageRegistry $providerMessages,
    private readonly MailPayloadRegistry $payloads,
    private readonly TimeInterface $time,
    private readonly ?PublicUrlInterface $publicUrls = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(MailTransportSender::class),
      $container->get(LegacyMessageConverter::class),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('logger.channel.aculta_mailer'),
      $container->get('renderer'),
      $container->get('aculta_mailer.smtp_failures'),
      $container->get('aculta_mailer.provider_messages'),
      $container->get('aculta_mailer.payloads'),
      $container->get('datetime.time'),
      $container->has(PublicUrlInterface::class) ? $container->get(PublicUrlInterface::class) : NULL,
    );
  }

  /**
   * Junta o corpo e converte o HTML em texto, como o envio padrão do Core.
   */
  public function format(array $message) {
    $message['body'] = implode("\n\n", $message['body']);
    $message['body'] = MailFormatHelper::htmlToText($message['body']);
    return $message;
  }

  /**
   * Envelope HTML pelo hook de tema; o logo é a URL absoluta do servidor.
   */
  private function renderHtml(array $message): string {
    $body = Html::escape((string) ($message['body'] ?? ''));
    $build = [
      '#theme' => 'aculta_mailer_wrap',
      '#body' => Markup::create(nl2br($body, FALSE)),
      '#subject' => (string) ($message['subject'] ?? ''),
      '#type' => 'core',
      '#sub_type' => (string) ($message['key'] ?? ''),
      '#is_html' => TRUE,
      '#public_base' => $this->publicUrls !== NULL ? $this->publicUrls->absolute('/') : '',
    ];
    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * Envia pela entidade de transporte escolhida. Sem corpo nem destinatário no registro de erro.
   */
  public function mail(array $message) {
    $transport = $this->entityTypes->getStorage(MailTransport::ENTITY_TYPE)->load($this->converter->transportId($message));
    if (!$transport instanceof MailTransport) {
      $this->logger->error('Transporte da camada de envio ausente: @id.', ['@id' => $this->converter->transportId($message)]);
      return FALSE;
    }
    $from = (string) $this->configFactory->get('system.site')->get('mail');
    // Imagens do envelope são carregadas do servidor (URL absoluta); nada é anexado nem embutido.
    $html = $this->renderHtml($message);
    // Conteúdo para o reenvio, só em memória até o fim da requisição: a falha o leva ao registro, cifrado.
    // Anexos não entram: os e-mails do sistema não têm anexos.
    // Link de uso único: o conteúdo guardado não tem link e expira em 24 horas (SingleUseLinkMail).
    $singleUse = SingleUseLinkMail::applies((string) ($message['module'] ?? ''), (string) ($message['key'] ?? ''));
    $this->payloads->remember((string) ($message['headers']['X-Aculta-Mail-ID'] ?? ''), [
      'to' => (string) $message['to'],
      'from' => $from,
      'subject' => (string) ($message['subject'] ?? ''),
      'body' => $singleUse ? SingleUseLinkMail::withoutLinks((string) ($message['body'] ?? '')) : (string) ($message['body'] ?? ''),
      'html' => $singleUse ? SingleUseLinkMail::withoutLinks($html) : $html,
      'reply-to' => (string) ($message['reply-to'] ?? ''),
      'headers' => array_filter($message['headers'] ?? [], 'is_scalar'),
      'expires' => $singleUse ? $this->time->getRequestTime() + SingleUseLinkMail::TTL : NULL,
    ]);
    $result = $this->sender->send($transport, $this->converter->toEmail($message, $from, $html));
    if ($result['sent'] && $result['provider_id'] !== NULL) {
      $this->providerMessages->remember((string) ($message['headers']['X-Aculta-Mail-ID'] ?? ''), $result['provider_id']);
    }
    if (!$result['sent']) {
      // Mesmo registro de falha que o envio do módulo usa para classificar (código SMTP e texto completo).
      $this->failures->remember((string) ($message['headers']['X-Aculta-Mail-ID'] ?? ''), $result['code'], $result['message']);
      $this->logger->error('Envio pela camada E-Mails ACULTA recusado pelo transporte @id: @message', [
        '@id' => $transport->id(),
        '@message' => $result['message'],
      ]);
    }
    return $result['sent'];
  }

}
