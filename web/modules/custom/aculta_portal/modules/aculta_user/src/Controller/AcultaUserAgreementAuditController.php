<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\aculta_user\Consent\AcultaUserAgreementLedger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read-only administrative view of consent evidence and chain integrity. */
final class AcultaUserAgreementAuditController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly AcultaUserAgreementLedger $ledger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('date.formatter'), $container->get('aculta_user.aculta_user_agreement_ledger'));
  }

  /**
   * Texto de uma coluna da linha; vazio quando o valor não é escalar.
   */
  private function textValue(mixed $value): string {
    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * Inteiro de uma coluna da linha; zero quando o valor não é numérico.
   */
  private function intValue(mixed $value): int {
    return is_numeric($value) ? (int) $value : 0;
  }

  /**
   * Lista os registros de consentimento com a integridade da cadeia.
   *
   * @return array<string, mixed>
   *   Estrutura renderizável da tabela de auditoria.
   */
  public function listing(): array {
    $rows = [];
    $statement = $this->database->select('aculta_user_agreement', 'c')->fields('c')->orderBy('id', 'DESC')->range(0, 200)->execute();
    foreach ($statement ?? [] as $event) {
      if (!$event instanceof \stdClass) {
        continue;
      }
      $rows[] = [
        $this->dateFormatter->format($this->intValue($event->created), 'short'),
        $this->textValue($event->uid),
        $this->textValue($event->aculta_user_agreement_type),
        $this->textValue($event->action),
        $event->ip_address ?: $this->t('Não disponível (registro legado)'),
        $event->user_agent ?: $this->t('Não disponível (registro legado)'),
        $event->legacy ? $this->t('Legado incompleto') : $this->t('Completo'),
        $this->ledger->verify($this->intValue($event->uid)) ? $this->t('Íntegro') : $this->t('Divergência'),
        ['data' => ['#plain_text' => $this->textValue($event->record_hash)]],
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Data/hora'),
        $this->t('UID'),
        $this->t('Registro'),
        $this->t('Ação'),
        $this->t('IP'),
        $this->t('User-Agent'),
        $this->t('Evidência'),
        $this->t('Integridade'),
        $this->t('Hash'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum registro de aceite ou consentimento.'),
      '#cache' => ['max-age' => 0],
    ];
  }

}
