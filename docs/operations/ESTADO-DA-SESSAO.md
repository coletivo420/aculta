# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# Shell — rodapé e regions — 2026-11-05

- **Objetivo:** cobrir a pendência de shell de conferir o rodapé e os regions restantes sem alterar a Institution Bar nem o contrato do Domain Header.
- **Concluído:** `1fae08f720` cria `tests/browser/aculta420-v2-footer/`. Captura 6/6 em 320, 390 e 1440 px, claro/escuro: Institution Bar e Domain Header presentes, rodapé institucional com conteúdo e sem overflow horizontal. Os regions `highlighted`, `help`, `breadcrumb` e `sidebar_first` continuam condicionais no template.
- **Verificações:** script no Firefox ESR visível PASS 6/6; `validate-aculta420-shell-contract.php` PASS (13 checks).
- **Riscos:** a decisão de navegador registrada no handoff é Playwright, que aposenta o geckodriver. As suítes `aculta-htmx-f1` e `aculta420-v2-footer` usam geckodriver nesta sessão por instrução do prompt e precisam de migração para Playwright.
- **Pendências:** aceitação visual final do rodapé pelo responsável; migração das duas suítes para Playwright.
- **Próximo passo:** migrar as suítes para Playwright ou seguir F2 depois das decisões de F1.

# Skills de handoff, padrões Drupal 12, segurança e Playwright (decisão) — 2026-11-05

- **Objetivo:** melhorar o handoff, adicionar skills de padrão Drupal e de segurança estática, adicionar a skill de alinhamento e renomear o padrão normativo para Drupal 12.
- **Concluído:** `2249d7a5` (handoff com passo de retomada, `aculta-drupal-padroes`, `aculta-seguranca-estatica`, limite de 9 skills), `a55dbe1d` (`aculta-grill-me`, `/graphify-out/` no `.gitignore`, limite de 10 skills), `576760cc` (renomeia `DRUPAL-11-STANDARDS.md` para `DRUPAL-12-STANDARDS.md`). Gate do Drupal renomeado para `validate-aculta-portal-drupal12.php` nesta entrega (commit a seguir).
- **Decisões:** Trail of Bits não copiada (licença CC-BY-SA; instalação do plugin fica com o responsável). Skill Drupal do registro não copiada (licença não declarada; conteúdo de Drupal 10 com DDEV); escrita versão própria. Navegador de teste: **Playwright como padrão único** (opção B), que aposenta o geckodriver e o Firefox ESR; o aceite visual passa a ser captura do Playwright. Esta decisão ainda não está em `NAVEGADOR-TESTES.md`.
- **Verificações:** `validate-agent-token-budget.php` PASS (skills 10). `validate-aculta-portal-drupal12.php` DEFERRED: falta `vendor/`. Gate de caminhos PASS. Graphify: grafo gerado sem LLM (127.738 nós, 202.904 arestas), consulta funcionando; semgrep instalado, varredura de linha de base não feita.
- **Riscos:** o padrão de navegador em `NAVEGADOR-TESTES.md` e na skill `aculta-navegador-testes` ainda diz Firefox ESR até a migração. Commit de V1 acidental foi revertido antes do push.
- **Pendências:** migração para Playwright (prompt já entregue ao agente local); varredura do Semgrep; decidir o plugin da Trail of Bits; rodar o gate do Drupal 12 onde houver `vendor/`.
- **Próximo passo:** o agente local migra os testes para Playwright e atualiza `NAVEGADOR-TESTES.md` e a skill, e depois roda a varredura do Semgrep sobre os módulos alterados.

# HTMX F1 — busca do shell e cadastro popup — 2026-11-05

- **Objetivo:** concluir as pendências apontadas da microfase HTMX F1: migrar a busca do shell e o cadastro popup para HTMX, validar com sessão/Form API reais e executar a matriz visual.
- **Concluído:** `1efae40765` (Portal) migra `SearchPopupForm`, `RegisterPopupForm`, `SearchUtility` e `search-preview.js`; `31a061ef75` corrige a pendência documental dos filtros. No tema, `multidomain-bar.js` abre a busca por `hx-get`, sem Fetch nem `DOMParser`; `multidomain-bar.twig` ganhou `id="aculta-search-content"`, que faltava e impedia o alvo HTMX. Nova suíte `tests/browser/aculta-htmx-f1/`. Validação real encontrou e este commit corrige `data-hx-post=""` no login e no cadastro, que enviava o POST à página atual em vez da rota do popup; agora a URL é explícita.
- **Verificações:** no Runtime de desenvolvimento, login real da conta de teste autenticou e o `HX-Redirect` navegou; POST inválido do cadastro devolveu o fragmento com erro e não criou conta; busca retornou `data-hx-*` com `HX-Request` e manteve o script legado sem o cabeçalho. Suíte Firefox ESR visível: 43/43 checks (busca, login e gaveta de avisos autenticada em 320/390/768/959/960/1440 px, claro/escuro, prévia HTMX, foco visível, zoom real 200%, Escape com retorno de foco e fallback sem JavaScript). `validate-browser-validators` (21), `validate-aculta420-sdc-schemas` (55/764) e shell contract (13) PASS; PHP lint e `git diff --check` PASS.
- **Gates com falha preexistente:** `validate-aculta-portal-drupal11` e `validate-project-status-index` (versões divergentes de nove módulos), `validate-portal-popup-rule` (9 ocorrências em Events/Paths) e `validate-aculta420-v2-sdc` (`src/styles/buttons.css`). Nenhum arquivo dessas falhas foi alterado nesta entrega.
- **Decisões de 2026-11-05 (responsável):** (1) gavetas de mensagens/avisos mantêm a exceção documentada — dependem de JavaScript e não têm link nativo para a página completa, porque o Portal não fornece `all_href`; (2) o gatilho do cadastro no checkout de doação foi autorizado e migrado para o diálogo HTMX do shell, com `href` como fallback. Ambas registradas no ADR-050, no AGENTS/architecture do tema e no CHANGELOG de `aculta_donation`.
- **Pendências:** captura do diálogo de cadastro em pedido de doação real; restante do checkout/pagamento na F5. F1 está coberta; F2 pode ser retomada.
- **Próximo passo:** retomar F2 ou a F3 (o Fórum usa diálogos do CKEditor e precisa de decisão separada).
