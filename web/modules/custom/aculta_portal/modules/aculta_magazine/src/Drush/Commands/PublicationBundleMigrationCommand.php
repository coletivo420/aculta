<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Drush\Commands;

use Drupal\aculta_magazine\Migration\PublicationBundleMigrator;
use Drupal\Core\Database\Connection;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Migrates the existing article/blog Nodes to the canonical Revista bundle.
 */
#[AsCommand(name: 'aculta-magazine:migrate-publications', description: 'Migra article/blog para revista preservando NID, UUID, revisão e aliases.')]
final class PublicationBundleMigrationCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_magazine.publication_bundle_migrator')]
    private readonly PublicationBundleMigrator $migrator,
    #[Autowire(service: 'database')]
    private readonly Connection $database,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct();
  }

  /**
   * Defines the explicit execution and backup requirements.
   */
  protected function configure(): void {
    $this->addOption('execute', NULL, InputOption::VALUE_NONE, 'Executa a migração; sem esta opção apenas valida e exibe as quantidades.');
    $this->addOption('backup-file', NULL, InputOption::VALUE_REQUIRED, 'Dump gzip privado criado imediatamente antes da migração. Obrigatório com --execute.');
    $this->addOption('config-backup-dir', NULL, InputOption::VALUE_REQUIRED, 'Diretório privado com export completo da configuração ativa. Obrigatório com --execute.');
  }

  /**
   * Runs the preflight or guarded migration.
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $plan = $this->migrator->plan();
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>Pré-validação interrompida: ' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }

    $output->writeln(sprintf('Pré-validação PASS: %d Nodes, %d revisões e %d aliases.', count($plan['nids']), $plan['revisions'], $plan['aliases']));
    if (!$input->getOption('execute')) {
      $output->writeln('<comment>Dry run: nenhum dado alterado.</comment>');
      return Command::SUCCESS;
    }

    if (!$this->isAuthorizedDevelopmentRuntime()) {
      $output->writeln('<error>Execução recusada: somente `toca` + `aculta_runtime` + purpose Magazine de teste.</error>');
      return Command::FAILURE;
    }
    $backup = $input->getOption('backup-file');
    $config_backup = $input->getOption('config-backup-dir');
    if (!is_string($backup) || !$this->isRecentPrivateGzipDump($backup)
      || !is_string($config_backup) || !$this->isRecentPrivateConfigExport($config_backup)) {
      $output->writeln('<error>Execução recusada: informe dump gzip e export de configuração completos, privados e criados nos últimos 30 minutos.</error>');
      return Command::FAILURE;
    }

    try {
      $report = $this->migrator->migrate();
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>Migração revertida: ' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
    $output->writeln(sprintf('Migração concluída: %d Nodes `revista`, %d revisões e %d aliases preservados.', count($report['nids']), $report['revisions'], $report['aliases']));
    return Command::SUCCESS;
  }

  /**
   * Restricts data changes to the documented Homelab Runtime and URI.
   */
  private function isAuthorizedDevelopmentRuntime(): bool {
    $request = $this->requestStack->getCurrentRequest();
    return gethostname() === 'toca'
      && ($this->database->getConnectionOptions()['database'] ?? NULL) === 'aculta_runtime'
      && $request?->getHost() === 'revista420.aculta.toca.net.br';
  }

  /**
   * Checks backup presence, age, permissions and gzip integrity without output.
   */
  private function isRecentPrivateGzipDump(string $path): bool {
    $real_path = realpath($path);
    if ($real_path === FALSE
      || str_starts_with($real_path, dirname(DRUPAL_ROOT) . DIRECTORY_SEPARATOR)
      || !is_file($real_path)
      || filesize($real_path) < 1_000_000
      || (fileperms($real_path) & 0077) !== 0
      || filemtime($real_path) < time() - 1800
      || file_get_contents($real_path, FALSE, NULL, 0, 2) !== "\x1f\x8b") {
      return FALSE;
    }
    $handle = @gzopen($real_path, 'rb');
    if ($handle === FALSE) {
      return FALSE;
    }
    while (!gzeof($handle)) {
      if (gzread($handle, 1_048_576) === FALSE) {
        gzclose($handle);
        return FALSE;
      }
    }
    $valid = gzclose($handle);
    return $valid;
  }

  /**
   * Validates a recent private full export of active Drupal configuration.
   */
  private function isRecentPrivateConfigExport(string $path): bool {
    $real_path = realpath($path);
    if ($real_path === FALSE
      || str_starts_with($real_path, dirname(DRUPAL_ROOT) . DIRECTORY_SEPARATOR)
      || !is_dir($real_path)
      || (fileperms($real_path) & 0077) !== 0
      || filemtime($real_path) < time() - 1800) {
      return FALSE;
    }
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real_path, \FilesystemIterator::SKIP_DOTS));
    $yaml_count = 0;
    foreach ($files as $file) {
      if (!$file->isFile()) {
        continue;
      }
      if (($file->getPerms() & 0077) !== 0) {
        return FALSE;
      }
      if (str_ends_with($file->getFilename(), '.yml')) {
        $yaml_count++;
      }
    }
    return $yaml_count >= 100;
  }

}
