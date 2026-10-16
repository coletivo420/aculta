<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Status;

/** Curated metadata for reusable Portal services shown in administrative Status. */
final class PortalHelperRegistry {

  /**
   * Human-readable metadata keyed by the registered Drupal service ID.
   *
   * The service_id_collector supplies only the IDs whose service definitions
   * are active. Keeping text here makes this registry the single source for the
   * panel summaries; detailed contracts remain in the linked documentation.
   *
   * @var array<string, array{label: string, summary: string, docs: string}>
   */
  private const DEFINITIONS = [
    'aculta_user.achievement_definitions' => [
      'label' => 'Definições de conquistas',
      'summary' => 'Preserva o catálogo e as formas de concessão na Config API do User, sem motor de regras executáveis.',
      'docs' => 'web/modules/custom/aculta_portal/modules/aculta_game/docs/EXECUCAO.md',
    ],
    'aculta_game.mission_progress' => [
      'label' => 'Missões e progresso',
      'summary' => 'Apresenta missões iniciais e progresso privado a partir das concessões reais, sem pontuação ou storage paralelo.',
      'docs' => 'web/modules/custom/aculta_portal/modules/aculta_game/docs/EXECUCAO.md',
    ],
    'aculta_game.achievement_presentation' => [
      'label' => 'Exibição de conquistas',
      'summary' => 'Aplica privacidade, destaques e ordem às conquistas do perfil e da conta, sem expor evidências privadas.',
      'docs' => 'web/modules/custom/aculta_portal/modules/aculta_game/docs/EXECUCAO.md',
    ],
    'aculta_portal.account_courses' => [
      'label' => 'Cursos da conta',
      'summary' => 'Consulta participação e situação de cursos a partir das entidades LMS e Group.',
      'docs' => 'web/modules/custom/aculta_portal/README.md',
    ],
    'aculta_portal.account_shell_builder' => [
      'label' => 'Navegação da conta',
      'summary' => 'Monta a estrutura neutra da área Minha Conta e aplica access/cache aos itens.',
      'docs' => 'docs/portal/ACCOUNT-MENU.md',
    ],
    'aculta_portal.account_terms_policy' => [
      'label' => 'Política de termos da conta',
      'summary' => 'Consulta e valida o aceite dos termos no fluxo da conta.',
      'docs' => 'web/modules/custom/aculta_portal/README.md',
    ],
    'aculta_portal.auth_integration' => [
      'label' => 'Integrações de autenticação',
      'summary' => 'Resume estado e disponibilidade das integrações de autenticação configuradas.',
      'docs' => 'web/modules/custom/aculta_portal/README.md',
    ],
    'aculta_portal.cart_utility' => [
      'label' => 'Carrinho Commerce',
      'summary' => 'Obtém o carrinho ativo e constrói destinos canônicos no purpose MAIN.',
      'docs' => 'docs/portal/PAYMENT-DOMAIN-POLICY.md',
    ],
    'aculta_portal.color_mode_preference' => [
      'label' => 'Preferência de aparência',
      'summary' => 'Lê e grava a preferência individual de modo de cor usando User Data.',
      'docs' => 'docs/portal/IMPLEMENTATIONS.md',
    ],
    'aculta_portal.content_purpose_resolver' => [
      'label' => 'Propósito do conteúdo',
      'summary' => 'Resolve o Domain purpose responsável por apresentar uma entidade de conteúdo.',
      'docs' => 'docs/portal/DOMAIN-PRESENTATION-CONTRACT.md',
    ],
    'aculta_portal.deployer_status_reader' => [
      'label' => 'Leitura do estado do Deployer',
      'summary' => 'Lê o relatório neutro publicado pelo ACULTA Deployer sem executar sua CLI.',
      'docs' => 'web/modules/custom/aculta_portal/modules/aculta_deployer/README.md',
    ],
    'aculta_portal.domain_purpose' => [
      'label' => 'Domain Purpose Manager',
      'summary' => 'Resolve propósito atual e URLs canônicas entre Domains.',
      'docs' => 'docs/portal/DOMAIN-PRESENTATION-CONTRACT.md',
    ],
    'aculta_portal.email_confirmation_policy' => [
      'label' => 'Política de confirmação de e-mail',
      'summary' => 'Avalia confirmação, acesso e transições de estado da conta.',
      'docs' => 'docs/portal/SOURCE-OF-TRUTH.md',
    ],
    'aculta_portal.email_confirmation_requester' => [
      'label' => 'Solicitação de confirmação de e-mail',
      'summary' => 'Solicita a confirmação usando a integração Email Confirmer e User do Drupal.',
      'docs' => 'docs/portal/SOURCE-OF-TRUTH.md',
    ],
    'aculta_portal.lms_friendly_slugs' => [
      'label' => 'Slugs públicos de cursos',
      'summary' => 'Converte títulos de cursos, lições e atividades em slugs e os resolve para entidades LMS.',
      'docs' => 'docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md',
    ],
    'aculta_lms.course_completion_publisher' => [
      'label' => 'Eventos de conclusão do LMS',
      'summary' => 'Publica fatos de conclusão aprovada já persistidos pelo LMS, sem armazenar progresso ou nota.',
      'docs' => 'web/modules/custom/aculta_portal/modules/aculta_lms/docs/CONTRACT.md',
    ],
    'aculta_portal.mercado_pago_webhook_guard' => [
      'label' => 'Proteção de webhook de pagamento',
      'summary' => 'Valida estrutura e autenticidade das notificações recebidas do gateway.',
      'docs' => 'docs/portal/PAYMENT-DOMAIN-POLICY.md',
    ],
    'aculta_portal.registration_mail_policy' => [
      'label' => 'Política de e-mail de cadastro',
      'summary' => 'Coordena as mensagens transacionais de cadastro pela infraestrutura de e-mail existente.',
      'docs' => 'docs/portal/SOURCE-OF-TRUTH.md',
    ],
    'aculta_portal.registration_terms' => [
      'label' => 'Termos do cadastro',
      'summary' => 'Fornece os termos vigentes do módulo Agreement ao fluxo de cadastro.',
      'docs' => 'docs/portal/SOURCE-OF-TRUTH.md',
    ],
    'aculta_portal.search_results' => [
      'label' => 'Resultados de busca',
      'summary' => 'Consulta conteúdo publicado e autorizado usando Entity API e o propósito atual.',
      'docs' => 'web/modules/custom/aculta_portal/README.md',
    ],
    'aculta_portal.search_utility' => [
      'label' => 'Utilitários de busca',
      'summary' => 'Prepara links e rótulos da busca respeitando o Domain purpose.',
      'docs' => 'web/modules/custom/aculta_portal/README.md',
    ],
    'aculta_portal.secrets_manager' => [
      'label' => 'Referências de credenciais',
      'summary' => 'Lê referências Drupal Key e valores fornecidos pelo ambiente sem expor segredos no Status.',
    'docs' => 'docs/operations/SECRETS.md',
    ],
    'aculta_portal.turnstile_key_override' => [
      'label' => 'Chaves de ambiente do Turnstile',
      'summary' => 'Seleciona a referência Drupal Key correspondente ao ambiente declarado.',
      'docs' => 'web/modules/custom/aculta_portal/README.md',
    ],
    'aculta_portal.turnstile_toggle' => [
      'label' => 'Política Turnstile',
      'summary' => 'Verifica configuração, credencial e disponibilidade do desafio anti-bot.',
      'docs' => 'web/modules/custom/aculta_portal/README.md',
    ],
  ];

  /** @param string[] $activeHelperIds IDs coletados das definições ativas do container. */
  public function __construct(private readonly array $activeHelperIds) {}

  /**
   * Returns rows for registered active helpers, including an explicit missing-metadata state.
   *
   * @return array<int, array<string, array<string, mixed>>>
   *   Render API table rows.
   */
  public function rows(): array {
    $rows = [];
    $helperIds = $this->activeHelperIds;
    sort($helperIds);
    foreach ($helperIds as $id) {
      $definition = self::DEFINITIONS[$id] ?? NULL;
      $rows[] = [
        'helper' => ['data' => ['#plain_text' => $definition['label'] ?? $id]],
        'summary' => ['data' => ['#plain_text' => $definition['summary'] ?? 'Documentação pendente.']],
        'service' => ['data' => ['#plain_text' => $id]],
        'documentation' => ['data' => ['#plain_text' => $definition['docs'] ?? 'Pendente']],
      ];
    }
    return $rows;
  }

  /** Exposes curated definitions to the static gate and unit tests. */
  public static function definitions(): array {
    return self::DEFINITIONS;
  }

}
