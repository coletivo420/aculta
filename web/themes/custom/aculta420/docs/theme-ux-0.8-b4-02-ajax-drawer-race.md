# THEME-UX 0.8-B4-02 — Corrida de respostas nos drawers

**Data:** 2026-10-22. **Estado:** hardening estático commitado, sem validação no Runtime.

## Falha observável por inspeção
Em `js/messenger-drawers.js`, as gavetas de Avisos e Mensagens fazem fetch e conversão `response.text()`. Quando outra navegação ou fechamento abortava o request, uma promise já resolvida poderia prosseguir e substituir o conteúdo corrente. O risco aparece especialmente no encadeamento entre `show.bs.offcanvas`, `drawer.acultaNavigate()` e `hide.bs.offcanvas`.

## Correção
- Guard `activeRequest.signal.aborted || request !== activeRequest` antes de aplicar o HTML em **ambos os caminhos**: navegação da gaveta e carregamento inicial na abertura.
- Mensagens de erro somente são exibidas quando o request ainda é o ativo e não foi abortado.
- `request = null` ao encerrar a requisição atual e remover `aria-busy`, sem afetar requisições subsequentes.
- Mantidos `Drupal.detachBehaviors` e `Drupal.attachBehaviors` durante substituição de conteúdo, cabeçalhos de contagem e URLs nativas. Sem novo framework de overlays, sem duplicar o Bootstrap.
- Sem alterações nos componentes globais Institution Bar + Domain Header.

## Gate de revisão B4-02 no homelab
1. Abrir Avisos, fechar imediatamente e reabrir; conferir o conteúdo e ausência de erro stale.
2. Abrir Mensagens, navegar entre duas conversas rapidamente; a resposta antiga não pode substituir a atual.
3. Abrir Mensagens e acionar `Iniciar conversa` do cartão de usuário; conferir a ordem entre abertura e URL de destino.
4. Validar `aria-busy`, estado de foco, Esc/X/backdrop e retorno ao acionador.
5. Confirmar contadores e ações com permissões, cache e isolamento por usuário.
6. Regressão do carrinho (Commerce) e Minha Conta (navegação AJAX, formulários, histórico) em 320/390/768/1280 e claro/escuro.
7. Drush CR, JS syntax/lint, Theme hooks, UI Suite/SDC e screenshot da Institution Bar + Domain Header.

## Próximo incremento visual B4-03/B5
Após confirmação do comportamento em navegador, padronizar o catálogo visual de drawers, modais e painéis da Minha Conta sem criar segundo gerenciador JS de modais, respeitando SDC Core, UI Patterns, UI Styles, Bootstrap e separação módulo/tema.

**Não marcar testes Runtime como PASS sem execução verificável.**
