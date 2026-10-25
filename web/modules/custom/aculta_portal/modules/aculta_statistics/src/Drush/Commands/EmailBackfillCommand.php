<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Drush\Commands;

use Drupal\aculta_statistics\Reconciliation\EmailReconciler;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Preenche o histórico de e-mails na central, para os dias antes da ativação do cron de conciliação.
 *
 * Usa o mesmo reconciliador do cron: substitui o valor de cada dia, então rodar de novo não duplica.
 */
#[AsCommand(name: 'aculta-statistics:backfill-email', description: 'Reconcilia o histórico de e-mails (até 90 dias) no aculta_statistics.')]
final class EmailBackfillCommand extends Command {

  use AutowireTrait;

  /**
   * Prazo máximo do backfill: o mesmo da retenção padrão das métricas de e-mail.
   */
  private const MAX_DAYS = 90;

  public function __construct(
    #[Autowire(service: 'aculta_statistics.email_reconciler')]
    private readonly EmailReconciler $reconciler,
    #[Autowire(service: 'aculta_statistics.storage')]
    private readonly AggregateStorageInterface $storage,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addOption('days', NULL, InputOption::VALUE_REQUIRED, 'Dias a reconciliar, até o dia atual (1 a 90).', '90');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $days = (int) $input->getOption('days');
    if ($days < 1 || $days > self::MAX_DAYS) {
      $output->writeln('<error>--days precisa estar entre 1 e ' . self::MAX_DAYS . '.</error>');
      return Command::FAILURE;
    }

    $today = $this->storage->currentDay();
    $this->reconciler->reconcile($today - $days + 1, $today);
    $output->writeln('<info>Histórico de e-mails reconciliado: ' . $days . ' dias até o dia UTC ' . $today . '.</info>');
    return Command::SUCCESS;
  }

}
