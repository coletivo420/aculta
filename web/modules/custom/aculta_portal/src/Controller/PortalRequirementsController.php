<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Drupal\aculta_portal\Deployer\DeployerStatusReader;
use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Administrative status report for the Portal's runtime integrations. */
final class PortalRequirementsController extends ControllerBase {

  /** @var array<string, string>|null */
  private ?array $composerRequirements = NULL;

  public function __construct(
    #[Autowire(service: 'theme_handler')]
    private readonly ThemeHandlerInterface $themes,
    #[Autowire(service: 'module_handler')]
    private readonly ModuleHandlerInterface $modules,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configs,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $account,
    #[Autowire(param: 'app.root')]
    private readonly string $appRoot,
    #[Autowire(service: 'aculta_portal.deployer_status_reader')]
    private readonly DeployerStatusReader $deployerStatus,
  ) {}

  /**
   * The Composer-backed modules integrated by the Portal.
   *
   * Constraints mirror composer.json. A row may represent multiple Drupal
   * extensions shipped by one Composer package.
   */
  private const MODULE_REQUIREMENTS = [
    [
      'name' => 'Drupal Core',
      'modules' => ['user', 'node', 'taxonomy', 'datetime', 'options', 'media'],
      'package' => 'drupal/core-recommended',
      'minimum' => '^11.4',
      'purpose' => 'Contas, conteúdo, campos e APIs de apresentação.',
    ],
    [
      'name' => 'Profile',
      'modules' => ['profile'],
      'package' => 'drupal/profile',
      'minimum' => '^1.14',
      'purpose' => 'Dados pessoais e endereço canônico do perfil customer.',
    ],
    [
      'name' => 'Address',
      'modules' => ['address'],
      'package' => 'drupal/address',
      'minimum' => '^2.0',
      'purpose' => 'Estrutura de endereço compartilhada com o Commerce.',
    ],
    [
      'name' => 'CEP Autocomplete',
      'modules' => ['cep_autocomplete'],
      'package' => 'drupal/cep_autocomplete',
      'minimum' => '^1.0',
      'purpose' => 'Consulta ViaCEP sobre o campo Address.',
      'warning' => 'O projeto não está coberto pela Drupal Security Advisory Policy.',
    ],
    [
      'name' => 'Login por usuário ou e-mail',
      'modules' => ['login_emailusername'],
      'package' => 'drupal/login_emailusername',
      'minimum' => '^3.0',
      'purpose' => 'Autenticação Drupal com username ou e-mail no mesmo campo.',
    ],
    [
      'name' => 'Change Mail Page',
      'modules' => ['change_mail_page'],
      'package' => 'drupal/change_mail_page',
      'minimum' => '^1.0.2',
      'purpose' => 'Formulário de solicitação de alteração do e-mail.',
    ],
    [
      'name' => 'Email Confirmer',
      'modules' => ['email_confirmer', 'email_confirmer_user'],
      'package' => 'drupal/email_confirmer',
      'minimum' => '^1.0',
      'purpose' => 'Confirmação do novo e-mail antes de atualizar Drupal User.',
    ],
    [
      'name' => 'Social API / Social Auth',
      'modules' => ['social_api', 'social_auth'],
      'package' => 'drupal/social_auth',
      'minimum' => '^4.1',
      'purpose' => 'Infraestrutura de autenticação e conexões sociais.',
    ],
    [
      'name' => 'Social Auth Google',
      'modules' => ['social_auth_google'],
      'package' => 'drupal/social_auth_google',
      'minimum' => '^4.0',
      'purpose' => 'Provedor Google; OAuth fica inativo até homologação externa.',
      'warning' => 'Credenciais Google não configuradas neste ambiente pré-deploy.',
    ],
    [
      'name' => 'Image, Crop e Image Widget Crop',
      'modules' => ['image', 'crop', 'image_widget_crop'],
      'package' => 'drupal/crop',
      'minimum' => '^2.6',
      'purpose' => 'Foto de perfil, enquadramento 1:1 e processamento de imagem.',
      'related_package' => 'drupal/image_widget_crop',
      'related_minimum' => '^3.0',
    ],
    [
      'name' => 'Drupal Commerce',
      'modules' => ['commerce', 'commerce_checkout', 'commerce_order', 'commerce_payment', 'commerce_store'],
      'package' => 'drupal/commerce',
      'minimum' => '^3.3',
      'purpose' => 'Store, checkout, pedidos e pagamentos; fonte de verdade financeira.',
    ],
    [
      'name' => 'Commerce Donation Flow',
      'modules' => ['commerce_donation_flow'],
      'package' => 'drupal/commerce_donation_flow',
      'minimum' => '^1.2',
      'purpose' => 'Fluxo de apoio único e Donation Order Item.',
    ],
    [
      'name' => 'Commerce Mercado Pago',
      'modules' => ['commerce_mercado_pago'],
      'package' => 'drupal/commerce_mercado_pago',
      'minimum' => '^3.0@RC',
      'purpose' => 'Gateway de pagamento. Até a versão 1.0 é habilitado com chaves de teste; chaves de produção dependem de decisão do responsável.',
      'warning' => 'Versão RC sem cobertura da Drupal Security Advisory Policy. Enquanto a versão não for estável, o gateway fica restrito às chaves de teste.',
    ],
    [
      'name' => 'Key',
      'modules' => ['key'],
      'package' => 'drupal/key',
      'minimum' => '^1.22',
      'purpose' => 'Referências a segredos fornecidos em runtime, sem persistir valores.',
    ],
    [
      'name' => 'CAPTCHA / Cloudflare Turnstile',
      'modules' => ['captcha', 'turnstile'],
      'package' => 'drupal/captcha',
      'minimum' => '^2.0',
      'purpose' => 'Proteção anti-bot dos formulários públicos autorizados.',
      'related_package' => 'drupal/turnstile',
      'related_minimum' => '^1.2',
      'warning' => 'Chaves reais e homologação externa permanecem adiadas.',
    ],
    [
      'name' => 'Mailer Plus',
      'modules' => ['symfony_mailer', 'mailer_override'],
      'package' => 'drupal/symfony_mailer',
      'minimum' => '^2.0',
      'purpose' => 'Transporte de e-mail transacional pelo SMTP2GO, com o envio do Core e do Webform pelo Mailer Plus.',
      'warning' => 'Entrega em produção depende do RC e do provisionamento das credenciais SMTP2GO.',
    ],
    [
      'name' => 'Agreement',
      'modules' => ['agreement'],
      'package' => 'drupal/agreement',
      'minimum' => '^3.0',
      'purpose' => 'Registro do aceite dos termos no fluxo de conta.',
    ],
    [
      'name' => 'Metatag / Schema Metatag',
      'modules' => ['metatag', 'schema_metatag'],
      'package' => 'drupal/metatag',
      'minimum' => '^2.2',
      'purpose' => 'Metadados institucionais e Schema.org integrados pelo Portal.',
      'related_package' => 'drupal/schema_metatag',
      'related_minimum' => '^3.0',
    ],
    [
      'name' => 'Webform',
      'modules' => ['webform'],
      'package' => 'drupal/webform',
      'minimum' => '^6.3',
      'purpose' => 'Formulários institucionais relacionados, como Faça Parte.',
    ],
  ];

  /** Administrative landing page for the ACULTA Portal section. */
  public function overview(): array {
    $rows = [];
    foreach ([
      ['aculta_portal.requirements', $this->t('Status'), $this->t('Confira os requisitos, versões instaladas e pendências conhecidas das integrações do Portal.')],
      ['aculta_portal.turnstile_settings', $this->t('Turnstile (anti-bot)'), $this->t('Ativar ou desativar o desafio Turnstile em cadastro, login, recuperação de senha e contato. Só ativa com a chave configurada no ambiente.')],
      ['aculta_portal.support_settings', $this->t('Configurações do Apoio'), $this->t('Edite o texto institucional exibido na página pública Apoie.')],
      ['aculta_portal.deployer_status', $this->t('ACULTA Deployer: status'), $this->t('Relatório de fronteiras, correções de deploy abertas e credenciais por ambiente. Somente leitura.')],
      ['aculta_portal.secrets_import', $this->t('Credenciais do ambiente'), $this->t('Cadastrar e conferir as credenciais do ambiente. Valores salvos ficam mascarados e podem ser revelados pelo botão de olho.')],
      ['entity.simple_sitemap.collection', $this->t('Sitemaps: variantes'), $this->t('Variantes de sitemap por purpose (MAIN, SUPPORT, WIKI, MAGAZINE, COURSES) e o índice em /sitemap.xml.')],
      ['simple_sitemap.settings', $this->t('Sitemaps: configurações'), $this->t('Configurações gerais do sitemap (base URL, geração e limites).')],
    ] as [$route, $title, $description]) {
      $url = Url::fromRoute($route);
      if (!$url->access($this->account)) {
        continue;
      }
      $rows[] = [
        'area' => ['data' => ['#type' => 'link', '#title' => $title, '#url' => $url]],
        'description' => ['data' => ['#plain_text' => $description]],
      ];
    }

    return [
      'intro' => ['#plain_text' => $this->t('Gerencie as opções administrativas e consulte os requisitos das integrações do módulo aculta_portal.')],
      'options' => [
        '#type' => 'table',
        '#header' => [$this->t('Opção'), $this->t('Descrição')],
        '#rows' => $rows,
        '#empty' => $this->t('Não há opções administrativas disponíveis para sua conta.') ,
      ],
      '#cache' => ['contexts' => ['user.permissions'], 'max-age' => 0],
    ];
  }

  /** Builds the report without exposing configuration or credential values. */
  public function report(): array {
    $rows = [];
    foreach (self::MODULE_REQUIREMENTS as $requirement) {
      $rows[] = $this->buildModuleRow($requirement);
    }
    $rows[] = $this->buildBaseThemeRow();
    $rows[] = $this->buildThemeRow();

    $build = [
      'intro' => [
        '#plain_text' => $this->t('Este relatório compara os requisitos do aculta_portal com as extensões ativas e as versões disponíveis neste código local. Ele não exibe valores de configuração nem segredos.'),
      ],
      'legend' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-portal-requirements__legend'], 'aria-label' => $this->t('Legenda de status')],
        'ok' => ['#plain_text' => $this->t('✔ OK: instalado, habilitado e compatível.')],
        'attention' => ['#plain_text' => $this->t('⚠ Atenção: integração deliberadamente pendente ou risco conhecido.')],
        'error' => ['#plain_text' => $this->t('✖ Erro: requisito ausente, desabilitado ou incompatível.')],
      ],
      'requirements' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Módulo / tema'),
          $this->t('Função integrada'),
          $this->t('Versão mínima'),
          $this->t('Versão instalada'),
          $this->t('Status'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum requisito foi encontrado.'),
        '#attributes' => ['class' => ['aculta-portal-requirements']],
      ],
      'note' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'text' => ['#plain_text' => $this->t('Os avisos sobre Google e Turnstile refletem o estado pré-deploy intencional. Até a versão 1.0, o gateway Mercado Pago fica habilitado com chaves de teste. A compatibilidade de versão não significa que um serviço externo foi homologado.')],
      ],
      'indexing' => $this->buildIndexingSection(),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['config:core.extension', 'config:system.theme'],
        'max-age' => 0,
      ],
    ];

    $build['#attached']['library'][] = 'system/admin';
    $build['#attached']['library'][] = 'aculta_portal/requirements-report';
    return $build;
  }

  /**
   * Indexação: regra do ambiente declarado e último resultado do deployer. O Portal não executa
   * a verificação; ela é feita por `aculta-deployer robots`, que grava var/deployer/status.json.
   */
  private function buildIndexingSection(): array {
    $environment = DeployerEnvironment::current(dirname($this->appRoot));
    $rule = $environment === 'production'
      ? $this->t('Produção: indexável em todos os hosts. Sem noindex e com o sitemap do índice anunciado no robots.txt.')
      : $this->t('Teste: não indexável em todos os hosts (noindex, nofollow, noarchive). Caminhos privados mantêm noindex.');
    $section = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-portal-requirements__indexing']],
      'heading' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $this->t('Indexação')],
      'environment' => ['#plain_text' => $this->t('Ambiente declarado: @env.', ['@env' => $environment])],
      'rule' => ['#plain_text' => (string) $rule],
    ];
    $result = $this->deployerStatus->indexing();
    if ($result === NULL) {
      $section['last'] = ['#plain_text' => $this->t('Nenhuma verificação registrada. Execute aculta-deployer robots no ambiente para publicar o resultado.')];
      return $section;
    }
    $checkedAt = (string) ($result['checked_at'] ?? '');
    $section['last'] = ['#plain_text' => $this->t('Última verificação: @at (ambiente @env).', ['@at' => $checkedAt, '@env' => (string) ($result['environment'] ?? '')])];
    if (($result['environment'] ?? NULL) !== $environment) {
      $section['mismatch'] = ['#plain_text' => $this->t('⚠ O resultado publicado é de outro ambiente. Execute aculta-deployer robots neste ambiente.')];
    }
    $section['summary'] = ['#plain_text' => ($result['ok'] ?? FALSE) === TRUE
      ? $this->t('✔ OK: @passed verificações aprovadas.', ['@passed' => (int) ($result['passed'] ?? 0)])
      : $this->t('✖ Falha: @failed verificação(ões) com problema.', ['@failed' => (int) ($result['failed'] ?? 0)]),
    ];
    $failed = is_array($result['failed_urls'] ?? NULL) ? $result['failed_urls'] : [];
    if ($failed !== []) {
      $section['failed'] = ['#theme' => 'item_list', '#items' => array_map('strval', $failed)];
    }
    return $section;
  }

  /** Builds one module requirement row. */
  private function buildModuleRow(array $requirement): array {
    $missing = [];
    foreach ($requirement['modules'] as $module) {
      if (!$this->modules->moduleExists($module)) {
        $missing[] = $module;
      }
    }

    $minimum = $this->minimumConstraint($requirement['package'], $requirement['minimum']);
    $version = $this->packageVersion($requirement['package']);
    $compatible = $version !== NULL && $this->versionSatisfies($version, $minimum);
    $related_minimum = NULL;
    if (!empty($requirement['related_package'])) {
      $related_minimum = $this->minimumConstraint($requirement['related_package'], $requirement['related_minimum']);
      $related_version = $this->packageVersion($requirement['related_package']);
      $related_compatible = $related_version !== NULL && $this->versionSatisfies($related_version, $related_minimum);
      $version = $version && $related_version ? $version . ' / ' . $related_version : ($version ?: $related_version);
      $compatible = $compatible && $related_compatible;
    }

    if ($missing || !$compatible) {
      $status = 'error';
      $status_label = $missing ? $this->t('Erro — extensão desabilitada ou ausente: @modules', ['@modules' => implode(', ', $missing)]) : $this->t('Erro — versão ausente ou abaixo do requisito.');
    }
    elseif (!empty($requirement['warning'])) {
      $status = 'attention';
      $status_label = $this->t('Atenção — @warning', ['@warning' => $requirement['warning']]);
    }
    else {
      $status = 'ok';
      $status_label = $this->t('OK — extensões habilitadas e compatíveis.');
    }

    return [
      'name' => ['data' => ['#plain_text' => $requirement['name'] . ' (' . implode(', ', $requirement['modules']) . ')']],
      'purpose' => ['data' => ['#plain_text' => $requirement['purpose']]],
      'minimum' => ['data' => ['#plain_text' => $minimum . ($related_minimum ? ' / ' . $related_minimum : '')]],
      'installed' => ['data' => ['#plain_text' => $version ?: $this->t('Não encontrado')]],
      'status' => [
        'data' => ['#plain_text' => $this->statusSymbol($status) . ' ' . $status_label],
        'class' => ['aculta-portal-requirements__status', 'aculta-portal-requirements__status--' . $status],
      ],
    ];
  }

  /** Builds the enabled public theme row. */
  private function buildBaseThemeRow(): array {
    $exists = $this->themes->themeExists('bootstrap5');
    $version = $this->packageVersion('drupal/bootstrap5');
    $minimum = $this->minimumConstraint('drupal/bootstrap5', '4.0.8');
    $compatible = $version !== NULL && $this->versionSatisfies($version, $minimum);
    $status = $exists && $compatible ? 'ok' : 'error';
    return [
      'name' => ['data' => ['#plain_text' => 'Tema base Bootstrap 5 (bootstrap5)']],
      'purpose' => ['data' => ['#plain_text' => $this->t('Tema base declarado pelo tema público ACULTA420.')]],
      'minimum' => ['data' => ['#plain_text' => $minimum]],
      'installed' => ['data' => ['#plain_text' => $version ?: $this->t('Não encontrado')]],
      'status' => [
        'data' => ['#plain_text' => $this->statusSymbol($status) . ' ' . ($status === 'ok' ? $this->t('OK — tema base disponível.') : $this->t('Erro — tema base ausente ou incompatível.'))],
        'class' => ['aculta-portal-requirements__status', 'aculta-portal-requirements__status--' . $status],
      ],
    ];
  }

  /** Builds the enabled public theme row. */
  private function buildThemeRow(): array {
    $theme_exists = $this->themes->themeExists('aculta420');
    $default_theme = $this->configs->get('system.theme')->get('default');
    $enabled = $theme_exists && $default_theme === 'aculta420';
    $status = $enabled ? 'ok' : 'error';
    return [
      'name' => ['data' => ['#plain_text' => 'Tema ACULTA420 (aculta420)']],
      'purpose' => ['data' => ['#plain_text' => $this->t('Identidade visual global, componentes, header e footer usados pelo Portal.')]],
      'minimum' => ['data' => ['#plain_text' => '^11 (core_version_requirement)']],
      'installed' => ['data' => ['#plain_text' => $theme_exists ? $this->t('Tema custom do repositório; sem versão Composer') : $this->t('Não encontrado')]],
      'status' => [
        'data' => ['#plain_text' => $this->statusSymbol($status) . ' ' . ($enabled ? $this->t('OK — tema padrão habilitado.') : $this->t('Erro — tema ausente ou não é o tema padrão.'))],
        'class' => ['aculta-portal-requirements__status', 'aculta-portal-requirements__status--' . $status],
      ],
    ];
  }

  /** Returns an installed Composer package version when available. */
  private function packageVersion(string $package): ?string {
    if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled($package)) {
      return NULL;
    }
    return InstalledVersions::getPrettyVersion($package)
      ?: InstalledVersions::getVersion($package);
  }

  /** Reads the actual project requirement, falling back to the audited map. */
  private function minimumConstraint(string $package, string $fallback): string {
    if ($this->composerRequirements === NULL) {
      $file = dirname($this->appRoot) . '/composer.json';
      $decoded = is_readable($file) ? json_decode((string) file_get_contents($file), TRUE) : NULL;
      $this->composerRequirements = is_array($decoded['require'] ?? NULL) ? $decoded['require'] : [];
    }
    return $this->composerRequirements[$package] ?? $fallback;
  }

  /** Tests a Composer version constraint, including legacy Drupal versions. */
  private function versionSatisfies(string $version, string $constraint): bool {
    // Composer packages with older Drupal.org tags can expose versions like
    // "8.x-1.22"; Semver expects the project release portion only.
    $version = preg_replace('/^8\.x-/', '', $version);
    try {
      return Semver::satisfies($version, $constraint);
    }
    catch (\UnexpectedValueException) {
      return FALSE;
    }
  }

  /** Returns an accessible textual status symbol. */
  private function statusSymbol(string $status): string {
    return match ($status) {
      'ok' => '✔',
      'attention' => '⚠',
      default => '✖',
    };
  }

}
