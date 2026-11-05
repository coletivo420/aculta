# Auditoria de tokens e testes — 2026-11-05

Objetivo: reduzir o consumo de tokens dos agentes sem reduzir segurança, gates
obrigatórios nem evidência de testes. Medições feitas com `wc`, `du`, `grep` e
`git ls-files` no repositório, em `dev` (commit `c940cab4`).

## 1. Medições

| Item | Tamanho | Por que pesa |
| --- | --- | --- |
| `docs/operations/ACULTA-CONTINUOUS-EXECUTION.md` | 506.904 bytes, 3.196 linhas | log histórico de execução; 5 arquivos o referenciam |
| `docs/operations/ESTADO-DA-SESSAO.md` | 197.363 bytes, 1.112 linhas | handoff obrigatório ao fim de sessão (AGENTS, seção 1) |
| `docs/operations/AGENT-GUARDRAILS-FULL.md` | 58.694 bytes | arquivo histórico; já marcado para não carregar |
| `docs/portal/TESTES-NAVEGADOR.md` | 57.687 bytes, 645 linhas | histórico de rodadas, não regra |
| `docs/operations/ACULTA-AGENT2-WORKLOG.md` | 45.786 bytes | histórico de agente |
| `tests/browser/README.md` | 15.398 bytes, 254 linhas | índice de scripts; lido sempre que há teste visual |
| `docs/history/changelog/*.md` | até 3.976 linhas (`aculta_forum.md`) | histórico; não é entrada de agente |
| `scripts/` | 126 arquivos, 64 `validate-*` | gates; muitos com 0 ou 1 referência em docs |
| `tests/browser/` | 40 suítes; 193 arquivos mencionam captura, Firefox ou screenshot | cada suíte carrega driver e documentação |
| `references/` | 4,6 MB, 7 arquivos rastreados | só consultar por `rg` |
| `data/` | 93 MB, 873 arquivos rastreados | dados de importação; não ler |

Olivero: 107 arquivos citam o tema, entre código, documentação e scripts.
O tema não está em `config/sync/core.extension.yml`; `scripts/validate-final-drupal.php`
afirma que ele foi desinstalado. Testes que exigem Olivero não rodam no estado exportado
e forçam troca de tema global no Runtime.

## 2. Ações de Olivero já presentes no dev

Ao preparar esta auditoria, o `dev` já tinha a limpeza de Olivero feita por outra sessão
(commit `37399695` e anteriores). Verificado em 2026-11-05:

1. **Regras:** `CLAUDE.md`, `AGENTS.md` e `web/themes/custom/aculta420_v2/AGENTS.md` proíbem
   habilitar, selecionar, testar ou exigir Olivero em gates, suítes ou aceite.
2. **Scripts exclusivos de Olivero removidos:** `aculta-cpf-checkout/olivero-smoke.py` e
   `aculta-portal-cep/olivero.py`. O README do CEP foi reduzido ao contrato atual.
3. **Loops de dupla execução:** `office-options.py` e `pdf-viewer-rc.py` não rodam mais
   Olivero. O `categorias-visual.py` não tem mais a variável de tema.
4. **Índice:** `tests/browser/README.md` não cita mais scripts removidos.

`grep -ri olivero tests/browser` retorna vazio no `dev` (2026-11-05). Nenhum teste de
navegador foi executado nesta auditoria (sem `DISPLAY` nem Runtime): **DEFERRED**.

## 3. Pendências de Olivero

Ainda há referências fora de `tests/browser`:

- `scripts/finalize-home-carousel.php` e `scripts/sync-reviewed-final-config.php`:
  histórico de remoção; decidir se arquivam.
  `scripts/validate-final-drupal.php` **fica**, porque confirma a desinstalação.
- Documentos de histórico (`docs/portal/TESTES-NAVEGADOR.md`, `docs/history/changelog/*`,
  `docs/operations/status/*.yml`, `docs/operations/*`). Manter como histórico.
- `web/themes/custom/aculta420/` (V1) e outros módulos podem citar Olivero em comentários;
  não tocar na V1.

## 4. Decisões do responsável e estado

Decisões de 2026-11-05 (respostas às perguntas):

1. **Remover `aculta_links`:** **pendente de verificação no Runtime.** O README do módulo diz
   "Runtime MAIN ativo", mas o `config/sync/core.extension.yml` não o inclui e o
   `aculta_linkweed` é o nome novo. Não remover antes de confirmar com
   `drush pm:list --status=enabled` no Runtime. Se estiver ativo lá, a remoção precisa de
   desinstalação e de configuração própria.
2. **Captura:** a matriz completa fica só na aceitação da fase. Mudança visual pequena dentro
   de uma fase: 320 e 1440 CSS px, claro. **Aplicado** em `web/themes/custom/aculta420_v2/AGENTS.md`.
3. **Suítes de reflow:** decidido consolidar em `aculta-theme-mobile-first`, migrando as medições.
   **Não há script idêntico** entre as quatro (verificado por `md5sum`). Plano de migração, ainda
   **não executado**, porque cada medição precisa rodar no Firefox ESR do Homelab antes de a
   suíte antiga ser removida:

   | Origem | Medição | Destino proposto |
   | --- | --- | --- |
   | `aculta420-v2-foundations/visual.py` | foco por teclado, zoom, `no-js`, `reduced-motion`, modo claro e escuro por purpose | `aculta-theme-mobile-first/` (novo `foundations-matrix.py`) |
   | `aculta-smoke/page-matrix.py` e `smoke-page.py` | matriz de páginas e HTTP | `aculta-theme-mobile-first/` (novo `page-matrix.py`) |
   | `aculta-visual/visual.py` | álbum, história e marcador da Revista (login por link único) | manter como suíte própria da Revista; **não** mesclar, porque depende de dados de Revista |

   Remoção só depois de a suíte de destino passar no Homelab (DEFERRED). A suíte da Revista não
   é duplicada: mede outro contrato.
4. **Gates sem referência:**
   - `validate-session-cookie.php` **removido.** Falhava sem `vendor/` e duplicava
     `validate-session-cookie-policy.php`, que passa e é citado em
     `aculta_deployer/docs/COOKIES-AMBIENTE.md`.
   - `validate-cross-domain-request-policy.php` **mantido.** Correção desta auditoria: eu o
     classifiquei como sem referência por busca do nome exato com `.php`. Ele é citado em
     `docs/portal/FRONTEIRA-MODULO-TEMA.md` e implementa a política de
     `docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md`, que a migração htmx (ADR-050) depende. Ele só
     roda dentro do Drupal (`DRUPAL_ROOT`), por isso não executa aqui.

## 5. Regras que não mudam

- Gates obrigatórios por mudança (`docs/gates.md` do tema e `validate-admin-routes-main-domain.php`).
- Testes de acesso no servidor, de pagamento e de segredo.
- Não economizar testes de segurança, evidência ou honestidade sobre resultado
  (`docs/portal/AGENT-TOKEN-ECONOMY.md`, item 8).
