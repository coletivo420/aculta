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

## 4. Propostas que exigem decisão do responsável

1. **Mover históricos para fora do caminho de leitura.** Mover `ACULTA-CONTINUOUS-EXECUTION.md`
   para `docs/history/operations/` e atualizar as 5 referências. Não apagar conteúdo.
2. **Separar o handoff.** `ESTADO-DA-SESSAO.md` com estado atual curto (até ~150 linhas) e o
   histórico movido para `docs/history/operations/`. O AGENTS continua exigindo o handoff.
3. **Reduzir a matriz de captura.** O AGENTS do tema exige captura em 320, 390 e 1440 px, em
   claro e escuro, com zoom de 200%, para cada mudança visual. Proposta: captura completa
   só na aceitação da fase; mudanças pequenas com 320 e 1440 px no modo claro.
4. **Duplicidades de suítes.** `tests/browser/aculta420-v2-foundations`,
   `aculta-theme-mobile-first`, `aculta-visual` e `aculta-smoke` fazem medições sobrepostas
   de reflow. Proposta: uma suíte de reflow (`aculta-theme-mobile-first`) e as outras
   restritas a contrato específico.
5. **Duplicidade de módulo.** `aculta_links` e `aculta_linkweed` têm o mesmo
   `SocialLinksSettingsForm`. Decidir qual permanece (ver ADR-050, seção 7).
6. **Gates sem referência.** `scripts/validate-cross-domain-request-policy.php` e
   `scripts/validate-session-cookie.php` não são citados em nenhum documento. Confirmar se
   são necessários; se não forem, remover em PR própria.
7. **Testes unitários por escopo.** `aculta_messenger` tem 82 testes unitários e
   `aculta_lms` 65. Recomendação: rodar só o módulo alterado, não a suíte inteira, em cada
   PR. A regra já existe em `docs/portal/AGENT-TOKEN-ECONOMY.md`, mas não é referenciada por
   `AGENTS.md` nem `CLAUDE.md`. Referenciar.
8. **Teste duplicado de design-foundations.** `scripts/tests/validate-aculta420-design-foundations-test.php`
   (1.235 linhas) acompanha o analisador de 2.282 linhas. Decidir se o teste roda em CI ou
   só no gate.

## 5. Regras que não mudam

- Gates obrigatórios por mudança (`docs/gates.md` do tema e `validate-admin-routes-main-domain.php`).
- Testes de acesso no servidor, de pagamento e de segredo.
- Não economizar testes de segurança, evidência ou honestidade sobre resultado
  (`docs/portal/AGENT-TOKEN-ECONOMY.md`, item 8).
