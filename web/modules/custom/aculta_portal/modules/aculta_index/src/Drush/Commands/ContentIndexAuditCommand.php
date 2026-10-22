<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Drush\Commands;

use Drupal\aculta_index\Integrity\ContentIndexIntegrityChecker;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Starts and advances bounded incremental catalog integrity audits.
 */
#[AsCommand(name: 'aculta-index:audit', description: 'Executa auditoria incremental do ACULTA Content Index.')]
final class ContentIndexAuditCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_index.integrity_checker')]
    private readonly ContentIndexIntegrityChecker $integrity,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->addArgument('action', InputArgument::REQUIRED, 'Ação: status, start ou batch.');
    $this->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'Registros verificados neste lote (1 a 500).', 200);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $action = (string) $input->getArgument('action');
    try {
      if ($action === 'status') {
        $result = $this->integrity->getAuditState();
      }
      elseif ($action === 'start') {
        $result = $this->integrity->startAudit();
      }
      elseif ($action === 'batch') {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if ($limit === FALSE || $limit < 1 || $limit > 500) {
          $output->writeln('<error>--limit precisa estar entre 1 e 500.</error>');
          return Command::FAILURE;
        }
        $result = $this->integrity->processAuditBatch($limit);
      }
      else {
        $output->writeln('<error>Ação inválida; use status, start ou batch.</error>');
        return Command::FAILURE;
      }
      $output->writeln(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
      return Command::SUCCESS;
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
  }

}
