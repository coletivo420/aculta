<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Drush\Commands;

use Drupal\aculta_magazine\Migration\GalleryReferenceMigrator;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Migrates unambiguous Revista Gallery embeds to a structured reference.
 */
#[AsCommand(name: 'aculta-magazine:migrate-gallery-references', description: 'Copia embeds únicos da Gallery para field_editorial_gallery sem alterar o corpo.')]
final class GalleryReferenceMigrationCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_magazine.gallery_reference_migrator')]
    private readonly GalleryReferenceMigrator $migrator,
  ) {
    parent::__construct();
  }

  /**
   * Defines bounded, resumable migration options.
   */
  protected function configure(): void {
    $this->addOption('execute', NULL, InputOption::VALUE_NONE, 'Grava as referências encontradas; sem esta opção executa apenas a prévia.');
    $this->addOption('after', NULL, InputOption::VALUE_REQUIRED, 'Retoma após este NID.', '0');
    $this->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'Publicações neste lote (1 a 500).', '100');
  }

  /**
   * Runs one migration batch and emits a machine-readable report.
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $after = filter_var($input->getOption('after'), FILTER_VALIDATE_INT);
    $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
    if ($after === FALSE || $after < 0 || $limit === FALSE || $limit < 1 || $limit > 500) {
      $output->writeln('<error>Use --after com inteiro não negativo e --limit entre 1 e 500.</error>');
      return Command::FAILURE;
    }

    try {
      $report = $this->migrator->processBatch($after, $limit, (bool) $input->getOption('execute'));
      $output->writeln(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
      if ($report['failed'] > 0) {
        return Command::FAILURE;
      }
      if (!$input->getOption('execute')) {
        $output->writeln('<comment>Prévia concluída: nenhum conteúdo alterado. Reexecute com --execute para aplicar o lote.</comment>');
      }
      return Command::SUCCESS;
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>Migração interrompida: ' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
  }

}
