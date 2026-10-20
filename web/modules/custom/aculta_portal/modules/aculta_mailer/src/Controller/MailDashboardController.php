<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Controller;

use Drupal\aculta_mailer\Dashboard\MailDashboard;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Painel de envios: um quadro por período, com contagens. Sem destinatário e sem conteúdo.
 */
final class MailDashboardController extends ControllerBase {

  public function __construct(
    private readonly MailDashboard $dashboard,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_mailer.dashboard'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Página do painel.
   */
  public function overview(): array {
    $now = $this->time->getRequestTime();
    $build = [
      '#type' => 'container',
      'intro' => [
        '#markup' => '<p>' . $this->t('Aceito pelo sistema de e-mail não é o mesmo que entregue: a entrega depende dos provedores.') . '</p>',
      ],
    ];
    foreach (MailDashboard::PERIODS as $days) {
      $summary = $this->dashboard->summary($now - $days * 86400);
      $build['period_' . $days] = [
        '#type' => 'details',
        '#title' => new TranslatableMarkup('Últimos @days dia(s): @total envio(s)', [
          '@days' => $days,
          '@total' => $summary['total'],
        ]),
        '#open' => $days === 1,
        'status' => $this->table($this->t('Estado'), $summary['by_status']),
        'type' => $this->table($this->t('Tipo'), $summary['by_type']),
        'transport' => $this->table($this->t('Transporte'), $summary['by_transport']),
      ];
    }
    return $build;
  }

  /**
   * Quadro de duas colunas: rótulo e contagem.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   Título da primeira coluna.
   * @param array $counts
   *   Contagem por valor.
   *
   * @return array
   *   Render array de tabela.
   */
  private function table(TranslatableMarkup $label, array $counts): array {
    $rows = [];
    foreach ($counts as $name => $count) {
      $rows[] = [$name, $count];
    }
    return [
      '#type' => 'table',
      '#header' => [$label, $this->t('Quantidade')],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum registro.'),
    ];
  }

}
