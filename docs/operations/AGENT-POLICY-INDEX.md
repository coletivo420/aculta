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
| Tema | `web/themes/custom/aculta420/AGENTS.md` |
| Runtime e Git | `docs/operations/AGENT-OPERATIONS.md` |
| Rotas e domínios | `docs/architecture/AGENT-ROUTES.md` |
| Conteúdo institucional e Wiki | `docs/operations/AGENT-CONTENT.md` |
| Cache e saídas | `docs/operations/AGENT-CACHE.md` e `docs/operations/AGENT-OUTPUT.md` |

## Duplicação e obsolescência

- Regras gerais de Git, segurança, Runtime e economia de tokens devem existir apenas na raiz; as locais acrescentam contratos especializados, sem repetir o texto.
- Textos históricos com datas, versões, URLs de ambiente, usuários de teste ou estado de PR são **referência**, não evidência de estado atual. Confirme com manifests, código instalado, status YAML, documentação de ambientes e PR ativa antes de executar.
- Quando uma regra histórica conflitar com código/contrato atual, não a aplique cegamente: identifique o conflito, confirme a decisão vigente e atualize a documentação dona em commit próprio.
- Regras de pagamentos/Commerce e outros módulos sem índice local validado permanecem no arquivo histórico; não foram descartadas nem automaticamente declaradas obsoletas.
- A organização por arquivos reduz carregamento indiscriminado; o consumo efetivo depende do CLI, que pode carregar instruções locais automaticamente.
