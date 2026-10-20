<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\QueueWorker;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\DeliveryPlanner;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Queue\MailQueue;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Envia uma mensagem da fila; em falha temporária, reagenda com o atraso do DeliveryPlanner.
 */
#[QueueWorker(
  id: MailQueue::QUEUE_NAME,
  title: new TranslatableMarkup('Envio de e-mails transacionais'),
  cron: ['time' => 30],
)]
final class MailSendWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly AcultaMailerInterface $mailer,
    private readonly MailTypeRegistry $types,
    private readonly DeliveryPlanner $planner,
    private readonly QueueFactory $queues,
    private readonly TimeInterface $time,
    private readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('aculta_mailer.mailer'),
      $container->get('Drupal\aculta_mailer\Type\MailTypeRegistry'),
      $container->get('aculta_mailer.delivery_planner'),
      $container->get('queue'),
      $container->get('datetime.time'),
      $container->get('logger.channel.aculta_mailer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $message = MailMessage::fromArray(is_array($data['message'] ?? NULL) ? $data['message'] : []);
    $attempt = (int) ($data['attempt'] ?? 1);
    $notBefore = (int) ($data['not_before'] ?? 0);
    $transport = (string) ($data['transport'] ?? 'hostinger');

    // Ainda não é hora da próxima tentativa: a mensagem volta para a fila sem ser perdida.
    if ($notBefore > $this->time->getRequestTime()) {
      $this->queues->get(MailQueue::QUEUE_NAME)->createItem($data);
      return;
    }

    if (!$this->types->has($message->type)) {
      $this->logger->error('Mensagem de tipo não registrado descartada: @type (correlação @id).', [
        '@type' => $message->type,
        '@id' => $message->correlationId,
      ]);
      return;
    }

    if ($this->mailer->send($message)) {
      return;
    }

    // O envio não informa o código SMTP ainda (F2): falha tratada como temporária, com retry limitado.
    $decision = $this->planner->afterFailure($attempt, $transport, DeliveryError::Temporary);
    if ($decision->status === MailStatus::Deferred) {
      $this->queues->get(MailQueue::QUEUE_NAME)->createItem([
        'message' => $message->toArray(),
        'attempt' => $decision->nextAttempt,
        'not_before' => $this->time->getRequestTime() + (int) $decision->delaySeconds,
        'transport' => (string) $decision->transport,
      ]);
      $this->logger->warning('Envio adiado (tentativa @n, correlação @id).', [
        '@n' => $decision->nextAttempt,
        '@id' => $message->correlationId,
      ]);
      return;
    }

    $this->logger->error('Envio encerrado sem sucesso após @n tentativas (correlação @id).', [
      '@n' => $attempt,
      '@id' => $message->correlationId,
    ]);
  }

}
