<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher\Drush\Commands;

use Drupal\aculta_patcher\PatchManifestExporter;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Export active ACULTA patch records for Composer builds. */
#[AsCommand(name: 'aculta:patcher-export', description: 'Validate and export active ACULTA Composer patches.')]
final class AcultaPatcherExportCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_patcher.exporter')]
    private readonly PatchManifestExporter $exporter,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addOption('check', NULL, InputOption::VALUE_NONE, 'Validate only; do not write artifacts.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $message = $this->exporter->export((bool) $input->getOption('check'));
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
    $output->writeln('<info>' . $message . '</info>');
    return Command::SUCCESS;
  }

}
