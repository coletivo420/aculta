# Auditoria e correções de consistência CSS / layouts - 2026-11-11

## Escopo e snapshot

Tema `web/themes/custom/aculta420_v2`, branch `dev`, snapshot de origem
`eaa0ad6c8be236dca8b0b5290eab32b25753cacc`.
Inspeção estática via GitHub: CSS global e contextual selecionado, SDCs de
layout e superfície, shell, configuração de blocos e documentos da ADR-054/055.
Sem acesso ao Drupal/Runtime nesta execução: nenhum gate foi declarado PASS.

## Decisões preservadas

- Modo claro/escuro exclusivamente por `data-theme`: canvas claro
  `#f2f7f0`, escuro `#171513`. UI Skins administra somente acentos
  selecionados por `data-skin`, sem voltar ao motor visual por purpose.
- Cinco regiões finais de tema:
  `institution`, `header`, `content`, `sidebar_first`, `footer`.
- Shell `v2` no Config Sync, `layout-page` SDC com variantes
  `stack`, `aside` e `full`. Conteúdo precede `aside` no DOM.
- Manter Layout Builder, UI Patterns, UI Styles e a separação funcional
  `data-site-key` vs. visual `data-skin`; não reinstalar Bootstrap.

## Correções aplicadas por esta rodada

1. **Contraste do painel institucional.** O cabeçalho de
   `surface-panel` usava texto branco sobre `#689427` em modo claro:
   contraste WCAG aproximado de **3,59:1**. A variável
   `--surface-panel-institutional-text` agora referencia
   `--color-footer-text` (`#171513` no claro; **5,08:1**).
   No escuro, permanece o override com texto claro em `#2e4d22`
   (**8,34:1**). Adicionado teste de contraste para este par ao gate
   `scripts/validate-aculta420-v2-foundations.php`.
2. **Aside desktop.** `layout-page--aside` posiciona a barra lateral
   visualmente à esquerda com CSS Grid a partir de 60rem, sem alterar
   ordem de leitura: conteúdo antes da área complementar. Quando não há
   aside, o layout continua com uma coluna.
3. **Fallback do shell.** Tanto o Twig clássico quanto o v2 atribuem
   `page__main--stack` ou `page__main--with-sidebar` conforme a
   variante calculada pelo preprocess. O fallback de duas colunas agora
   usa a mesma geometria de aside à esquerda.
4. **Regiões do shell.** Eliminada área vazia `navigation` do grid
   `page--shell-v2`. Comentários não prometem mais regiões legadas
   inexistentes em `info.yml`. Help e breadcrumb vêm dos blocos
   extraídos do header.
5. **Alias de superfícies.** `--aculta-surface-raised` acompanha
   `--color-surface-raised` por `var()` nos dois modos; não confundir
   `--color-surface` com `--color-surface-raised`.

## Execução no Runtime de desenvolvimento (2026-11-11)

Método: Playwright com Firefox headless (método 1 de
`docs/operations/NAVEGADOR-TESTES.md`), frente
`tests/browser/transversal/aculta420-v2-audit-2026-11/`. Manifest versionado em
`tests/browser/capturas/aculta420-v2-audit/2026-11-11/manifest.json`.

- **774/774 checks PASS, 0 FAIL** no commit `ca4246fb6`.
- Domínios públicos: home, Fórum (`growroom420.aculta.toca.net.br`), Links,
  Wiki, Revista, Eventos, Loja, Apoio, Cursos e login da Conta — 10 hosts.
- Larguras: 320, 390, 768, 959, 960, 1024 e 1440 CSS px, em claro e escuro.
- 140 combinações públicas × 5 checks: canvas esperado (`#f2f7f0`/`#171513`),
  ausência de overflow horizontal, um `main`, no máximo um `h1` e contraste do
  cabeçalho institucional ≥ 4,5:1 (140/140 PASS).
- Conta autenticada (`teste_comum`, `/conta`, 14 combinações): sem overflow,
  canvas correto, um `main` e no máximo um `h1`.
- UI Patterns Library autenticada (`teste_administrativo`, story do
  `layout-page` em 960/1024/1440): 6/6 `aside` à esquerda do conteúdo
  confirmados por coordenadas; stories renderizadas.
- O `aside` de conteúdo real não aparece nas páginas públicas atuais: a home do
  Fórum usa `full` com `sidebar_first` e a Conta ainda não expõe
  `sidebar_first` ao layout (o menu é interno ao shell). A geometria
  conteúdo → aside foi homologada na UI Library, com as stories
  `aside` e `aside-no-title`.

### Cascata e tokens

- `layout.css` ficou restrito à base do shell (198 linhas). As regras da UI
  Patterns Library migraram para `component-library-mode.css` e o container do
  `site_key` account para `portal-account.css`.
- `ui-library-admin.css` não repete mais a moldura de `.ui_patterns_component`;
  mantém só o layout administrativo (colunas, painéis, barra de ferramentas).
- Removida a declaração duplicada de `--button-primary-bg-hover` no modo claro.
  Os tokens `--aculta-*` do contrato de fundamentos seguem sem consumidores
  fora de `tokens.css`, como documentado no próprio arquivo.
- Stylelint: 0 erros em todo o tema (eram 23 antes; 17 `max-empty-lines` e 6 de
  ordem de propriedades).
- O gate `validate-aculta420-css-scope.php` foi atualizado de `$purpose` para
  `$siteKey`, acompanhando a migração RN-00..RN-05 já aplicada no tema.

## Pendências para agente no Runtime (não executadas aqui)

P0 — **CONCLUÍDO.** Git conferido e reconciliado sem sobrescrever trabalho de
outras sessões; `git diff --check` limpo; todos os gates do tema PASS;
Stylelint 0 erros; PHPUnit do tema 27/27.

P1 — **PARCIAL.** Varredura de 10 domínios e UI Library concluída (774/774).
Faltam zoom real de 200%, navegação por teclado, `prefers-reduced-motion` e
aceite visual humano; nenhum deles foi declarado PASS.

P2 — **CONCLUÍDO.** Regras exclusivas da UI Patterns Library saíram de
`layout.css` para `component-library-mode.css`; container da conta foi para
`portal-account.css`; sobreposição de `.ui_patterns_component` removida.
`global-styling` ficou com 16 entradas e 106.888 bytes de CSS local, contra
116.177 bytes antes da extração (−9.289 bytes por página). O gate de escopo
ganhou cinco asserções de regressão para impedir que as regras da UI Patterns
Library e o container da conta voltem a `layout.css`.

P3 — **PARCIAL.** Aliases revisados; uma duplicata real removida e os
`--aculta-*` sem consumidor mantidos como contrato. Classificação
obsoleto/depreciado de toda a base fora de UI Suite depende de revisão de
consumidores em Views/Layout Builder e segue pendente.

P4 — **CONCLUÍDO.** `layout-page` (stack/aside/full), `layout-narrow`,
`layout-full`, `layout-two-columns`, `layout-grid` e `layout-section`
auditados. Container externo (750 px configurável) separado da medida de
leitura (68–70 ch); `!important` responsivo mantido com justificativa
documentada.

P5 — **PARCIAL.** Runtime testado em claro/escuro nos 10 domínios públicos e
na UI Library autenticada; cache reconstruído (`drush cr`) antes da rodada
final. Config Sync permanece divergente por trabalho de outras frentes, sem
importação nesta tarefa. Zoom, cache-hit por hash e teste cross-domain de
cache seguem pendentes.

## Riscos e critérios de aceite

- CSS Grid reposiciona visualmente aside no desktop, mantendo a ordem
  semântica. Testar foco e ordem de leitura, não usar `order` arbitrário
  nem injetar um segundo landmark principal.
- Fallback não deve carregar dois H1, perder conteúdo, duplicar
  `id="main-content"` nem alterar metadados/cache.
- O cabeçalho institucional deve garantir contraste >=4,5:1 para
  texto normal em ambos os modos.
- Tokens fundamentais do canvas, cabeçalho e foco não variam por skin.
- Nunca alterar layout de bloco para forçar aparência sem conferir
  conteúdo real e permissões. Nenhuma configuração destrutiva ou
  `drush cim` sem autorização/snapshot de Runtime.
