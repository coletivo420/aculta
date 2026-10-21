<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Drush\Commands;

use Drupal\aculta_statistics\Connector\Ga4\Ga4ReportClient;
use Drupal\aculta_statistics\Connector\SearchConsole\GoogleSearchConsoleClient;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Verificação somente leitura do GA4 e do Search Console: mostra quantos dias voltaram, sem imprimir credencial.
 */
#[AsCommand(name: 'aculta-statistics:google-check', description: 'Consulta de teste ao GA4 e ao Search Console (somente leitura, sem imprimir credenciais).')]
final class GoogleCheckCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_statistics.ga4_client')]
    private readonly Ga4ReportClient $ga4,
    #[Autowire(service: 'aculta_statistics.gsc_client')]
    private readonly GoogleSearchConsoleClient $searchConsole,
  ) {
    parent::__construct();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $end = gmdate('Y-m-d');
    $start = gmdate('Y-m-d', strtotime('-6 days'));
    $today = intdiv(time(), 86400);

    $output->writeln('GA4 ligado: ' . ($this->ga4->isEnabled() ? 'sim' : 'não'));
    if ($this->ga4->isEnabled()) {
      $series = $this->ga4->report($today - 6, $today);
      $output->writeln($series === NULL ? 'GA4: consulta falhou (ver log do canal aculta_statistics).' : 'GA4: ' . count($series) . ' dia(s) retornados.');
    }

    $output->writeln('Search Console ligado: ' . ($this->searchConsole->isEnabled() ? 'sim' : 'não'));
    if ($this->searchConsole->isEnabled()) {
      $series = $this->searchConsole->report($start, $end);
      $output->writeln($series === NULL ? 'Search Console: consulta falhou (ver log do canal aculta_statistics).' : 'Search Console: ' . count($series) . ' dia(s) retornados.');
    }
    return Command::SUCCESS;
  }

}
