<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\EventSubscriber;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Conta buscas executadas, somente com a coleta explicitamente habilitada.
 *
 * Desligado por padrão. Não registra termos, sessão, IP, user-agent ou
 * resultado individual; apenas quantidade de buscas e buscas sem resultado.
 * Visualizações de página e cliques de resultado não são coletados aqui.
 */
final class ForumSearchSubscriber implements EventSubscriberInterface {

  /**
   * Nome do evento emitido pelo aculta_forum.
   */
  private const SEARCH_EXECUTED = 'aculta_forum.search_executed';

  public function __construct(
    private readonly StatisticsRecorder $recorder,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [self::SEARCH_EXECUTED => 'onSearchExecuted'];
  }

  /**
   * Grava a busca quando a coleta está habilitada.
   */
  public function onSearchExecuted(object $event): void {
    if (!$this->configFactory->get('aculta_statistics.settings')
      ->get('forum_discovery.enabled')) {
      return;
    }
    $resultCount = get_object_vars($event)['resultCount'] ?? NULL;
    if (!is_int($resultCount) || $resultCount < 0) {
      return;
    }
    $this->recorder->record('forum.search.queries');
    if ($resultCount === 0) {
      $this->recorder->record('forum.search.zero_results');
    }
  }

}
