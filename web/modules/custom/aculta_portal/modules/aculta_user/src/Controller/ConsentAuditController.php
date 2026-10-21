<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\aculta_user\Consent\ConsentLedger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Read-only administrative view of consent evidence and chain integrity. */
final class ConsentAuditController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ConsentLedger $ledger,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('date.formatter'), $container->get('aculta_user.consent_ledger'));
  }

  public function listing(): array {
    $rows = [];
    foreach ($this->database->select('aculta_user_consent', 'c')->fields('c')->orderBy('id', 'DESC')->range(0, 200)->execute() as $event) {
      $rows[] = [
        $this->dateFormatter->format((int) $event->created, 'short'),
        (string) $event->uid,
        (string) $event->agreement,
        (string) $event->action,
        $event->ip_address ?: $this->t('Não disponível (registro legado)'),
        $event->user_agent ?: $this->t('Não disponível (registro legado)'),
        $event->legacy ? $this->t('Legado incompleto') : $this->t('Completo'),
        $this->ledger->verify((int) $event->uid) ? $this->t('Íntegro') : $this->t('Divergência'),
        ['data' => ['#plain_text' => (string) $event->record_hash]],
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Data/hora'), $this->t('UID'), $this->t('Registro'), $this->t('Ação'), $this->t('IP'), $this->t('User-Agent'), $this->t('Evidência'), $this->t('Integridade'), $this->t('Hash')],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum registro de aceite ou consentimento.'),
      '#cache' => ['max-age' => 0],
    ];
  }

}
