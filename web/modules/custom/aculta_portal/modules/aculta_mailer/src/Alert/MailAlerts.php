<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Alert;

use Drupal\aculta_mailer\Delivery\AdministratorRecipients;
use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Queue\MailQueue;
use Drupal\aculta_mailer\Type\AdminAlertMailType;
use Drupal\aculta_mailer\Type\WeeklySummaryMailType;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Alertas ao administrador: falha de envio e endereço que devolveu a mensagem (modelo "Alerts" do WP Mail SMTP).
 *
 * Um alerta por janela de 5 minutos: o restante é contado e vai no próximo alerta. Os alertas entram na fila, para não
 * atrasar a requisição do usuário. Alertas e resumos não geram alertas, o que evita laço.
 */
final class MailAlerts {

  /**
   * Janela entre alertas, em segundos (padrão do WP Mail SMTP).
   */
  public const THROTTLE = 300;

  private const STATE_LAST = 'aculta_mailer.alerts.last';

  private const STATE_SUPPRESSED = 'aculta_mailer.alerts.suppressed';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly MailQueue $queue,
    private readonly UuidInterface $uuid,
    private readonly AdministratorRecipients $administrators,
  ) {}

  /**
   * Alerta de falha de envio. Mensagens do próprio sistema de alertas e de resumo não geram alerta.
   */
  public function failure(string $type, string $correlationId, string $transport, string $code, ?string $detail): void {
    if (in_array($type, [AdminAlertMailType::ID, WeeklySummaryMailType::ID], TRUE)) {
      return;
    }
    $this->dispatch([
      'evento' => 'Falha de envio',
      'tipo' => $type,
      'transporte' => $transport,
      'codigo' => $code,
      'correlacao' => $correlationId,
      'detalhe' => $detail ?? '-',
    ]);
  }

  /**
   * Alerta de endereço que devolveu a mensagem (bounce aplicado pelo monitoramento IMAP).
   */
  public function hardBounce(string $correlationId, string $code): void {
    $this->dispatch([
      'evento' => 'Endereço devolveu a mensagem',
      'tipo' => '-',
      'transporte' => '-',
      'codigo' => $code,
      'correlacao' => $correlationId,
      'detalhe' => '-',
    ]);
  }

  /**
   * Enfileira o alerta para os destinatários, respeitando a janela. Desligado ou sem destinatário, não faz nada.
   */
  private function dispatch(array $context): void {
    $settings = $this->configFactory->get(DeliverySettings::CONFIG);
    if (!$settings->get('alerts_enabled')) {
      return;
    }
    $recipients = DeliverySettings::recipients((string) $settings->get('alerts_recipients'));
    if ($settings->get('alerts_admins')) {
      $recipients = array_values(array_unique(array_merge($recipients, $this->administrators->emails())));
    }
    if ($recipients === []) {
      return;
    }
    $now = $this->time->getRequestTime();
    if ($now - (int) $this->state->get(self::STATE_LAST, 0) < self::THROTTLE) {
      $this->state->set(self::STATE_SUPPRESSED, (int) $this->state->get(self::STATE_SUPPRESSED, 0) + 1);
      return;
    }
    $context['suprimidos'] = (string) $this->state->get(self::STATE_SUPPRESSED, 0);
    $this->state->set(self::STATE_LAST, $now);
    $this->state->set(self::STATE_SUPPRESSED, 0);
    foreach ($recipients as $recipient) {
      $this->queue->enqueue(new MailMessage(
        AdminAlertMailType::ID,
        $recipient,
        'pt-br',
        $context,
        $this->uuid->generate(),
      ));
    }
  }

}
