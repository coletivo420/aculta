<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\Mail;

use Drupal\aculta_mailer\Entity\MailTransport;
use Drupal\aculta_mailer\Mail\LegacyMessageConverter;
use Drupal\aculta_mailer\Transport\MailTransportSender;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailInterface;
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
   * Envia pela entidade de transporte escolhida. Sem corpo nem destinatário no registro de erro.
   */
  public function mail(array $message) {
    $transport = $this->entityTypes->getStorage(MailTransport::ENTITY_TYPE)->load($this->converter->transportId($message));
    if (!$transport instanceof MailTransport) {
      $this->logger->error('Transporte da camada de envio ausente: @id.', ['@id' => $this->converter->transportId($message)]);
      return FALSE;
    }
    $from = (string) $this->configFactory->get('system.site')->get('mail');
    $result = $this->sender->send($transport, $this->converter->toEmail($message, $from));
    if (!$result['sent']) {
      $this->logger->error('Envio pela camada E-Mails ACULTA recusado pelo transporte @id: @message', [
        '@id' => $transport->id(),
        '@message' => $result['message'],
      ]);
    }
    return $result['sent'];
  }

}
