<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Drush\Commands;

use Drupal\aculta_forum\Analytics\ForumReactionReconciliation;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Audita e reconcilia reações do Fórum com escopo público/restrito divergente.
 *
 * Dry-run por padrão. A gravação exige `--apply` explícito.
 */
#[AsCommand(name: 'aculta-forum:reconcile-reactions', description: 'Audita reações do Fórum por escopo público/restrito; dry-run por padrão, grava só com --apply.')]
final class ForumReactionReconciliationCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_forum.reaction_reconciliation')]
    private readonly ForumReactionReconciliation $reconciliation,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->addOption('apply', NULL, InputOption::VALUE_NONE, 'Grava a classificação e reconstrói os contadores.')
      ->addOption('batch-size', NULL, InputOption::VALUE_REQUIRED, 'Reações por lote (1 a 1000).', (string) ForumReactionReconciliation::DEFAULT_BATCH_SIZE)
      ->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'Limite total de reações inspecionadas; omitido percorre tudo.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $apply = (bool) $input->getOption('apply');
    $batchSize = (int) $input->getOption('batch-size');
    $limitOption = $input->getOption('limit');
    $limit = $limitOption === NULL || $limitOption === '' ? NULL : (int) $limitOption;

    try {
      $report = $this->reconciliation->run($apply, $batchSize, $limit);
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>Reconciliação interrompida; nenhum dado foi alterado além dos lotes já processados.</error>');
      return Command::FAILURE;
    }

    if ($report['status'] === 'locked') {
      $output->writeln('<comment>Outra reconciliação está em execução; nada foi alterado.</comment>');
      return Command::FAILURE;
    }

    $output->writeln(sprintf('Reações inspecionadas: %d', (int) $report['scanned']));
    $output->writeln(sprintf('Resolvidas como públicas: %d', (int) $report['public']));
    $output->writeln(sprintf('Resolvidas como restritas/contextuais: %d', (int) $report['restricted']));
    $output->writeln(sprintf('Divergências: %d', (int) $report['divergences']));
    $output->writeln(sprintf('Sem host resolvível: %d', (int) $report['unresolved']));
    if ($apply) {
      $output->writeln(sprintf('Registros reclassificados: %d', (int) $report['updated']));
      $output->writeln(sprintf('Contas com contador regravado: %d', (int) ($report['user_stats_rebuilt'] ?? 0)));
      $output->writeln('<info>Reconciliação aplicada.</info>');
    }
    else {
      $output->writeln('<comment>Dry run: nenhum registro foi alterado. Use --apply para corrigir.</comment>');
    }
    return $report['status'] === 'failed' ? Command::FAILURE : Command::SUCCESS;
  }

}
