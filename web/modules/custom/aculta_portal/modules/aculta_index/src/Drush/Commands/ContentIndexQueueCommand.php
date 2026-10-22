<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Drush\Commands;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Inspects and repairs the internal Content Index work queue.
 *
 * Dead-letter output contains entity IDs and is intended for operators only.
 */
#[AsCommand(name: 'aculta-index:queue', description: 'Inspeciona ou reenvia jobs da fila do ACULTA Content Index.')]
final class ContentIndexQueueCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_index.work_manager')]
    private readonly ContentIndexWorkManager $work,
    #[Autowire(service: 'aculta_index.provider_registry')]
    private readonly ContentIndexProviderRegistry $providers,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->addArgument('action', InputArgument::REQUIRED, 'Ação: status, dead ou retry.');
    $this->addArgument('work-id', InputArgument::OPTIONAL, 'ID do dead-letter a reenviar.');
    $this->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'Máximo de dead-letters listados (1 a 100).', 50);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $action = (string) $input->getArgument('action');
    if ($action === 'status') {
      $state = $this->work->getActivationGateState(array_keys($this->providers->all()));
      $output->writeln(sprintf(
        'activation_ready=%s queued=%d processing=%d retry=%d dead=%d',
        $state['ready'] ? 'yes' : 'no',
        $state['queued'],
        $state['processing'],
        $state['retry'],
        $state['dead'],
      ));
      return Command::SUCCESS;
    }

    if ($action === 'dead') {
      $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
      if ($limit === FALSE || $limit < 1 || $limit > 100) {
        $output->writeln('<error>--limit precisa estar entre 1 e 100.</error>');
        return Command::FAILURE;
      }
      foreach ($this->work->listDead($limit) as $item) {
        $output->writeln(implode("\t", [
          $item['work_id'],
          $item['provider_id'],
          $item['entity_type'],
          $item['entity_id'],
          $item['langcode'],
          $item['attempts'],
          $item['last_error_code'],
        ]));
      }
      return Command::SUCCESS;
    }

    if ($action === 'retry') {
      $workId = filter_var($input->getArgument('work-id'), FILTER_VALIDATE_INT);
      if ($workId === FALSE || $workId < 1 || !$this->work->retryDead($workId)) {
        $output->writeln('<error>Dead-letter inexistente ou indisponível para reenvio.</error>');
        return Command::FAILURE;
      }
      $output->writeln('<info>Job reenfileirado; aguarde a conclusão antes de ativar uma geração.</info>');
      return Command::SUCCESS;
    }

    $output->writeln('<error>Ação inválida; use status, dead ou retry.</error>');
    return Command::FAILURE;
  }

}
