<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Drush\Commands;

use Drupal\aculta_statistics\Provider\SnapshotReconciliation;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reconcilia retratos de métricas derivadas com a fonte.
 *
 * Dry-run por padrão; grava apenas com `--apply`.
 */
#[AsCommand(name: 'aculta-statistics:reconcile-snapshots', description: 'Compara retratos gravados com a fonte; dry-run por padrão, grava só com --apply.')]
final class SnapshotReconciliationCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_statistics.snapshot_reconciliation')]
    private readonly SnapshotReconciliation $reconciliation,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->addOption('from', NULL, InputOption::VALUE_REQUIRED, 'Primeiro dia UTC (timestamp/dias desde epoch); omitido usa hoje.')
      ->addOption('to', NULL, InputOption::VALUE_REQUIRED, 'Último dia UTC; omitido usa hoje.')
      ->addOption('apply', NULL, InputOption::VALUE_NONE, 'Grava os valores calculados.')
      ->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'Máximo de comparações.', '1000');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $today = intdiv(time(), 86400);
    $fromDay = $input->getOption('from') !== NULL ? (int) $input->getOption('from') : $today;
    $toDay = $input->getOption('to') !== NULL ? (int) $input->getOption('to') : $today;

    try {
      $report = $this->reconciliation->run(
        $fromDay,
        $toDay,
        (bool) $input->getOption('apply'),
        (int) $input->getOption('limit'),
      );
    }
    catch (\Throwable) {
      $output->writeln('<error>Reconciliação inválida; nada foi alterado.</error>');
      return Command::FAILURE;
    }

    if ($report['status'] === 'locked') {
      $output->writeln('<comment>Outra reconciliação está em execução.</comment>');
      return Command::FAILURE;
    }

    $output->writeln(sprintf('Verificações: %d', (int) $report['checked']));
    $output->writeln(sprintf('Iguais: %d', (int) $report['matched']));
    $output->writeln(sprintf('Divergentes: %d', (int) $report['divergent']));
    $output->writeln(sprintf('Sem coleta: %d', (int) $report['missing']));
    $output->writeln(sprintf('Indisponíveis na fonte: %d', (int) $report['unavailable']));
    if ($input->getOption('apply')) {
      $output->writeln(sprintf('Atualizados: %d', (int) $report['updated']));
    }
    else {
      $output->writeln('<comment>Dry run: nenhum registro foi alterado.</comment>');
    }
    return $report['status'] === 'failed' ? Command::FAILURE : Command::SUCCESS;
  }

}
