<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Controller;

use Drupal\aculta_mailer\Form\MailLogFilterForm;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lista de todos os envios do registro, com filtros e paginação. Sem destinatário nem conteúdo.
 */
final class MailLogListController extends ControllerBase {

  /**
   * Linhas por página.
   */
  public const PAGE_SIZE = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly FormBuilderInterface $forms,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get('form_builder'), $container->get('datetime.time'));
  }

  /**
   * Página da lista.
   */
  public function list(Request $request): array {
    $type = trim((string) $request->query->get('type', ''));
    $status = (string) $request->query->get('status', '');
    $transport = (string) $request->query->get('transport', '');
    $days = (int) $request->query->get('days', 7);
    if (!array_key_exists($days, MailLogFilterForm::PERIODS)) {
      $days = 7;
    }
    if ($status !== '' && MailStatus::tryFrom($status) === NULL) {
      $status = '';
    }
    $page = max(0, (int) $request->query->get('page', 0));

    $storage = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE);
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('created', $this->time->getRequestTime() - $days * 86400, '>=');
    if ($type !== '') {
      $query->condition('type', $type);
    }
    if ($status !== '') {
      $query->condition('status', $status);
    }
    if ($transport !== '') {
      $query->condition('transport', $transport);
    }
    $total = (clone $query)->count()->execute();
    $ids = $query->sort('created', 'DESC')->range($page * self::PAGE_SIZE, self::PAGE_SIZE)->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $log) {
      $rows[] = [
        date('Y-m-d H:i', (int) $log->get('created')->value),
        (string) $log->get('type')->value,
        (string) $log->get('status')->value,
        (string) $log->get('transport')->value,
        (int) $log->get('attempts')->value,
        (string) $log->get('error_code')->value ?: '-',
        (string) $log->get('correlation_id')->value,
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Estado'),
            '#url' => Url::fromRoute('aculta_mailer.log_status', ['log_id' => (string) $log->id()]),
          ],
        ],
      ];
    }

    $filters = ['type' => $type, 'status' => $status, 'transport' => $transport, 'days' => $days];
    $build = [
      'filtros' => $this->forms->getForm(MailLogFilterForm::class, $filters),
      'resumo' => [
        '#markup' => '<p>' . $this->t('@total envio(s) no período. Página @page.', [
          '@total' => $total,
          '@page' => $page + 1,
        ]) . '</p>',
      ],
      'tabela' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Data'),
          $this->t('Tipo'),
          $this->t('Estado'),
          $this->t('Transporte'),
          $this->t('Tentativas'),
          $this->t('Código'),
          $this->t('Correlação'),
          $this->t('Detalhe'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum envio com estes filtros.'),
        // A rolagem vai no contêiner: overflow-x não rola em <table>; sem isso a página inteira rolava em tela estreita.
        '#prefix' => '<div data-aculta-mailer-table="rolagem">',
        '#suffix' => '</div>',
        '#attached' => ['library' => ['aculta_mailer/admin']],
      ],
      'paginacao' => $this->pager($total, $page, $type, $status, $transport, $days),
    ];
    return $build;
  }

  /**
   * Links de página anterior e próxima, mantendo os filtros.
   */
  private function pager(int $total, int $page, string $type, string $status, string $transport, int $days): array {
    $query = array_filter(
      ['type' => $type, 'status' => $status, 'transport' => $transport, 'days' => $days],
      static fn($v) => $v !== '' && $v !== NULL,
    );
    $links = [];
    if ($page > 0) {
      $links[] = [
        '#type' => 'link',
        '#title' => $this->t('Anterior'),
        '#url' => Url::fromRoute('aculta_mailer.log_list', [], ['query' => $query + ['page' => $page - 1]]),
      ];
    }
    if (($page + 1) * self::PAGE_SIZE < $total) {
      $links[] = [
        '#type' => 'link',
        '#title' => $this->t('Próxima'),
        '#url' => Url::fromRoute('aculta_mailer.log_list', [], ['query' => $query + ['page' => $page + 1]]),
      ];
    }
    return $links === [] ? [] : ['#type' => 'container', 'links' => $links];
  }

}
