<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Status;

/** Curated metadata for active runtime integrations applied by the Portal. */
final class PortalImplementationRegistry {

  /** @var array<string, array{label: string, summary: string, docs: string}> */
  private const DEFINITIONS = [
    'aculta_portal.access.protected_route' => ['label' => 'Proteção de rotas', 'summary' => 'Aplica requisitos de confirmação de e-mail e termos a rotas protegidas.', 'docs' => 'docs/portal/GUARDRAILS.md'],
    'aculta_portal.access.email_confirmed' => ['label' => 'Acesso com e-mail confirmado', 'summary' => 'Impede operações protegidas enquanto a conta não confirma o e-mail.', 'docs' => 'docs/portal/SOURCE-OF-TRUTH.md'],
    'aculta_portal.email_confirmation_subscriber' => ['label' => 'Fluxo de confirmação de e-mail', 'summary' => 'Integra mudanças de estado do usuário ao fluxo de confirmação.', 'docs' => 'docs/portal/SOURCE-OF-TRUTH.md'],
    'aculta_portal.purpose_front_path_processor' => ['label' => 'Página inicial por purpose', 'summary' => 'Resolve o caminho inicial conforme o Domain purpose ativo.', 'docs' => 'docs/portal/DOMAIN-PRESENTATION-CONTRACT.md'],
    'aculta_portal.account_ajax_main_content_renderer' => ['label' => 'Conteúdo principal AJAX da conta', 'summary' => 'Adapta a resposta AJAX da navegação da conta para preservar conteúdo principal e contexto.', 'docs' => 'docs/portal/ACCOUNT-MENU.md'],
    'aculta_portal.checkout_friendly_path_processor' => ['label' => 'Caminhos amigáveis do checkout', 'summary' => 'Converte caminhos técnicos do checkout em rotas públicas em português.', 'docs' => 'docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md'],
    'aculta_portal.account_list_path_processor' => ['label' => 'Caminhos amigáveis da conta', 'summary' => 'Apresenta caminhos de listagem da conta conforme o contrato público.', 'docs' => 'docs/portal/ACCOUNT-MENU.md'],
    'aculta_portal.breadcrumb_builder' => ['label' => 'Breadcrumb por purpose', 'summary' => 'Constrói trilhas de navegação usando o propósito do domínio corrente.', 'docs' => 'docs/portal/DOMAIN-PRESENTATION-CONTRACT.md'],
    'aculta_portal.domain_route_subscriber' => ['label' => 'Política de rotas por domínio', 'summary' => 'Aplica restrições e decisões de rota no limite entre purposes.', 'docs' => 'docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md'],
    'aculta_portal.domain_purpose_subscriber' => ['label' => 'Proteção de purpose', 'summary' => 'Aplica acesso, canonicalização e destino conforme Domain purpose.', 'docs' => 'docs/portal/DOMAIN-PRESENTATION-CONTRACT.md'],
    'aculta_portal.mercado_pago_environment_override' => ['label' => 'Configuração Mercado Pago por ambiente', 'summary' => 'Seleciona configuração e credenciais de pagamento segundo o ambiente declarado.', 'docs' => 'docs/portal/PAYMENT-DOMAIN-POLICY.md'],
    'aculta_portal.mercado_pago_webhook_subscriber' => ['label' => 'Validação de webhook Mercado Pago', 'summary' => 'Integra a verificação de notificações recebidas ao fluxo de pagamento.', 'docs' => 'docs/portal/PAYMENT-DOMAIN-POLICY.md'],
    'aculta_portal.account_route_subscriber' => ['label' => 'Rotas da Minha Conta', 'summary' => 'Aplica regras de purpose e acesso às rotas da conta.', 'docs' => 'docs/portal/ACCOUNT-MENU.md'],
    'aculta_portal_cep.rate_limit_subscriber' => ['label' => 'Limite de consultas de CEP', 'summary' => 'Aplica rate limit às consultas do serviço de CEP.', 'docs' => 'web/modules/custom/aculta_portal/modules/aculta_portal_cep/README.md'],
    'aculta_portal.lms_friendly_slug_converter' => ['label' => 'Conversão de slugs LMS', 'summary' => 'Resolve parâmetros públicos em português para entidades de cursos.', 'docs' => 'docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md'],
    'aculta_portal.lms_friendly_route_processor' => ['label' => 'Rotas públicas LMS', 'summary' => 'Gera URLs amigáveis para cursos, lições e atividades.', 'docs' => 'docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md'],
    'aculta_lms.course_order_enrollment' => ['label' => 'Matrícula de curso Commerce', 'summary' => 'Concede e revoga memberships de turma conforme o estado dos pedidos de curso e preserva matrículas manuais.', 'docs' => 'docs/modules/LMS-GROUP.md'],
    'aculta_lms.attempt_archive' => ['label' => 'Histórico de tentativas LMS', 'summary' => 'Preserva respostas descartadas pelo reinício da aula e as exibe somente a quem mantém acesso atual ao curso.', 'docs' => 'web/modules/custom/aculta_portal/modules/aculta_lms/README.md'],
    'aculta_portal.donation_friendly_route_subscriber' => ['label' => 'Rota pública de apoio', 'summary' => 'Adapta a rota de apoio a um caminho público em português.', 'docs' => 'docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md'],
    'aculta_portal.cart_friendly_route_subscriber' => ['label' => 'Rota pública do carrinho', 'summary' => 'Adapta o destino do carrinho ao contrato público e ao purpose MAIN.', 'docs' => 'docs/portal/PAYMENT-DOMAIN-POLICY.md'],
    'aculta_portal.lms_friendly_route_subscriber' => ['label' => 'Rotas LMS amigáveis', 'summary' => 'Aplica aliases públicos em português às rotas LMS previstas.', 'docs' => 'docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md'],
    'aculta_portal.social_auth_user_created_subscriber' => ['label' => 'Criação de conta Social Auth', 'summary' => 'Aplica políticas do Portal a contas criadas por autenticação social.', 'docs' => 'docs/portal/GUARDRAILS.md'],
    'aculta_portal.social_auth_profile_subscriber' => ['label' => 'Perfil Social Auth', 'summary' => 'Integra dados de perfil do provedor ao perfil Drupal conforme política local.', 'docs' => 'docs/portal/GUARDRAILS.md'],
    'aculta_portal.uploaded_filename_extension_subscriber' => ['label' => 'Normalização da extensão de uploads', 'summary' => 'Padroniza a extensão em minúsculas após a sanitização nativa do Drupal.', 'docs' => 'web/modules/custom/aculta_portal/README.md'],
  ];

  /** @param string[] $activeImplementationIds Active IDs supplied by Drupal's service container. */
  public function __construct(private readonly array $activeImplementationIds) {}

  /** @return array<int, array<string, array<string, mixed>>> Render API table rows. */
  public function rows(): array {
    $ids = $this->activeImplementationIds;
    sort($ids);
    $rows = [];
    foreach ($ids as $id) {
      $definition = self::DEFINITIONS[$id] ?? NULL;
      $rows[] = [
        'implementation' => ['data' => ['#plain_text' => $definition['label'] ?? $id]],
        'effect' => ['data' => ['#plain_text' => $definition['summary'] ?? 'Documentação pendente.']],
        'service' => ['data' => ['#plain_text' => $id]],
        'documentation' => ['data' => ['#plain_text' => $definition['docs'] ?? 'Pendente']],
      ];
    }
    return $rows;
  }

  /** Exposes curated definitions to validation gates and unit tests. */
  public static function definitions(): array {
    return self::DEFINITIONS;
  }

}
