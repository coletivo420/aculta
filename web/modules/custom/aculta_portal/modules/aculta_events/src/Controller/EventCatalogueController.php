<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Catalogue\EventCatalogue;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Catálogo público de eventos (/eventos), com filtros por tipo e período.
 *
 * Período e datas usam o formato brasileiro (dd/mm/aaaa). Data inválida é ignorada e
 * aparece como aviso, sem derrubar a página.
 */
final class EventCatalogueController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EventCatalogue $catalogo,
    private readonly TimeInterface $tempo,
    private readonly Request $requisicao,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_events.catalogue'),
      $container->get('datetime.time'),
      $container->get('request_stack')->getCurrentRequest(),
    );
  }

  /**
   * Página do catálogo.
   */
  public function page(): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $tipo = $this->requisicao->query->getInt('tipo') ?: NULL;
    $avisos = [];
    $de = $this->dataBr((string) $this->requisicao->query->get('de'), $fuso, $avisos, 'início');
    $ate = $this->dataBr((string) $this->requisicao->query->get('ate'), $fuso, $avisos, 'fim');

    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
    $resultados = $this->catalogo->busca($tipo, $de, $ate, $agora);

    $itens = [];
    foreach ($resultados as $item) {
      $evento = $item['evento'];
      $url = Url::fromRoute('aculta_events.page', ['slug' => (string) $evento->get('field_slug')->value])->toString();
      $linha = '<a href="' . Html::escape($url) . '">' . Html::escape((string) $evento->label()) . '</a>'
        . ' — ' . Html::escape($item['proxima']['inicio']->format('d/m/Y H:i'));
      if ($evento->get('field_status')->value === 'cancelado') {
        $linha .= ' <strong>(cancelado)</strong>';
      }
      $itens[] = ['#markup' => $linha];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $itens,
      '#empty' => $this->t('Nenhum evento encontrado para este período.'),
      '#prefix' => $this->avisos($avisos),
      '#cache' => ['contexts' => ['url.query_args'], 'tags' => ['aculta_event_list'], 'max-age' => 900],
    ];
  }

  /**
   * Converte dd/mm/aaaa em data de Brasília; vazio vira NULL; inválido vira aviso.
   */
  private function dataBr(string $valor, \DateTimeZone $fuso, array &$avisos, string $rotulo): ?\DateTimeImmutable {
    if ($valor === '') {
      return NULL;
    }
    $data = \DateTimeImmutable::createFromFormat('!d/m/Y', $valor, $fuso);
    if ($data === FALSE || $data->format('d/m/Y') !== $valor) {
      $avisos[] = $this->t('Data de @rotulo inválida: @valor. Use dd/mm/aaaa.', ['@rotulo' => $rotulo, '@valor' => $valor]);
      return NULL;
    }
    return $data;
  }

  /**
   * Avisos de filtro como lista de mensagens.
   */
  private function avisos(array $avisos): string {
    if ($avisos === []) {
      return '';
    }
    $html = '';
    foreach ($avisos as $aviso) {
      $html .= '<p role="alert">' . Html::escape((string) $aviso) . '</p>';
    }
    return $html;
  }

}
