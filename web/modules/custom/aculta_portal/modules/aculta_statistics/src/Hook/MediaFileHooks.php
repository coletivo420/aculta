<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Conta aberturas de arquivo privado de mídia, por arquivo, sem identificar quem acessou.
 *
 * Só arquivo privado passa pelo Core (`system.files`), e o hook dispara a cada requisição. Arquivo público é servido
 * pelo servidor web e não é medido. O visualizador de PDF pede o arquivo em pedaços (`Range`): só a abertura conta,
 * isto é, requisição sem `Range` ou com `bytes=0-`. Conta só quando o usuário atual pode baixar o arquivo.
 */
final class MediaFileHooks {

  /**
   * Métrica do catálogo para aberturas de arquivo.
   */
  public const METRIC = 'media.files.requests';

  public function __construct(
    private readonly StatisticsRecorder $recorder,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Registra a abertura do arquivo quando permitida e não é um pedaço intermediário.
   */
  #[Hook('file_download')]
  public function fileDownload(string $uri): ?array {
    $request = $this->requestStack->getCurrentRequest();
    $range = $request?->headers->get('Range');
    if ($range !== NULL && preg_match('/^bytes=0-/', $range) !== 1) {
      return NULL;
    }
    $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri]);
    $file = $files === [] ? NULL : reset($files);
    if ($file === NULL || !$file->access('download')) {
      return NULL;
    }
    $this->recorder->record(self::METRIC, (string) $file->id());
    return NULL;
  }

}
