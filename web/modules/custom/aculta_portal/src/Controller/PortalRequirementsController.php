<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Template\Attribute;
use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Drupal\aculta_portal\Deployer\DeployerStatusReader;
use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\aculta_portal\Status\PortalHelperRegistry;
use Drupal\aculta_portal\Status\PortalImplementationRegistry;
use Drupal\aculta_portal\Status\PhpExtensionRequirements;
use Drupal\aculta_portal_cep\Status\CepStatusReport;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\InfoParserInterface;
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
    #[Autowire(service: 'info_parser')]
    private readonly InfoParserInterface $infoParser,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configs,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $account,
    #[Autowire(param: 'app.root')]
    private readonly string $appRoot,
    #[Autowire(service: 'aculta_portal.deployer_status_reader')]
    private readonly DeployerStatusReader $deployerStatus,
    #[Autowire(service: 'aculta_portal.helper_registry')]
    private readonly PortalHelperRegistry $helperRegistry,
    #[Autowire(service: 'aculta_portal.implementation_registry')]
    private readonly PortalImplementationRegistry $implementationRegistry,
    #[Autowire(service: 'aculta_portal_cep.status_report')]
    private readonly CepStatusReport $cepStatus,
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
      'purpose' => 'Provedor Google ativo em modo de testes; aguardando homologação do Google.',
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
      'name' => 'Mercado Pago (SDK e gateway)',
      'modules' => ['aculta_pagamentos_mercadopago'],
      'package' => 'mercadopago/dx-php',
      'minimum' => '^3',
      'purpose' => 'SDK oficial e gateway Checkout Pro próprio do Portal (submódulo aculta_pagamentos_mercadopago). Chaves de produção dependem de decisão do responsável.',
      'warning' => 'Porte próprio do gateway, sem cobertura da Drupal Security Advisory Policy do upstream.',
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
      'name' => 'camada de envio E-Mails ACULTA',
      'modules' => ['aculta_mailer'],
      'package' => 'módulo do projeto (aculta_portal/aculta_mailer)',
      'minimum' => '0.1.0',
      'purpose' => 'Envio de e-mail do site e do Webform pelos transportes aculta_mailer_transport (SMTP Hostinger, SMTP2GO e Brevo).',
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
      'purpose' => 'Formulários institucionais publicados.',
    ],
  ];

  /** Composer packages used by custom modules that need explicit attribution. */
  private const PHP_EXTENSION_MODULE_REQUIREMENTS = [
    [
      'name' => 'ACULTA Image',
      'modules' => ['aculta_image'],
      'packages' => [
        'drupal/entity_usage',
        'drupal/media_duplicates',
        'drupal/imageapi_optimize',
        'drupal/imageapi_optimize_binaries',
      ],
    ],
    [
      'name' => 'ACULTA PDF Viewer',
      'modules' => ['aculta_pdf_viewer'],
      'packages' => [
        'drupal/pdfpreview',
        'drupal/imagemagick',
        'drupal/csp',
        'drupal/reporting',
      ],
    ],
  ];

  /** Administrative landing page for the ACULTA Portal section. */
  public function overview(): array {
    $rows = [];
    foreach ([
      ['aculta_portal.requirements', $this->t('Status'), $this->t('Confira os requisitos, versões instaladas e pendências conhecidas das integrações do Portal.')],
      ['aculta_portal.turnstile_settings', $this->t('Turnstile (anti-bot)'), $this->t('Ativar ou desativar o desafio Turnstile em cadastro, login, recuperação de senha e contato. Só ativa com a chave configurada no ambiente.')],
      ['aculta_portal_cep.settings', $this->t('Consulta de CEP'), $this->t('Banco de dados padrão e ordem das fontes de consulta de CEP (base local, ViaCEP e BrasilAPI), com a ativação de cada uma.')],
      ['aculta_portal.support_settings', $this->t('Configurações do Apoio'), $this->t('Edite o texto institucional exibido na página pública Apoie.')],
      ['aculta_portal.social_links_settings', $this->t('Redes sociais'), $this->t('Configure os links oficiais de Instagram, YouTube e Facebook exibidos na página LINKS.')],
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
        '#attributes' => ['data-portal-ui' => 'requirements-legend', 'aria-label' => $this->t('Legenda de status')],
        'ok' => ['#plain_text' => $this->t('✔ OK: instalado, habilitado e compatível.')],
        'attention' => ['#plain_text' => $this->t('⚠ Atenção: integração deliberadamente pendente ou risco conhecido.')],
        'error' => ['#plain_text' => $this->t('✖ Erro: requisito ausente, desabilitado ou incompatível.')],
      ],
      'active_portal_functions' => $this->buildActivePortalFunctions(),
      'portal_implementations' => $this->buildPortalImplementations(),
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
        '#attributes' => ['data-portal-ui' => 'requirements'],
      ],
      'php_extensions' => $this->buildPhpExtensionSection(),
      'note' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'notice-warning'],
        'text' => ['#plain_text' => $this->t('Os avisos sobre Google e Turnstile refletem o estado pré-deploy intencional. Até a versão 1.0, o gateway Mercado Pago fica habilitado com chaves de teste. A compatibilidade de versão não significa que um serviço externo foi homologado.')],
      ],
      'indexing' => $this->buildIndexingSection(),
      'cep' => $this->buildCepSection(),
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

  /** Reports the runtime PHP extensions required by the integrated modules. */
  private function buildPhpExtensionSection(): array {
    $lockFile = dirname($this->appRoot) . '/composer.lock';
    $decoded = is_readable($lockFile) ? json_decode((string) file_get_contents($lockFile), TRUE) : NULL;
    $lockAvailable = is_array($decoded['packages'] ?? NULL);
    $packages = $lockAvailable ? $decoded['packages'] : [];
    $extensionRows = PhpExtensionRequirements::build(
      array_merge(
        self::MODULE_REQUIREMENTS,
        self::PHP_EXTENSION_MODULE_REQUIREMENTS,
        [[
          'name' => 'Dependências de runtime do site (varredura global)',
          'modules' => [],
          'scan_all' => TRUE,
        ]],
      ),
      $packages,
      get_loaded_extensions(),
    );
    $rows = [];
    foreach ($extensionRows as $extension) {
      $status = $extension['loaded'] ? 'ok' : 'error';
      $rows[] = [
        'extension' => ['data' => ['#plain_text' => $extension['extension']]],
        'integrations' => ['data' => ['#plain_text' => implode(', ', $extension['integrations'])]],
        'modules' => ['data' => ['#plain_text' => $extension['modules'] === [] ? '—' : implode(', ', $extension['modules'])]],
        'packages' => ['data' => ['#plain_text' => implode(', ', $extension['packages'])]],
        'status' => [
          'data' => ['#plain_text' => $this->statusSymbol($status) . ' ' . ($extension['loaded']
            ? $this->t('OK — carregada neste PHP.')
            : $this->t('Erro — ausente ou desativada neste PHP.'))],
          'data-portal-ui' => 'requirements-status status-' . $status,
        ],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Extensões PHP necessárias'),
      '#open' => TRUE,
      'description' => ['#plain_text' => $this->t('Requisitos ext-* declarados no composer.lock pelos pacotes dos módulos integrados e suas dependências transitivas. O status verifica se a extensão está carregada no PHP que atende esta página; requisitos opcionais (suggest) e dependências de desenvolvimento não entram na lista.')],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Extensão PHP'),
          $this->t('Integração'),
          $this->t('Módulos Drupal'),
          $this->t('Pacote que declara o requisito'),
          $this->t('Status'),
        ],
        '#rows' => $rows,
        '#empty' => $lockAvailable
          ? $this->t('Não foi encontrado requisito ext-* nos pacotes registrados para as integrações.')
          : $this->t('Não foi possível ler o composer.lock; os requisitos de extensões PHP são desconhecidos.'),
        '#attributes' => ['data-portal-ui' => 'requirements-php-extensions'],
      ],
      '#cache' => ['contexts' => ['user.permissions'], 'max-age' => 0],
    ];
  }

  /**
   * Lists enabled Portal submodules from Drupal's extension registry.
   *
   * Names and summaries come from each module's own .info.yml; this avoids a
   * second manually maintained inventory that could drift from runtime state.
   */
  private function buildActivePortalFunctions(): array {
    $rows = [];
    $moduleList = $this->modules->getModuleList();
    foreach ($moduleList as $machineName => $extension) {
      $path = str_replace('\\', '/', $extension->getPath());
      if ($machineName !== 'aculta_portal' && !str_starts_with($path, 'modules/custom/aculta_portal/modules/')) {
        continue;
      }
      $info = $this->infoParser->parse($this->appRoot . '/' . $path . '/' . $machineName . '.info.yml');
      $rows[] = [
        'name' => ['data' => ['#plain_text' => (string) ($info['name'] ?? $machineName)]],
        'function' => ['data' => ['#plain_text' => (string) ($info['description'] ?? '')]],
        'machine_name' => ['data' => ['#plain_text' => $machineName]],
      ];
    }
    usort($rows, static fn (array $a, array $b): int => strcmp(
      (string) ($a['name']['data']['#plain_text'] ?? ''),
      (string) ($b['name']['data']['#plain_text'] ?? ''),
    ));

    return [
      '#type' => 'details',
      '#title' => $this->t('ACULTA Portal e submódulos ativos'),
      '#open' => TRUE,
      'description' => ['#plain_text' => $this->t('Lista derivada das extensões ACULTA Portal habilitadas no Drupal. Nome e resumo vêm dos arquivos .info.yml, sem cadastro paralelo de status.')],
      'items' => [
        '#type' => 'table',
        '#header' => [$this->t('Função'), $this->t('Resumo'), $this->t('Identificador')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum submódulo do Portal está habilitado.'),
      ],
      '#cache' => ['tags' => ['config:core.extension']],
    ];
  }

  /** Lists active, explicitly registered Portal implementation services. */
  private function buildPortalImplementations(): array {
    return [
      '#type' => 'details',
      '#title' => $this->t('Implementações do ACULTA Portal'),
      '#open' => TRUE,
      'description' => ['#plain_text' => $this->t('Inventário de serviços reutilizáveis e integrações runtime aplicadas pelo Portal. Os itens ativos vêm das tags do container Drupal; os resumos e contratos são mantidos no registro documentado.')],
      'helpers_title' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $this->t('Helpers ativos')],
      'helpers' => [
        '#type' => 'table',
        '#header' => [$this->t('Helper'), $this->t('Função'), $this->t('Serviço'), $this->t('Documentação')],
        '#rows' => $this->helperRegistry->rows(),
        '#empty' => $this->t('Nenhum helper está registrado.'),
      ],
      'extras_title' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $this->t('Extras aplicados')],
      'extras' => [
        '#type' => 'table',
        '#header' => [$this->t('Implementação'), $this->t('Efeito aplicado'), $this->t('Serviço'), $this->t('Documentação')],
        '#rows' => $this->implementationRegistry->rows(),
        '#empty' => $this->t('Nenhuma implementação extra está registrada.'),
      ],
      '#cache' => ['tags' => ['config:core.extension'], 'max-age' => 0],
    ];
  }

  /**
   * Indexação: regra do ambiente declarado e último resultado do deployer. O Portal não executa
   * a verificação; ela é feita pela verificação de robots do ACULTA Deployer, que grava var/deployer/status.json.
   */
  private function buildIndexingSection(): array {
    $environment = DeployerEnvironment::current(dirname($this->appRoot));
    $rule = $environment === 'production'
      ? $this->t('Produção: indexável em todos os hosts. Sem noindex e com o sitemap do índice anunciado no robots.txt.')
      : $this->t('Teste: não indexável em todos os hosts (noindex, nofollow, noarchive). Caminhos privados mantêm noindex.');
    $result = $this->deployerStatus->indexing();
    $rows = [
      [$this->t('Ambiente declarado'), $environment],
      [$this->t('Regra esperada'), (string) $rule],
    ];
    if ($result === NULL) {
      $rows[] = [$this->t('Última verificação'), $this->t('Nenhuma verificação registrada.')];
      $rows[] = [$this->t('Como publicar'), $this->t('Execute a verificação de robots do ACULTA Deployer no ambiente.')];
    }
    else {
      $rows[] = [$this->t('Última verificação'), $this->t('@at (ambiente @env)', ['@at' => (string) ($result['checked_at'] ?? ''), '@env' => (string) ($result['environment'] ?? '')])];
      if (($result['environment'] ?? NULL) !== $environment) {
        $rows[] = [$this->t('Atenção'), $this->t('O resultado publicado é de outro ambiente. Execute a verificação de robots neste ambiente.')];
      }
      $rows[] = [$this->t('Resultado'), ($result['ok'] ?? FALSE) === TRUE
        ? $this->t('OK: @passed verificações aprovadas.', ['@passed' => (int) ($result['passed'] ?? 0)])
        : $this->t('Falha: @failed verificação(ões) com problema.', ['@failed' => (int) ($result['failed'] ?? 0)]),
      ];
      $failed = is_array($result['failed_urls'] ?? NULL) ? array_map('strval', $result['failed_urls']) : [];
      if ($failed !== []) {
        $rows[] = [$this->t('URLs com falha'), ['#theme' => 'item_list', '#items' => $failed]];
      }
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'requirements-indexing'],
      'heading' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => $this->t('Indexação')],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Item'), $this->t('Valor')],
        '#rows' => $rows,
        '#attributes' => ['data-portal-ui' => 'requirements-indexing-table'],
      ],
    ];
  }

  /**
   * Consultas de CEP (aculta_portal_cep): último resultado das APIs (cron) e versão da base local, pela data de hoje.
   * Mesma legenda e mesmas classes de status das demais linhas do relatório.
   */
  private function buildCepSection(): array {
    $rows = [];
    foreach ($this->cepStatus->rows() as $row) {
      $rows[] = [
        'item' => ['data' => ['#plain_text' => $row['item']]],
        'value' => ['data' => ['#plain_text' => $row['value']]],
        'status' => [
          'data' => ['#plain_text' => $this->statusSymbol($row['status']) . ' ' . $this->statusText($row['status'])],
          'data-portal-ui' => 'requirements-status status-' . $row['status'],
        ],
        'detail' => ['data' => ['#plain_text' => $row['detail']]],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'requirements-cep'],
      'heading' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => $this->t('Consulta de CEP')],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Item'), $this->t('Valor'), $this->t('Status'), $this->t('Detalhe')],
        '#rows' => $rows,
        '#empty' => $this->t('Consulta de CEP não está habilitada.'),
        '#attributes' => ['data-portal-ui' => 'requirements-cep-table'],
      ],
    ];
  }

  /** Texto do status, junto do símbolo (OK, Atenção ou Erro). */
  private function statusText(string $status): string {
    return match ($status) {
      'ok' => (string) $this->t('OK'),
      'attention' => (string) $this->t('Atenção'),
      default => (string) $this->t('Erro'),
    };
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
        'data-portal-ui' => 'requirements-status status-' . $status,
      ],
    ];
  }

  /** Builds the enabled public theme row. */
  private function buildBaseThemeRow(): array {
    // Tema base do ACULTA420 (base theme: bootstrap). O pacote é drupal/bootstrap (Bootstrap 5), não drupal/bootstrap5.
    $exists = $this->themes->themeExists('bootstrap');
    $version = $this->packageVersion('drupal/bootstrap');
    $minimum = $this->minimumConstraint('drupal/bootstrap', '^5.0');
    $compatible = $version !== NULL && $this->versionSatisfies($version, $minimum);
    $status = $exists && $compatible ? 'ok' : 'error';
    return [
      'name' => ['data' => ['#plain_text' => 'Tema base Bootstrap 5 (bootstrap)']],
      'purpose' => ['data' => ['#plain_text' => $this->t('Tema base declarado pelo tema público ACULTA420.')]],
      'minimum' => ['data' => ['#plain_text' => $minimum]],
      'installed' => ['data' => ['#plain_text' => $version ?: $this->t('Não encontrado')]],
      'status' => [
        'data' => ['#plain_text' => $this->statusSymbol($status) . ' ' . ($status === 'ok' ? $this->t('OK — tema base disponível.') : $this->t('Erro — tema base ausente ou incompatível.'))],
        'data-portal-ui' => 'requirements-status status-' . $status,
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
        'data-portal-ui' => 'requirements-status status-' . $status,
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
