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

## Pendências para agente no Runtime (não executadas aqui)

P0. Executar antes de novas mudanças: `git status --short`,
`git fetch origin`, `git rev-parse HEAD`; reconciliar HEAD remoto
sem sobrescrever alterações de terceiros. Rodar `php -l` nos PHPs
alterados, `git diff --check`, `php scripts/validate-aculta420-v2-foundations.php`,
`php scripts/validate-aculta420-design-foundations.php`,
`php scripts/validate-aculta420-shell-regions.php --strict`,
`php scripts/validate-aculta420-theme-settings.php`,
`php scripts/validate-aculta420-css-scope.php`,
`php scripts/validate-aculta420-sdc-schemas.php`,
`php scripts/validate-aculta420-ui-skins.php` e
`npm run lint:css` no diretório correspondente. Corrigir falhas
que pertençam ao escopo e classificar outras como preexistentes com prova.

P1. Firefox/Playwright no Runtime: varrer home, Fórum,
Wiki, Revista, Conta/Studio, Events, Loja, Links e UI Library.
Capturar 320/390/768/959/960/1024/1440 CSS px em claro/escuro,
zoom 200%, teclado, foco, landmarks, RTL se aplicável,
contraste e reflow. Verificar `aside` com/sem título, com/sem bloco
de sidebar, inclusive fallback simulado; cobrir headers e
cards institucionais. Não afirmar PASS por evidência histórica.

P2. Inventariar CSS efetivamente entregue por rota; extrair
regras exclusivas da UI Patterns Library de `src/styles/layout.css`
para o estilo já contextualizado da biblioteca, sem perder o
CSS admin do catálogo. Examinar `global-styling` vs assets SDC
para carregamento duplicado real, preservando apenas dependências
justificadas. Comparar computed styles e bytes antes/depois.

P3. Analisar todos os aliases `--color-*` / `--aculta-*`:
normalizar semântica e dependências com `var()`, mantendo nomes
existentes como ponte; registrar consumidores e exceções. Não fazer
substituição global ingênua. Concluir classificação
obsoleto/depreciado de regras e seletores fora de UI Suite
conforme ADR-054/055, sem remoções até confirmar consumidores.

P4. Auditar `layout-page`, `layout-full`, `layout-narrow`,
`layout-two-columns`, `layout-grid`, Layout Builder e a
geometria de `68ch` vs `750px` vs `1200px`. Tratar largura
de container vs medida de leitura como conceitos diferentes.
Evitar `!important` responsivo genérico quando substituição
focada funcionar. Atualizar documentação anterior à ADR-054/055.

P5. Testar backend/Runtime/Config Sync em claro/escuro e todas
as skins, com evidências de cache-hit, zoom, autorização e
comportamento cross-domain. Atualizar roadmap, docs do tema,
gates e changelog, documentar `PASS/FAIL/DEFERRED`, commit
e push diretos para `dev` somente com escopo e provas.

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
