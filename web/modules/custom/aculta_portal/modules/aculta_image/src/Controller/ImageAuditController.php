<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Controller;

use Drupal\aculta_image\Audit\ImageAudit;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Template\Attribute;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Relatório administrativo de imagens (somente leitura): resumo, duplicatas e candidatas à revisão.
 */
final class ImageAuditController extends ControllerBase {

  public function __construct(private readonly ImageAudit $audit) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_image.audit'));
  }

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
    ];
  }

}
