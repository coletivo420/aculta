<?php

declare(strict_types=1);

namespace Drupal\aculta_paths\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Drupal\aculta_paths\Service\DnsStatusChecker;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lista dos caminhos públicos, com Editar (modal) e status de DNS.
 *
 * Rota em /painel-administrativo/aculta/caminhos, só no MAIN. O principal
 * não aparece na lista: o host dele é a origem dos demais.
 */
final class SurfaceListController extends ControllerBase {

  /**
   * Módulo ACULTA que é dono de cada superfície (somente leitura).
   */
  private const MODULOS = [
    'account' => 'aculta_user',
    'support' => 'aculta_portal',
    'magazine' => 'aculta_magazine',
    'wiki' => 'aculta_wiki',
    'shop' => 'aculta_commerce',
    'courses' => 'aculta_lms',
    'forum' => 'aculta_forum',
    'links' => 'aculta_linkweed',
    'eventos' => 'aculta_events',
  ];

  public function __construct(
    private readonly SurfaceConfigReader $reader,
    private readonly DomainPurposeManager $purposes,
    private readonly DnsStatusChecker $dns,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.surface_config_reader'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('aculta_paths.dns_status'),
    );
  }

  /**
   * Página com a tabela de caminhos e a orientação de DNS.
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
          $this->t('Módulo ACULTA'),
          $this->t('Nome do Site'),
          $this->t('Modo'),
          $this->t('Caminho público'),
          $this->t('Ativa'),
          $this->t('Status do DNS'),
          $this->t('Ações'),
        ],
        '#rows' => $this->linhas(),
        '#empty' => $this->t('Nenhuma superfície configurada.'),
      ],
      'dns' => $this->orientacaoDns(),
    ];
  }

  /**
   * @return array<int, array<int, mixed>>
   */
  private function linhas(): array {
    $linhas = [];
    foreach ($this->reader->superficies() as $purpose => $linha) {
      if ($purpose === DomainSurfaceMap::MAIN) {
        continue;
      }
      $editar = Link::createFromRoute($this->t('Editar'), 'aculta_paths.editar', ['purpose' => $purpose], [
        'attributes' => [
          'class' => ['use-ajax'],
          'data-portal-ui' => 'surface-list-edit-action',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 640]),
          'aria-label' => (string) $this->t('Editar @nome', ['@nome' => $linha['titulo']]),
        ],
      ])->toRenderable();
      $linhas[] = [
        self::MODULOS[$purpose] ?? (string) $this->t('(sem módulo)'),
        $linha['titulo'],
        $this->rotuloModo($linha['modo']),
        $this->caminhoPublico($purpose, $linha),
        (string) ($linha['ativa'] ? $this->t('Sim') : $this->t('Não')),
        $this->statusDns($purpose, $linha),
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

  /**
   * Status do DNS: só verifica o subdomínio; diretório não se aplica.
   */
  private function statusDns(string $purpose, array $linha): string {
    if ($linha['modo'] === 'diretorio') {
      return (string) $this->t('Modo Diretório, Não Aplicável');
    }
    if ($linha['modo'] === 'desativado') {
      return (string) $this->t('Modo Desativado, Não Aplicável');
    }
    $dominio = $this->purposes->getDomain($purpose);
    if ($dominio === NULL) {
      return (string) $this->t('Sem Domain');
    }
    return $this->dns->status((string) $dominio->getHostname())['texto'];
  }

  /**
   * Explicação de como configurar o DNS, com o host principal do Domain.
   */
  private function orientacaoDns(): array {
    $principal = $this->purposes->getDomain(DomainSurfaceMap::MAIN);
    $host = $principal !== NULL ? (string) $principal->getHostname() : (string) $this->t('(sem Domain principal)');
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['superficies-dns']],
      'titulo' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Como configurar o DNS dos subdomínios'),
      ],
      'passos' => [
        '#theme' => 'item_list',
        '#list_type' => 'ol',
        '#items' => [
          $this->t('No painel onde o domínio principal (@host) é administrado, crie um registro para cada subdomínio em modo Subdomínio.', ['@host' => $host]),
          $this->t('Tipo CNAME. Nome: só o prefixo do subdomínio (ex.: wiki420). Valor: @host.', ['@host' => $host]),
          $this->t('Um nome com CNAME não pode ter outros registros. Remova registros antigos desse nome antes de criar o CNAME.'),
          $this->t('Alternativa: registro A do subdomínio, com o mesmo IP do principal. Use esta opção se o provedor não aceitar CNAME no nome.'),
          $this->t('A propagação pode levar até 24 horas. Esta tabela consulta o DNS e atualiza o status a cada 5 minutos.'),
          $this->t('O servidor também precisa responder ao subdomínio, com certificado HTTPS e a configuração do Apache. Isso é feito pelo administrador do servidor, não nesta tela.'),
        ],
      ],
    ];
  }

  private function rotuloModo(string $modo): string {
    return match ($modo) {
      'diretorio' => (string) $this->t('Diretório'),
      'desativado' => (string) $this->t('Desativado'),
      default => (string) $this->t('Subdomínio'),
    };
  }

}
