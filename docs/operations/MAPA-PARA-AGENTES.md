# Mapa do projeto para agentes

Resumo de onde estão as coisas. Confirme sempre no código e no git antes de afirmar estado: planos e roadmaps podem estar atrasados.

## Ambientes e estados de release

| Ambiente | O que é | Regra |
| --- | --- | --- |
| Produção | `*.aculta.org` (Hostinger, Beta) | Só recebe deploy depois de Alpha validado e com tudo em RC |
| Servidor de desenvolvimento | `*.aculta.toca.net.br` (checkout `/home/piradopirata/projetos/aculta`) | Não é o Alpha. Banco `aculta_runtime`, testes Kernel em `aculta_simpletest` |
| Local | `127.0.0.1` | Simula o servidor de desenvolvimento; não é ele |

Estados de release: Dev → RC → Alpha (migração para `*.aculta.org`, depois do RC) → Beta (produção). Detalhes: `docs/operations/RELEASE-LIFECYCLE.md`.

## Onde estão as decisões

- Decisões do responsável, numeradas: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` (D-01 a D-43). São a fonte de verdade quando um plano conflita.
- ADRs: `docs/decisions/` (ex.: ADR-002 domínios e purposes; ADR-015 purpose `eventos`; ADR-030 mapa; ADR-031 calendário; ADR-032 carrinhos e checkouts).
- Arquitetura e rotas: `docs/architecture/` (ex.: `ROUTES-SITEMAP.md`, `environments.md`).

## Módulos do Portal por área

Todos ficam em `web/modules/custom/aculta_portal/modules/`. O quadro de status (versão e próxima fase de cada um) fica no `README.md` da raiz, gerado a partir de `docs/operations/status/`.

| Área | Módulos | Docs de entrada |
| --- | --- | --- |
| Conta e identidade | `aculta_user`, `aculta_id_cpf`, `aculta_mail_confirmer`, `aculta_mailer`, `aculta_messenger` | `AGENTS.md` do módulo (quando houver) |
| Loja | `aculta_commerce` (+ melhorenvio, messenger), `aculta_portal_cep` | `aculta_commerce/docs/ROADMAP.md` |
| Pagamentos | `aculta_pagamentos_mercadopago` (gateway Checkout Pro compartilhado) | `aculta_pagamentos_mercadopago/README.md` |
| Checkout e carrinho | Coordenação no `aculta_portal` (ADR-032, D-43) | `docs/decisions/ADR-032-portal-multi-cart-checkout.md` |
| Doações | `aculta_donation` | `aculta_donation/docs/ROADMAP-MULTI-CART.md` |
| LMS | `aculta_lms` (+ h5p, messenger, resources), `aculta_lms_agenda` | `aculta_lms/docs/ROADMAP.md` |
| Fórum e Wiki | `aculta_forum` (+ lms, wiki), `aculta_wiki` (+ messenger) | `AGENTS.md` do módulo |
| Eventos e calendário | `aculta_events`, `aculta_calendar`, `aculta_events_openstreetmap` (em andamento), `aculta_maps` (só docs) | `aculta_events/docs/ROADMAP.md`, `aculta_calendar/docs/ROADMAP.md` |
| Conteúdo e mídia | `aculta_magazine`, `aculta_gallery`, `aculta_image`, `aculta_document_viewer`, `aculta_pdf_viewer`, `aculta_editor`, `aculta_faq`, `aculta_content_admin` | README de cada módulo |
| Plataforma | `aculta_deployer`, `aculta_secrets_manager`, `aculta_patcher`, `aculta_index`, `aculta_statistics`, `aculta_portal_sitemap`, `aculta_links`, `aculta_qrcode`, `aculta_activity`, `aculta_game`, `aculta_studio` | README de cada módulo |
| Tema | `web/themes/custom/aculta420` | `web/themes/custom/aculta420/AGENTS.md` |

Observação: `aculta_ugc` existe só como documentação (`web/modules/custom/aculta_portal/modules/aculta_ugc/docs/README.md` e `.../docs/PLANO-IMPLANTACAO.md`), sem código. Não o trate como módulo instalado.

## Checkouts por contexto (D-43)

| Tipo de pedido | Contexto | Flow | Envio |
| --- | --- | --- | --- |
| `default` | Loja | `default` | Sim |
| `curso` | LMS | próprio do LMS (correção pendente no `config/sync`) | Não |
| `doacao` | Doações | `doacao` | Não |
| `assinatura` | Assinatura de doação | `assinatura_flow` | Não |
| `ingresso` (planejado) | Eventos | próprio | Não |

Carrinho da barra: um pedido por carrinho, com fluxo mais exigente entre as categorias presentes. Precedência real no código: tipos LMS, depois o tipo da Loja, depois `doacao` (`CartConsolidator::prioridade()`).

## Testes e navegador

- Kernel: banco `aculta_simpletest` (`SIMPLETEST_DB` em `secrets/simpletest-db.env`). Nunca `aculta_runtime`.
- Navegador: Firefox ESR em `DISPLAY=:0`, via geckodriver. Scripts visuais em `tests/browser/<frente>/` com `README.md`. Artefatos em `$SUITE_OUT`.
- Procedimento e histórico: `docs/operations/TESTING.md`, `docs/operations/NAVEGADOR-TESTES.md`, `tests/browser/README.md`.

## Quadro de status e documentos gerados

- `README.md` da raiz: tabela entre os marcadores `PROJECT-STATUS-INDEX`. Não edite à mão.
- Para regenerar: `php scripts/validate-project-status-index.php --write`. Para só validar: sem `--write`.
- Cada componente tem um arquivo em `docs/operations/status/<módulo>.yml`. A versão precisa coincidir com o `info.yml`.

## Documentos que não são instrução padrão

- `docs/operations/AGENT-GUARDRAILS-FULL.md`: histórico de auditoria. Não carregue por padrão.
- Relatórios em `docs/history/` e entradas antigas de CHANGELOG: referência, não estado atual.
