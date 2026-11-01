<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Controller;

use Drupal\aculta_image\Audit\ImageAudit;
use Drupal\aculta_image\ImageCapabilities;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exibe o relatório administrativo de imagens.
 *
 * O relatório é somente leitura e inclui resumo, duplicatas e candidatas.
 */
final class ImageAuditController extends ControllerBase {

  public function __construct(
    private readonly ImageAudit $audit,
    private readonly ImageCapabilities $capabilities,
    private readonly ConfigFactoryInterface $imageConfigFactory,
  ) {}

  /**
   * Creates the object with its injected services.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_image.audit'),
      $container->get('aculta_image.capabilities'),
      $container->get('config.factory'),
    );
  }

  /**
   * Builds the image audit dashboard.
   */
  public function dashboard(): array {
    $resumo = $this->audit->resumo();
    $settings = $this->imageConfigFactory->get('aculta_image.settings');
    $backends = $this->capabilities->backends();
    $active_backend = (string) ($settings->get('backend') ?? 'imagick');
    $active_available = isset($backends[$active_backend]);
    return [
      '#cache' => ['max-age' => 0],
      'intro' => ['#markup' => '<p>' . $this->t('Administre o acervo, revise imagens sem referência, acompanhe duplicatas e limpe arquivos temporários ou substituídos.') . '</p>'],
      'resumo' => [
        '#type' => 'table',
        '#header' => [$this->t('Indicador'), $this->t('Quantidade')],
        '#rows' => [
          [$this->t('Imagens gerenciadas'), (string) $resumo['total']],
          [$this->t('Espaço aproximado'), round($resumo['bytes'] / 1048576, 1) . ' MB'],
          [$this->t('Com referência em file_usage'), (string) $resumo['com_uso']],
          [$this->t('Sem referência em file_usage'), (string) $resumo['sem_uso']],
          [$this->t('Mídias'), (string) $resumo['midias']],
        ],
      ],
      'processamento' => [
        '#type' => 'table',
        '#caption' => $this->t('Estado do processamento de imagens neste servidor'),
        '#header' => [$this->t('Recurso'), $this->t('Estado')],
        '#rows' => [
          [
            $this->t('Backend selecionado'),
            $active_backend . ($active_available ? '' : ' (' . $this->t('indisponível') . ')'),
          ],
          [
            $this->t('Bibliotecas PHP'),
            $backends === [] ? $this->t('Nenhuma') : implode(', ', array_keys($backends)),
          ],
          [
            $this->t('Compressão e redimensionamento'),
            $settings->get('compression_enabled') ? $this->t('Ativos') : $this->t('Desativados'),
          ],
          [
            $this->t('Conversão WebP'),
            ($settings->get('webp_enabled') ?? TRUE)
              ? $this->t('Ativa, qualidade @quality', ['@quality' => $settings->get('webp_quality') ?? 82])
              : $this->t('Desativada'),
          ],
          [$this->t('Largura máxima'), (string) ($settings->get('max_width') ?? 1200) . ' px'],
        ],
      ],
      'acoes' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Funções administrativas'),
        '#items' => [
          $this->t('Relatório: resumo, duplicatas e candidatas à revisão.'),
          $this->t('Limpeza: executar a fila de imagens substituídas e temporários vencidos.'),
          $this->t('Quarentena: revisar candidatas, restaurar arquivos e excluir entradas vencidas.'),
        ],
      ],
      'links' => [
        '#theme' => 'item_list',
        '#items' => [
          Link::createFromRoute($this->t('Abrir relatório'), 'aculta_image.audit_report'),
          Link::createFromRoute($this->t('Executar limpeza'), 'aculta_image.cleanup'),
          Link::createFromRoute($this->t('Gerenciar quarentena'), 'aculta_image.quarantine'),
          Link::createFromRoute($this->t('Configurar processamento e formatos'), 'aculta_image.settings'),
        ],
      ],
    ];
  }

  /**
   * Builds the detailed image audit report.
   */
  public function report(): array {
    $resumo = $this->audit->resumo();
    $linhas = [];
    foreach ([
      'Imagens' => $resumo['total'],
      'Bytes (aprox.)' => round($resumo['bytes'] / 1048576, 1) . ' MB',
      'Com uso' => $resumo['com_uso'],
      'Sem uso (candidatas)' => $resumo['sem_uso'],
      'Mídias' => $resumo['midias'],
    ] as $rotulo => $valor) {
      $linhas[] = [$rotulo, (string) $valor];
    }

    $grupos = [];
    foreach ($this->audit->gruposDeDuplicatas() as $grupo) {
      $nomes = [];
      foreach ($grupo['midias'] as $mid => $nome) {
        $nomes[] = $nome . ' (mídia ' . $mid . ')';
      }
      $grupos[] = ['checksum' => substr($grupo['checksum'], 0, 16), 'midias' => implode('; ', $nomes)];
    }

    $candidatas = [];
    foreach ($this->audit->candidatas() as $c) {
      $candidatas[] = [$c['uri'], round($c['bytes'] / 1024, 1) . ' KB', implode('; ', $c['motivos'])];
    }

    $visuais = [];
    foreach ($this->audit->paresVisuais() as $par) {
      $visuais[] = [(string) $par['a'], (string) $par['b'], (string) $par['distancia']];
    }

    return [
      '#cache' => ['max-age' => 0],
      '#attributes' => ['data-portal-ui' => 'aculta-image-audit'],
      'aviso' => ['#markup' => '<p>' . $this->t('Relatório somente de leitura. Nada é apagado aqui: candidatas passam pela revisão e pela quarentena.') . '</p>'],
      'resumo' => ['#type' => 'table', '#header' => [$this->t('Indicador'), $this->t('Valor')], '#rows' => $linhas],
      'duplicatas' => [
        '#type' => 'table',
        '#caption' => $this->t('Grupos de duplicatas exatas (checksum)'),
        '#header' => [$this->t('Checksum'), $this->t('Mídias')],
        '#rows' => $grupos,
        '#empty' => $this->t('Nenhuma duplicata exata encontrada.'),
      ],
      'visuais' => [
        '#type' => 'table',
        '#caption' => $this->t('Possíveis duplicatas visuais (imagens parecidas, a revisar)'),
        '#header' => [$this->t('Imagem A (fid)'), $this->t('Imagem B (fid)'), $this->t('Distância (bits)')],
        '#rows' => $visuais,
        '#empty' => $this->t('Nenhum par parecido. As impressões digitais são calculadas no cron, em lotes.'),
      ],
      'candidatas' => [
        '#type' => 'table',
        '#caption' => $this->t('Candidatas à revisão (sem referência em file_usage)'),
        '#header' => [$this->t('Arquivo'), $this->t('Tamanho'), $this->t('Motivos')],
        '#rows' => $candidatas,
        '#empty' => $this->t('Nenhuma imagem sem referência.'),
      ],
      'usos_arquivo' => [
        '#type' => 'table',
        '#caption' => $this->t('Uso de arquivos de imagem'),
        '#header' => [$this->t('Arquivo'), $this->t('Tamanho'), $this->t('Ação')],
        '#rows' => array_map(fn (array $arquivo): array => [
          $arquivo['filename'],
          round($arquivo['filesize'] / 1024, 1) . ' KB',
          Link::createFromRoute($this->t('Onde é usado'), 'aculta_image.file_usage', ['file' => $arquivo['fid']]),
        ], $this->audit->arquivos()),
        '#empty' => $this->t('Nenhum arquivo de imagem gerenciado.'),
      ],
    ];
  }

}
