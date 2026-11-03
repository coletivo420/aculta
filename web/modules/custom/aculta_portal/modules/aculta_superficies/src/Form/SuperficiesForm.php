<?php

declare(strict_types=1);

namespace Drupal\aculta_superficies\Form;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administra modo, slug, título e ativação de cada superfície (Fase 3.6).
 *
 * Rota em /painel-administrativo/aculta/caminhos, só no MAIN. Mostra, em
 * tabela somente leitura, o caminho público de cada superfície e se o
 * interruptor leitura_ativa aplica a configuração às rotas.
 */
final class SuperficiesForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly SurfaceConfigReader $reader,
    private readonly DomainPurposeManager $purposes,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('aculta_portal.surface_config_reader'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  public function getFormId(): string {
    return 'aculta_superficies_form';
  }

  protected function getEditableConfigNames(): array {
    return [SurfaceConfigReader::CONFIG_NAME];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['caminhos'] = [
      '#type' => 'table',
      '#caption' => $this->t('Caminhos públicos (somente leitura)'),
      '#header' => [
        $this->t('Superfície'),
        $this->t('Modo'),
        $this->t('Caminho público'),
        $this->t('Ativa'),
        $this->t('Caminho em vigor'),
      ],
      '#rows' => $this->linhasDeCaminhos(),
      '#empty' => $this->t('Nenhuma superfície configurada.'),
    ];
    $form['aviso'] = [
      '#markup' => '<p>' . $this->t('Mudar o modo ou o slug não altera as rotas nesta versão. Antes de aplicar, confira o impacto na Conta e na sessão compartilhada.') . '</p>',
    ];
    $form['leitura_ativa'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Aplicar a configuração às rotas'),
      '#description' => $this->t('Desligado nesta versão. Ligar só depois do teste de rota no Runtime.'),
      '#default_value' => $this->reader->leituraAtiva(),
      '#disabled' => TRUE,
    ];
    foreach ($this->reader->superficies() as $purpose => $linha) {
      $form[$purpose] = [
        '#type' => 'details',
        '#title' => SurfaceConfigReader::TITULOS[$purpose] ?? $purpose,
        '#open' => TRUE,
      ];
      $form[$purpose]['ativa'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Ativa'),
        '#default_value' => $linha['ativa'],
        '#disabled' => $purpose === DomainSurfaceMap::MAIN,
      ];
      $form[$purpose]['modo'] = [
        '#type' => 'select',
        '#title' => $this->t('Modo'),
        '#options' => [
          'subdominio' => $this->t('Subdomínio'),
          'diretorio' => $this->t('Diretório'),
          'desativado' => $this->t('Desativado'),
        ],
        '#default_value' => $linha['modo'],
      ];
      $form[$purpose]['slug'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Slug (só para diretório)'),
        '#default_value' => $linha['slug'],
        '#maxlength' => 64,
      ];
      $form[$purpose]['titulo'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Título'),
        '#default_value' => $linha['titulo'],
        '#maxlength' => 80,
        '#required' => TRUE,
      ];
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach (SurfaceConfigReader::validar($this->valoresSubmetidos($form_state)) as $purpose => $mensagem) {
      $form_state->setErrorByName($purpose . '][slug', $mensagem);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $antes = $this->reader->superficies();
    $depois = $this->valoresSubmetidos($form_state);
    $this->config(SurfaceConfigReader::CONFIG_NAME)
      ->set('superficies', $depois)
      ->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Superfícies salvas.'));
    // O Portal não grava no registro de deploy (o repositório pode não
    // ser gravável em produção). Mostra a entrada que o operador registra com a CLI.
    foreach ($depois as $purpose => $linha) {
      $mudou = $linha['modo'] !== $antes[$purpose]['modo'] || $linha['slug'] !== $antes[$purpose]['slug'];
      if (!$mudou) {
        continue;
      }
      $this->messenger()->addWarning($this->t('@titulo mudou de modo ou slug. Registre a correção de deploy (canonical e sitemap) na ferramenta de deploy: página @pagina.', [
        '@titulo' => SurfaceConfigReader::TITULOS[$purpose] ?? $purpose,
        '@pagina' => $linha['modo'] === 'diretorio' ? '/' . $linha['slug'] : '(subdomínio)',
      ]));
    }
  }

  /**
   * Linhas da tabela de caminhos. Hosts vêm do Domain, nunca de texto fixo.
   *
   * @return array<int, array<int, string>>
   */
  private function linhasDeCaminhos(): array {
    $linhas = [];
    foreach ($this->reader->superficies() as $purpose => $linha) {
      $emVigor = $this->reader->caminhoDiretorioAtivo($purpose);
      if ($emVigor === NULL) {
        $emVigor = $this->reader->leituraAtiva()
          ? (string) $this->t('Não aplicável')
          : (string) $this->t('Não (leitura_ativa desligada)');
      }
      $linhas[] = [
        (string) (SurfaceConfigReader::TITULOS[$purpose] ?? $purpose),
        $this->rotuloModo($linha['modo']),
        $this->caminhoPublico($purpose, $linha),
        (string) ($linha['ativa'] ? $this->t('Sim') : $this->t('Não')),
        $emVigor,
      ];
    }
    return $linhas;
  }

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

  /**
   * @return array<string, array{modo: string, ativa: bool, slug: string, titulo: string}>
   */
  private function valoresSubmetidos(FormStateInterface $form_state): array {
    $saida = [];
    foreach (array_keys(DomainSurfaceMap::DOMAIN_IDS) as $purpose) {
      $saida[$purpose] = [
        'modo' => (string) $form_state->getValue([$purpose, 'modo']),
        'ativa' => $purpose === DomainSurfaceMap::MAIN ? TRUE : (bool) $form_state->getValue([$purpose, 'ativa']),
        'slug' => trim((string) $form_state->getValue([$purpose, 'slug'])),
        'titulo' => trim((string) $form_state->getValue([$purpose, 'titulo'])),
      ];
    }
    return $saida;
  }

}
