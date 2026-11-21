# Revisão visual dos SDCs e paridade Discourse

Data: 21/11/2026
Branch: `feat/forum-paridade-discourse`

## Referências de design V2

Analisados: `surface`, `surface-panel`, `badge`, `icon`, `button`,
`popup`, `content-reply-bar`, `forum-post-card`, `studio-marley-workspace`,
tokens semânticos em `src/styles/tokens.css` e a matriz UI Patterns.
O propósito é uma experiência Discourse **nativa Drupal**, visualmente
coerente com ACULTA, sem copiar CSS ou componentes de terceiros.

A revisão foi **estática de Twig/CSS/stories e tokens**, não uma
captura do Runtime; modo escuro, mobile, zoom 200% e leitor de tela
são gates **DEFERRED** até uma execução real em navegador.

## Auditoria dos componentes recém-criados

| SDC | Divergência encontrada | Solução publicada |
| --- | --- | --- |
| `cgu-report-button` | Rótulo aberto na barra, dimensão diferente dos ícones | botão independente mantém texto fora da barra; na `content-reply-bar` é circular e de 44 px, com nome acessível |
| `forum-poll-builder` | Caixa com borda/raio CSS próprios | composição `surface` verde suave, preserva slots Form API e #states |
| `forum-poll-card` | Superfície branca duplicada e raio independente | composição de `surface` verde suave, mantendo Form API no slot |
| `forum-poll-results` | Resumo sem identidade tipográfica do tema | ícone Phosphor de gráfico, badges de votantes e seleções, tipografia Oswald |
| `forum-reading-timeline` | Caixa genérica, links sem apresentação uniforme | `surface`, badge do progresso e navegação com 44 px |
| `forum-draft-status` | Estados sem distinção iconográfica | badges com ícones Phosphor para editando/salvando/salvo/erro, sem prometer autosave |
| `forum-revision-history` | Tabela sem superfície comum | `surface` raised, rolagem horizontal confinada e cabeçalho padronizado |

## Stories adicionadas

13 prévias complementares: múltipla escolha, enquete encerrada/indisponível,
resultados sem votos, novas publicações/leitura completa, rascunho salvando/
salvo, histórico vazio/extenso, popup CGU carregando/erro, barra somente com
ação de denúncia.

Novos SDCs visuais: `forum-solution-state` (duas stories) e
`forum-tracking-level` (quatro stories). Reutilizam `surface` e `badge`.
Nenhum dos dois registra solução, altera notificações, modifica banco ou
cria permissões. Os slots `actions` só recebem comandos autorizados do owner
em uma fase futura.

## Outras lacunas Discourse

1. Conectar Solved/Assign/Topic Voting ao serviço proprietário, à ACL e aos
   dados, antes de expor ações públicas.
2. Persistência real de nível de acompanhamento e notificações por conta,
   purpose e Group; `forum-tracking-level` não é seletor funcional.
3. Rascunho com autosave/retomada e concorrência protegidos; stories de
   `saving`/`saved` não são prova de funcionalidade.
4. Mentions com sugestões autorizadas, privacidade e controles de spam.
5. Cases e review timeline CGU: reutilizar `studio-marley-workspace`,
   `studio-report-item`, `surface-panel` e SDC Wiki, sem clonar motor.
6. Stories de estados restritos e Browser de foco no popup; uma story só
   apresenta a interface e não substitui testes do Form API.
7. Revisões comparadas fora do admin: definir ACL antes de integrar frontend.

## Gates de aceite e situação

- Contratos PHPUnit: `ForumDiscourseSdcContractsTest` acrescido para
  `surface`, stories e ausência de ações falsas.
- `php scripts/validate-aculta420-sdc-schemas.php`
- `php scripts/validate-aculta420-sdc-library-contract.php`
- `node_modules/.bin/stylelint` nos CSS alterados
- `php vendor/drush/drush.php php:script scripts/validate-aculta420-v2-sdc.php`
- Browser UI Patterns Library: 320/390/1280 px, claro/escuro, teclado,
  alto zoom, popup, contraste e leitura de tabela.

**Os gates dependentes do checkout completo, vendor/ e Runtime foram
DEFERRED nesta sessão remota.** Não declarar PASS sem evidência.
