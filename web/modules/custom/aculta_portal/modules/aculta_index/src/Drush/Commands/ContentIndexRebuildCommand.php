<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Drush\Commands;

use Drupal\aculta_index\Generation\ContentIndexGenerationManager;
use Drupal\aculta_index\Rebuild\ContentIndexRebuilder;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs explicit, bounded Content Index rebuild lifecycle operations.
 */
#[AsCommand(name: 'aculta-index:rebuild', description: 'Inicia, continua, valida ou ativa uma geração do ACULTA Content Index.')]
final class ContentIndexRebuildCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_index.rebuilder')]
    private readonly ContentIndexRebuilder $rebuilder,
    #[Autowire(service: 'aculta_index.generation_manager')]
    private readonly ContentIndexGenerationManager $generations,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->addArgument('action', InputArgument::REQUIRED, 'Ação: start, batch, validate, activate, discard ou discard-batch.');
    $this->addArgument('generation-id', InputArgument::OPTIONAL, 'ID da geração para batch, validate, activate ou descarte.');
    $this->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'Entidades por lote (1 a 500).', 100);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $action = (string) $input->getArgument('action');
    $generationId = (string) ($input->getArgument('generation-id') ?? '');
    try {
      if ($action === 'start') {
        $output->writeln($this->rebuilder->start());
        return Command::SUCCESS;
      }
      if ($generationId === '') {
        $output->writeln('<error>Informe o ID da geração.</error>');
        return Command::FAILURE;
      }
      if ($action === 'batch') {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if ($limit === FALSE || $limit < 1 || $limit > 500) {
          $output->writeln('<error>--limit precisa estar entre 1 e 500.</error>');
          return Command::FAILURE;
        }
        $result = $this->rebuilder->processNextBatch($generationId, $limit);
        $output->writeln(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return Command::SUCCESS;
      }
      if ($action === 'validate') {
        $result = $this->rebuilder->validate($generationId);
        $output->writeln(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return Command::SUCCESS;
      }
      if ($action === 'activate') {
        $this->generations->activate($generationId);
        $output->writeln('<info>Geração ativa: ' . $generationId . '.</info>');
        return Command::SUCCESS;
      }
      if ($action === 'discard') {
        $this->generations->discardBuilding($generationId);
        $output->writeln('<info>Geração marcada para descarte: ' . $generationId . '.</info>');
        return Command::SUCCESS;
      }
      if ($action === 'discard-batch') {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if ($limit === FALSE || $limit < 1 || $limit > 5000) {
          $output->writeln('<error>--limit precisa estar entre 1 e 5000 para descarte.</error>');
          return Command::FAILURE;
        }
        $result = $this->generations->discardBatch($generationId, $limit);
        $output->writeln(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return Command::SUCCESS;
      }
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }

    $output->writeln('<error>Ação inválida; use start, batch, validate, activate, discard ou discard-batch.</error>');
    return Command::FAILURE;
  }

}
