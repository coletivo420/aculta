# Índice de regras de agentes por escopo

O arquivo `docs/operations/AGENT-GUARDRAILS-FULL.md` é **arquivo histórico de auditoria**, não instrução de carregamento obrigatório. Não o leia integralmente por padrão.

| Escopo | Regras ativas/localizadas |
|---|---|
| Todos os agentes | `AGENTS.md`; Claude Code: `CLAUDE.md` |
| Portal | `web/modules/custom/aculta_portal/AGENTS.md` e `web/modules/custom/aculta_portal/docs/AGENT-POLICIES.md` |
| Fórum | `web/modules/custom/aculta_portal/modules/aculta_forum/AGENTS.md` e `docs/AGENT-POLICIES.md` local |
| Mailer | `web/modules/custom/aculta_portal/modules/aculta_mailer/AGENTS.md` |
| Studio | `web/modules/custom/aculta_portal/modules/aculta_studio/AGENTS.md` |
| Usuários | `web/modules/custom/aculta_portal/modules/aculta_user/AGENTS.md` |
| Avatar e identidade de autoria | `docs/architecture/avatar.md`; detalhe do módulo em `web/modules/custom/aculta_portal/modules/aculta_user/docs/AVATAR-PADRAO.md` |
| Tema / mobile-first global | `web/themes/custom/aculta420/AGENTS.md`; norma transversal em `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md`, obrigatória para todos os agentes que alterem UI no tema |
| Runtime e Git | `docs/operations/AGENT-OPERATIONS.md` |
| Rotas e domínios | `docs/architecture/AGENT-ROUTES.md` |
| Conteúdo institucional e Wiki | `docs/operations/AGENT-CONTENT.md` |
| Estatísticas | `web/modules/custom/aculta_portal/modules/aculta_statistics/AGENTS.md` |
| Galerias | `web/modules/custom/aculta_portal/modules/aculta_gallery/AGENTS.md` |
| Imagens/uploads | `web/modules/custom/aculta_portal/modules/aculta_image/AGENTS.md` |
| Eventos | `web/modules/custom/aculta_portal/modules/aculta_events/docs/README.md`; decisões do domínio em `docs/decisions/ADR-015-purpose-eventos.md` |
| Calendário | `web/modules/custom/aculta_portal/modules/aculta_calendar/docs/RECORRENCIA.md` |
| Agenda LMS | `web/modules/custom/aculta_portal/modules/aculta_lms_agenda/docs/INTEGRACAO.md` |
| UGC | `web/modules/custom/aculta_portal/modules/aculta_ugc/docs/README.md` |
| Visualização de documentos | `web/modules/custom/aculta_portal/modules/aculta_document_viewer/README.md` |
| Visualização de PDF | `web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md` |
| CPF/CNPJ | `web/modules/custom/aculta_portal/modules/aculta_id_cpf/AGENTS.md` |
| Messenger | `web/modules/custom/aculta_portal/modules/aculta_messenger/AGENTS.md` |
| Cache e saídas | `docs/operations/AGENT-CACHE.md` e `docs/operations/AGENT-OUTPUT.md` |
| Bibliotecas e dependências | `docs/operations/AGENT-DEPENDENCIAS.md`; tabela vigente em `README.md` (`## Bibliotecas usadas`) |
| Endereços e CEP centralizados | `docs/portal/ACCOUNT-ADDRESS.md`; `docs/decisions/ADR-034-pais-unico-brasil-endereco-central.md`; `web/modules/custom/aculta_portal/modules/aculta_portal_cep/README.md` |
| Mapa do projeto | `docs/operations/MAPA-PARA-AGENTES.md` (leia antes de uma tarefa nova) |
| Decisões do responsável | `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` (D-01 a D-43); prevalecem sobre planos |
| Checkouts, carrinho e pagamentos | `docs/decisions/ADR-032-portal-multi-cart-checkout.md`; `aculta_pagamentos_mercadopago/README.md`; `aculta_commerce/docs/PAYMENT-FLOW-CONTRACTS-0.1.0-B.md` |
| Ingressos pagos (planejamento) | `web/modules/custom/aculta_portal/modules/aculta_events/docs/PLANO-INGRESSOS-PAGOS.md`; sem código ainda |

## Duplicação e obsolescência

- Regras gerais de Git, segurança, Runtime e economia de tokens devem existir apenas na raiz; as locais acrescentam contratos especializados, sem repetir o texto.
- Textos históricos com datas, versões, URLs de ambiente, usuários de teste ou estado de PR são **referência**, não evidência de estado atual. Confirme com manifests, código instalado, status YAML, documentação de ambientes e PR ativa antes de executar.
- Quando uma regra histórica conflitar com código/contrato atual, não a aplique cegamente: identifique o conflito, confirme a decisão vigente e atualize a documentação dona em commit próprio.
- Regras de pagamentos/Commerce e outros módulos sem índice local validado permanecem no arquivo histórico; não foram descartadas nem automaticamente declaradas obsoletas.
- A organização por arquivos reduz carregamento indiscriminado; o consumo efetivo depende do CLI, que pode carregar instruções locais automaticamente.
