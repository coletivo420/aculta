<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Drush\Commands;

use Drupal\aculta_portal_cep\Local\CepLocalStore;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Importa a base local de CEPs (TSV ordenado) para os arquivos privados do Portal. */
#[AsCommand(name: 'aculta-portal-cep:import', description: 'Importa a base local de CEPs (TSV ordenado por CEP) para private://aculta_portal_cep.')]
final class CepLocalImportCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_portal_cep.local_store')]
    private readonly CepLocalStore $store,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('source', InputArgument::REQUIRED, 'Caminho do TSV: cep, logradouro, bairro, cidade, UF, código IBGE.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $count = $this->store->importTsv((string) $input->getArgument('source'));
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
    // Versão da base: data desta importação (a origem é o arquivo informado). Ver CepLocalStore::MANIFEST.
    $this->store->writeManifest(date('Y-m-d'), $count, basename((string) $input->getArgument('source')));
    $output->writeln('<info>' . $count . ' CEPs importados para ' . CepLocalStore::DIRECTORY . '.</info>');
    return Command::SUCCESS;
  }

}
