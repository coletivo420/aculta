<?php

declare(strict_types=1);

namespace Drupal\aculta_superficies\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lista somente leitura dos caminhos públicos, com o botão Editar (modal).
 *
 * Rota em /painel-administrativo/aculta/caminhos, só no MAIN.
 */
final class SurfaceListController extends ControllerBase {

  public function __construct(
    private readonly SurfaceConfigReader $reader,
    private readonly DomainPurposeManager $purposes,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.surface_config_reader'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * Página com a tabela de caminhos.
   */
  public function pagina(): array {
    $leitura = $this->reader->leituraAtiva()
      ? $this->t('Ligada: os caminhos de diretório valem nas rotas.')
      : $this->t('Desligada nesta versão: as rotas não mudam com esta configuração.');
    return [
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      'leitura' => [
        '#markup' => '<p>' . $this->t('Aplicação às rotas: @estado', ['@estado' => $leitura]) . '</p>',
      ],
      'aviso' => [
        '#markup' => '<p>' . $this->t('Mudar o modo ou o slug não altera as rotas nesta versão. Antes de aplicar, confira o impacto na Conta e na sessão compartilhada.') . '</p>',
      ],
      'caminhos' => [
        '#type' => 'table',
        '#caption' => $this->t('Caminhos públicos'),
        '#header' => [
          $this->t('Superfície'),
          $this->t('Modo'),
          $this->t('Caminho público'),
          $this->t('Ativa'),
          $this->t('Caminho em vigor'),
          $this->t('Ações'),
        ],
        '#rows' => $this->linhas(),
        '#empty' => $this->t('Nenhuma superfície configurada.'),
      ],
    ];
  }

  /**
   * @return array<int, array<int, mixed>>
   */
  private function linhas(): array {
    $linhas = [];
    foreach ($this->reader->superficies() as $purpose => $linha) {
      $emVigor = $this->reader->caminhoDiretorioAtivo($purpose);
      if ($emVigor === NULL) {
        $emVigor = $this->reader->leituraAtiva()
          ? (string) $this->t('Não aplicável')
          : (string) $this->t('Não (leitura_ativa desligada)');
      }
      $titulo = SurfaceConfigReader::TITULOS[$purpose] ?? $purpose;
      $editar = Link::createFromRoute($this->t('Editar'), 'aculta_superficies.editar', ['purpose' => $purpose], [
        'attributes' => [
          'class' => ['use-ajax', 'button', 'button--small'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => \Drupal\Component\Serialization\Json::encode(['width' => 640]),
          'aria-label' => (string) $this->t('Editar @titulo', ['@titulo' => $titulo]),
        ],
      ])->toRenderable();
      $linhas[] = [
        (string) $titulo,
        $this->rotuloModo($linha['modo']),
        $this->caminhoPublico($purpose, $linha),
        (string) ($linha['ativa'] ? $this->t('Sim') : $this->t('Não')),
        $emVigor,
        ['data' => $editar],
      ];
    }
    return $linhas;
  }

  /**
   * Hosts vêm do Domain, nunca de texto fixo.
   */
  private function caminhoPublico(string $purpose, array $linha): string {
    if ($linha['modo'] === 'desativado') {
      return '—';
    }
    if ($linha['modo'] === 'diretorio') {
      $principal = $this->purposes->getDomain(DomainSurfaceMap::MAIN);
      $host = $principal !== NULL ? $principal->getHostname() : '(sem Domain principal)';
      $caminho = $this->reader->caminhoDiretorioConfigurado($purpose);
      return $caminho !== NULL ? $host . $caminho : $host . ' (revisar slug ou ativação)';
    }
    $dominio = $this->purposes->getDomain($purpose);
    return $dominio !== NULL ? $dominio->getHostname() . '/' : '(sem Domain)';
  }

  private function rotuloModo(string $modo): string {
    return match ($modo) {
      'diretorio' => (string) $this->t('Diretório'),
      'desativado' => (string) $this->t('Desativado'),
      default => (string) $this->t('Subdomínio'),
    };
  }

}
