# ACULTA Portal (`aculta_portal`)

Módulo de integração do site institucional ACULTA. Conecta capacidades do Drupal
Core e de módulos contrib para as experiências de conta, conteúdo, Commerce,
aprendizagem e domínios. Core/contrib continuam donos das entidades e regras que
já fornecem.

## Evolução planejada: Conta e Perfil no ACULTA Studio (2026-10-24)

A proposta STUDIO-P0–P7 reorganiza **navegação e apresentação** de Minha Conta
e Meu Perfil sob um hub geral `/studio` e experiência `Meu Espaço`. O
Portal conserva Domain/Purpose, formulários, confirmação de e-mail, segurança,
endereços e os contratos compartilhados de checkout. User/Profile e os
demais owners não mudam de fonte de verdade.

**Estado:** somente alinhamento documental; a home `/` de `account`, o
`AccountShellBuilder`, os formulários e caminhos antigos continuam ativos;
`/minha-conta` segue técnico e bloqueado. O Studio não deverá aninhar seu
shell dentro do shell Conta; o corte fica para fases testadas e autorizadas.
O perfil público `/u/{apelido}` permanece em `main`.

[Contrato e roadmap canônico](../../../../docs/architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md)
e [navegação atual da conta](../../../../docs/portal/ACCOUNT-MENU.md).

## Busca com prévia

`aculta_portal/search-preview` atualiza uma região viva nomeada com links de
navegação. O campo permanece um searchbox nativo; Tab percorre os resultados e
as setas continuam disponíveis para edição do texto. Não declarar esses links
como opções de combobox.

## Fronteira

```text
Core + contrib → aculta_portal → contrato neutro de apresentação → ACULTA420
```

> **Código no Aculta Portal, Design no ACULTA420.**

O Portal cuida de integração funcional, rotas, dados, acesso, cache e presenters.
O tema cuida de templates, componentes e estilo. O Portal não deve criar storage
paralelo nem depender do tema para funcionar.

## Capacidades e extensões ativas

A política reutilizável de upload de usuários pertence ao Portal e é documentada
em [`docs/portal/USER-UPLOADS.md`](../../../../docs/portal/USER-UPLOADS.md).
Consumidores reutilizam `aculta_portal.user_upload_policy` e
`aculta_portal.user_upload_dialog_builder`; a política global/per-domain,
constraints Managed File e modal comum ficam no Portal, enquanto publicação,
acesso, retenção e apresentação continuam vinculados às entidades de cada
domínio. O contrato e a matriz de regressão com Fórum, `aculta_image` e
`aculta_gallery` estão em [`USER-UPLOADS.md`](../../../../docs/portal/USER-UPLOADS.md).

As extensões próprias incluem o ACULTA Forum com histórico de leitura por conta,
contexto, descoberta e autoria pública das atividades por meio dos cartões do
`aculta_user`; o LMS com atividades H5P vinculadas a cursos,
slugs públicos canônicos por `PublicRouteSlug::normalize()` e a política
`filename_sanitization` para nomes de upload. O `aculta_user` também fornece a
API base cacheável de identidade de autoria para consumidores do Portal. Cada
capacidade documenta seus limites no README do respectivo submódulo.

O relatório administrativo **ACULTA Portal > Status** varre os requisitos de
extensões PHP (`ext-*`) de todos os pacotes Composer de runtime, incluindo
dependências transitivas, e indica quais estão carregadas na SAPI PHP que
atende a página. Mapas explícitos atribuem os pacotes de `aculta_image` e
`aculta_pdf_viewer`; o gate bloqueia regressões de cobertura. Consulte o
[contrato do inventário de extensões PHP](../../../../docs/portal/PHP-EXTENSION-STATUS.md).

## Navegação documental

- [Índice normativo do Portal](../../../../docs/portal/README.md) — contratos,
  políticas e padrões de implementação.
- [Padrão Drupal 11+](../../../../docs/portal/DRUPAL-11-STANDARDS.md) — APIs,
  DI, hooks, EntityQuery, render e cache.
- [Guardrails](../../../../docs/portal/GUARDRAILS.md) — regras funcionais por área.
- [Fonte de verdade](../../../../docs/portal/SOURCE-OF-TRUTH.md) — ownership de
  dados e capacidades.
- [Fronteira módulo–tema](../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md) e
  [mapa módulo–tema](../../../../docs/portal/MAPA-MODULO-TEMA.md).
- [Roadmap](../../../../docs/portal/ROADMAP.md) e
  [registro de dívidas](../../../../docs/operations/DEBT-REGISTER.md).
- [CHANGELOG](CHANGELOG.md) — entregas implementadas e evidências por versão.

## Submódulos

Cada extensão tem seu próprio README como referência local. O índice raiz mantém
a lista navegável de todos os componentes: [Submódulos do Portal](../../../../README.md#submódulos-do-portal).

O submódulo `aculta_lms_h5p` conecta conteúdo interativo H5P às turmas do LMS,
com verificação de acesso do Group para reprodução, arquivos privados e
progresso. Sua condição contextual CTools identifica cursos que contêm essas
atividades; veja o [README do H5P](modules/aculta_lms_h5p/README.md) para limites
do editor e habilitação.

## Desenvolvimento

Antes de alterar o Portal, consulte as normas acima, verifique a contraparte do
tema e confirme a versão do Core instalada em `web/core`. Use dependency injection
em classes, APIs públicas do Drupal e declarações conscientes de access/cache.

Gate arquitetural obrigatório para mudanças do Portal:

```sh
php scripts/validate-aculta-portal-drupal11.php
```

Gates específicos de fronteira, mapa, segurança ou rotas estão referenciados nos
documentos normativos correspondentes. Resultados de execução e Runtime devem
ser registrados no changelog da entrega, não copiados para este README.

## Outras entradas

- [Documentação transversal de módulos](../../../../docs/modules/README.md)
- [Integrações externas](../../../../docs/integrations/README.md)
- [ACULTA420](../../../themes/custom/aculta420/README.md)


## Política de arquitetura transversal ACULTA — código compartilhado

[Contratos de moderação e centralização](../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md): serviços e código realmente reutilizáveis entre submódulos ACULTA devem migrar para o subkernel `aculta_user` após auditoria/ADR/testes, mantendo adapters específicos nos respectivos subkernels. Exemplo inicial: princípio da primeira participação do Fórum e primeira publicação da Wiki, com fontes de dados e workflows distintos. Não centralizar regras de negócio exclusivas ou renderização do tema.

## Retificação de ownership (2026-10-22)

**A política compartilhada de moderação e primeira participação reside em `aculta_user`**, estendendo `ParticipationRules`, sem migração para `aculta_portal`. Wiki e Fórum permanecem donos de filas/workflows e entidades próprias. Esta decisão substitui indicações anteriores de centralizar essa política no Portal. [Contrato revisado](../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md).

## Auditoria transversal XK 0.1 — 2026-10-22

Centralização é por ownership: User (participação/associados), Messenger (avisos/mensagens), Image/Gallery (mídia), viewers (documentos), Statistics (métricas), Portal (domain/purpose). Group restrito a associado deve exigir situação ativa User + membership Group no Fórum. Auditoria transversal: docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

## Decisão normativa: ACULTA Studio (2026-10-22)

aculta_studio already is a submodule of aculta_portal and is the shared UI/experience platform for GanjaLab, GanjaLab420, WE SATIVA and future own-account experiences. Portal remains owner of Domain/purpose, not Studio's experience presentation or Wiki's workflow.

[Contrato e roadmap transversal](../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md). Esta decisão prevalece sobre terminologia anterior que equiparava o submódulo exclusivamente a GanjaLab. **Não declarar consumidores planejados como rotas implementadas.**

## Carrinhos e checkout — decisão arquitetural ADR-013

`aculta_portal` assumirá a coordenação transversal de contextos de carrinho e da seleção de checkout **por pedido/carrinho**, com abas **Comum | Loja | LMS | Doações**. A aba Comum é agregadora de carrinhos autorizados, e não fusão automática de pedidos. `aculta_commerce`, `aculta_lms` e `aculta_donation` fornecem adaptadores; Drupal Commerce mantém dados/transações e `aculta_pagamentos_mercadopago` é o gateway único compartilhado. Preservar `donate|cart|both`, URLs, o shell e o cabeçalho multidomínio; apresentação no ACULTA420 com SDC/UI Suite e progressive enhancement.

[Decisão e fases](../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md). **Status: planejado; nenhum seletor novo habilitado.**

## RETIFICAÇÃO ADR-013 — Interface administrativa, carrinho público preservado

`aculta_portal` será responsável pela coordenação **administrativa** de políticas de carrinho e escolha dos checkouts. As abas **Comum | Loja | LMS | Doações** destinam-se exclusivamente à configuração interna. **O carrinho atual da barra multidomínio, incluindo ícone, posição, contadores, links e seleção existente do checkout, não será modificado.** Não criar carrinho público paralelo, checkout combinado ou nova seleção por usuário/sessão. Drupal Commerce continua fonte da verdade; Mercado Pago gateway compartilhado. Ver [ADR-013](../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md). Este texto prevalece sobre menções antigas conflitantes.

**Administração (P-CART E):** ACULTA > Carrinhos e checkouts, em `/painel-administrativo/aculta/checkout`, com as abas Comum, Loja, LMS e Doações. Permissões: `administer aculta checkout common|shop|lms` (Doações usa a permissão do próprio formulário). Config: `aculta_portal.checkout_settings` (`common_checkout_enabled`, `shop_order_type`, `lms_order_type`; padrões equivalentes ao comportamento anterior). Serviços: `aculta_portal.checkout_*` (registry por tag `aculta_portal.checkout_provider`, resolver, política e validador). A regra de estorno (doações, assinaturas e cursos sem estorno) fica em `MercadoPagoPaymentPolicy::bloqueiaEstorno()`. **Rollback:** reverter os commits da P-CART D/E; sem a config, valem os padrões.
