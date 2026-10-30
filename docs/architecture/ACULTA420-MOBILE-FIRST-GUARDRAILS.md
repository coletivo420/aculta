# Guardrails mobile-first do ACULTA420

**Status:** norma para todos os agentes e contribuidores que alterem apresentação no ACULTA420. A exigência cobre todo o tema e todos os purposes; não é exclusiva do Studio. 2026-10-30.

## Escopo

Aplicar a CSS/Sass, Twig, SDCs, JavaScript de apresentação, layouts, Views, bibliotecas anexadas, formulários, tabelas, modais, mídia, estados e stories que afetem o tema. Cobrir MAIN, ACCOUNT, SUPPORT, MAGAZINE/Revista, WIKI, SHOP/Loja, COURSES, FORUM, LINKS, EVENTOS e qualquer purpose ou consumidor adicional encontrado no código. Green Stash é a área comercial integrada ao Studio; não ganha shell, menu global ou aplicação próprios.

A fundação mobile-first é global. A fidelidade OpenCulturas Next continua restrita ao interior do Studio. **Institution Bar e Domain Header são contratos do shell e permanecem preservados.**

## Regras obrigatórias de implementação

1. Começar pela menor largura suportada: 320 CSS px. A base deve oferecer o conteúdo e a operação completos; aumentar espaço por `min-width` ou container query quando a tarefa e o conteúdo pedirem.
2. Não converter `max-width` em `min-width` mecanicamente, nem criar um stylesheet genérico que sobreponha todos os produtos. Antes de editar, rastrear seletor, cascata, library, template/SDC, consumers e states afetados. Regras ficam no arquivo que possui a apresentação.
3. Não usar largura/altura rígida para simular responsividade. Permitir shrink de itens Grid/Flex (`min-width: 0` quando apropriado), usar dimensões fluidas e propriedades lógicas, envolver texto longo e manter mídia dentro do container.
4. Não ordenar visualmente itens de forma diferente da ordem DOM/foco. Scroll bidimensional só pode ficar confinado a dados intrinsecamente tabulares ou conteúdo que realmente exija essa dimensão, sem alargar a página inteira e com alternativa acessível.
5. Não esconder operação, link, campo ou dado disponível em telas largas para fazer caber no mobile. Pode-se usar apresentação progressiva quando preservar rota, autorização, histórico, teclado, foco, estado, formulário não salvo e fallback sem JavaScript.
6. Formulários, editores, menus, diálogos, tabelas e barras de ação devem funcionar com teclado virtual e físico, leitor de tela e zoom. Foco e conteúdo não podem ficar cobertos por barras fixas; movimento respeita `prefers-reduced-motion`; cor não é sinal único. Claro/escuro podem trocar tokens, não geometria ou comportamento.
7. O tema apresenta tokens e markup neutros. Não mover para Twig/CSS decisões de rota, purpose, disponibilidade, ACL, dados, cache ou regra de negócio. Recursos sem implementação não viram controles funcionais. Mudança funcional pertence ao owner do módulo e deve seguir o ownership da PR; registrar `Pede-A` em vez de contornar contrato.
8. A construção deve respeitar Bootstrap 5, UI Patterns e SDC existentes. Reuso na ordem: componente atual, variante/slot, SDC transversal com consumer real, adaptador de apresentação somente quando indispensável. SDC novo exige schema, story e consumer real no mesmo commit.

## Regressão obrigatória por mudança

Escolher os consumers e purposes afetados pela cascata e registrar a escolha. A grade de referência completa é **320, 360, 390, 599, 600, 768, 1024, 1280 e 1440 CSS px**; incluir fronteiras próprias do componente quando relevantes. Validar overflow da página e do consumer, conteúdo longo, estado vazio/erro/sucesso, formulário, navegação e shell conforme aplicável.

Para gates transversais, exigir também:

- zoom 200% e 400%; teclado, Shift+Tab e foco visível;
- claro, escuro e `prefers-reduced-motion`;
- JavaScript ativo e sem JavaScript; caminhos AJAX e fallback quando existirem;
- anônimo, autenticado e contas A/B para conteúdo privado; purpose correto e cache contexts/tags/max-age;
- módulos opcionais presentes e ausentes;
- regressão de Fórum, Wiki, Revista, Loja, Eventos, Conta, Studio, GanjaLab, Green Stash integrado e demais consumers reais.

Usar Firefox ESR **visível** em `DISPLAY=:0` para screenshots, métricas e inspeção de browser. Não usar Chromium/Firefox headless como aceite visual. Salvar scripts reutilizáveis em `tests/browser/<frente>/` e README de cobertura/ambiente/variáveis/limites; manter screenshots, logs e outros artefatos em `$SUITE_OUT`, fora do repositório. Reutilizar `wdlib.py`.

## Registro e linguagem de status

- Registrar o baseline, estado antes/depois, purpose, URL, viewport, estado de autenticação, modo, zoom e teste no `web/themes/custom/aculta420/docs/mobile-first-audit.md` e no roadmap/catálogo relacionado.
- `PASS` exige execução real e evidência apropriada à abrangência da afirmação. Uma fixture CSS prova apenas a fixture; não prova rota ou Runtime. `DEFERRED` exige motivo e o restante possível deve ser executado.
- Não declarar `THEME-MF0` concluída com inventário parcial. Não declarar `THEME-MF3` ou todo o tema mobile-first com cobertura focal, sem evidência para todos os purposes e gates.
- Um problema em módulo proprietário deve ser reportado com caminho, contrato esperado e teste de aceite; não modificar serviço, controller, routing, permissions, dados nem Form API sem seguir autorização/ownership.

## Gates estáticos do tema

Executar conforme os arquivos alterados: `npm run lint:css`, `npm run lint:js`, `php scripts/validate-aculta420-sdc-schemas.php`, `php scripts/validate-aculta420-design-foundations.php`, `php scripts/validate-module-theme-boundary.php`, sintaxe/lint PHP quando houver PHP, PHPUnit pertinente e `git diff --check`. Gates não aplicáveis devem ser explicitamente identificados; falha preexistente deve ser separada da mudança, nunca chamada de PASS.

Este documento detalha a regra resumida no `AGENTS.md` raiz e em `web/themes/custom/aculta420/AGENTS.md`. O plano e o estado de execução ficam em `docs/architecture/PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md` e `web/themes/custom/aculta420/docs/mobile-first-audit.md`.
