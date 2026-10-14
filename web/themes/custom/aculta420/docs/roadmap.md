# Roadmap — ACULTA420

Atualizado em 2026-10-09 pela revisão documental. Dívidas e pendências, com evidência,
estão em [`docs/operations/DEBT-REGISTER.md`](../../../../../docs/operations/DEBT-REGISTER.md).
Este roadmap planeja o saneamento primeiro e as features de produto depois. Não planeja 1.0.

## Releases e marcações

| Versão | Estado | Marcação | Conteúdo |
| --- | --- | --- | --- |
| 0.6.0-dev.33 | Caixa compacta de remetente e ação de amizade no perfil público | Capturas locais desktop/mobile da lista revisadas; fluxo de amizade DEFERRED | Mensagens exibem avatar circular + nome; ação do perfil usa a forma de botão “Ver Perfil” |
| 0.6.0-dev.32 | Agrupamento de conversas, menu contextual e badge contextual | Implementado; apresentação substituída por dev.33; RUNTIME STATUS: DEFERRED | Menu aciona formulário nativo de histórico pessoal; badge vermelho só no shell de conta |
| 0.6.0-dev.30 | Primeiro item de Minha Conta usa destaque vermelho semântico na barra | Implementado; validação visual pendente; RUNTIME STATUS: DEFERRED | Hover e foco usam superfície vermelha; demais itens do menu preservados |
| 0.6.0-dev.29 | Contadores vermelhos ao lado dos ícones da barra multidomínio | Implementado; validação visual pendente; RUNTIME STATUS: DEFERRED | Badge compartilhado pelo carrinho, mensagens e avisos |
| 0.1.0 | concluída (2026-10-07) | marcada no código | Foundation: provider, namespace, tokens, fronteiras |
| 0.2.0 | concluída (2026-10-09) | marcada no código | Shell multidomínio: Domain Presentation, Institution Bar, Domain Header, sticky, QA |
| 0.3.0 | concluída (2026-10-09) | marcada no código | Card System v1: `editorial-card` stable, `project-card` experimental, skin do curso |
| 0.3.1 | concluída (2026-10-09) | marcada no código | Padrões SDC: seções, hero, grade, carrossel e cabeçalho por purpose |
| 0.6.0-dev.22 | Durações de movimento centralizadas em tokens (DT-T19): gate de Foundation volta a passar | Gate de Foundation PASS (328); validar no servidor de testes | Tokens `--aculta-motion-fast` e `--aculta-motion-quick` |
| 0.6.0-dev.23 | Componentes SDC experimentais de conversas, mensagens, composição, Site Alert, notificações, não lidas e estados | Gates de código pendentes; Runtime visual DEFERRED | Slots preservam render arrays e formulários nativos; sem lógica funcional no ACULTA420 |
| 0.6.0-dev.24 | Gatilho modal de avisos e apresentação de leitura por conversa | Gates pendentes; RUNTIME STATUS: DEFERRED | Visualização consome estado nativo provido pelo Private Message/Portal |
| 0.6.0-dev.26 | Gavetas Bootstrap de mensagens e avisos no padrão visual do carrinho, com AJAX ao abrir | Código local; validação de gates e runtime pendente; RUNTIME STATUS: DEFERRED | Mesmo shell Bootstrap, largura 420 px e tokens do carrinho; atualização apenas ao abrir, sem polling |
| 0.6.0-dev.27 | Badges atualizados na abertura das gavetas e remoção do throbber da busca no desktop | Código local; visual desktop/mobile pendente; RUNTIME STATUS: DEFERRED | Resposta AJAX já transporta o contador; a busca continua AJAX e o throbber permanece em telas menores |
| 0.6.0-dev.28 | Caixa em balões com avatar e modal de resposta; avisos vermelhos dispensáveis por pessoa | Implementado; validação interativa pendente; RUNTIME STATUS: DEFERRED | Private Message continua dono da thread/formulário; SDC só exibe render arrays autorizados |
| 0.6.0-dev.21 | Modelo padrão de publicação da Revista (`revista-publication`): todas as Notícias e blogs usam o mesmo layout | Em desenvolvimento; SDC, shell e fronteira PASS; validar no servidor de testes | Templates `node--article--full` e `node--blog--full` chamam o mesmo componente experimental |
| 0.6.0-dev.20 | Revista: imagens do corpo (notícia e blog) centralizadas na coluna de leitura e intertítulos alinhados à esquerda | Em desenvolvimento; gates de código PASS; validar no servidor de testes | Imagens do corpo chegam como mídia embutida (`media--type-image`), sem regra de módulo |
| 0.6.0-dev.19 | Sugestões interativas na busca Wiki420, com o comportamento de prévia compartilhado | Gates de código e HTTP local PASS; validar interação por teclado e visual no servidor de testes | O Portal consulta apenas verbetes acessíveis do Domain Wiki; tema estiliza o dropdown |
| 0.6.0-dev.18 | Apresentação Wiki420 inspirada na Wikipédia: cabeçalho compacto, busca junto da marca, categorias compactas e tipografia de leitura | HTTP e gates de contrato locais PASS; screenshot local indisponível; validar em desktop/mobile, claro/escuro no servidor de testes. Foundation bloqueado por DT-T19 | Busca recebida pelo contrato neutro; somente o Domain Header Wiki varia; nids de conteúdo de teste 72/73 somente em `aculta_runtime` |
| 0.6.0-dev.17 | Marca de domínio como contrato neutro: o Portal envia a chave, o tema entrega o asset (fronteira módulo x tema) | Em desenvolvimento; gate de fronteira PASS sem exceção do builder, teste com tema Claro PASS; inspeção visual DEFERRED | Compatibilidade de tema do Portal e da foto de perfil |
| 0.6.0-dev.16 | Apresentação enciclopédica Wiki420: pattern artigo/teaser, autoria omitida da leitura pública, home e busca revisadas | Em desenvolvimento; SDC/tokens/shell/fronteira PASS, Foundation bloqueado por DT-T19 preexistente; HTTP local PASS, inspeção visual DEFERRED | Primeiro layout enciclopédico com ownership Drupal → Portal → ACULTA420 |
| 0.6.0-dev.15 | Somente a Institution Bar multidomínio permanece no topo; Domain Header rola normalmente | RUNTIME STATUS: PASS; Chrome desktop/mobile em `/dados/outros-dados` (2026-10-13) | Sticky aplicado diretamente à barra institucional |
| 0.6.0-dev.14 | Institution Bar e Domain Header agrupados no topo durante o scroll | Comportamento substituído por 0.6.0-dev.15 após feedback do responsável | O wrapper sticky fazia o cabeçalho permanecer fixo também |
| 0.6.0-dev.13 | Botões adicionais da página LINKS recebidos do Portal e exibidos em sequência vertical | RUNTIME STATUS: validação pendente | Layout reutiliza a apresentação do botão fixo |
| 0.6.0-dev.12 | CTA “Visite nosso Site” na página LINKS, com destino à raiz MAIN resolvido pelo Portal | RUNTIME STATUS: pendente nesta alteração | Os canais sociais configurados permanecem no rodapé |
| 0.6.0-dev.11 | Página LINKS com canais administráveis, cards de projeto em carrossel VVJB e rodapé dedicado | RUNTIME STATUS: HTTP e visual mobile (claro/escuro) PASS; Foundation gate bloqueado por durações literais preexistentes em `portal-account.css` | Reusa View `aculta_projects`, SDC `carousel` e ícones Bootstrap `currentColor` |
| 0.6.0-dev.10 | Pattern mobile de links sociais, barra institucional e referências aos projetos publicados | RUNTIME STATUS: estrutura HTTP verificada no servidor de testes; inspeção visual pendente | Instagram institucional cadastrado; canais adicionais aguardam confirmação |
| 0.4.0 | linha 0.4.x fechada em 0.4.5 (2026-10-09), sem versão 0.4.0 retroativa (decisão do responsável) | sem tag | Patterns v1; T1 a T6 com decisão ou conclusão registradas (T5 com reconstrução em ambiente novo DEFERRED) |
| 0.6.0-dev.7 | página do produto da loja em SDC: galeria em carrossel, compra com preço, detalhes e abas (layout da loja original) | marcada no código; gates PASS; captura de tela DEFERRED |
| 0.6.0-dev.6 | marca da barra institucional: logo ACULTA no lugar do nome, 78×26 (1x) e 156×52 (2x) em WebP; nome completo só no `alt` | marcada no código; validada visualmente (captura de tela) |
| 0.6.0-dev.5 | F3 (busca): apresentação da página `/busca`, do popup e dos resultados com tokens do tema | marcada no código; `/busca` verificada no servidor de testes; popup e teclado DEFERRED |
| 0.6.0-dev.2 | validador de navegador: regras de barra e sigla ajustadas ao shell; apoio indisponível aceito; PASS no site público | marcada no código |
| 0.6.0-dev.1 | F4 (UI Patterns): biblioteca de componentes com 9 stories; ui_patterns ^2.0 | marcada no código |
| 0.5.1 | F2 (ícones) concluída: Bootstrap Icons, sem dependência nova; gate contra SVG inline no tema | marcada no código (release) |
| 0.5.0 | F1 (modo de cor) concluída: claro, escuro e automático; validada no Runtime oficial em 2026-10-09 | marcada no código (release) |
| 0.5.0-dev.3 | F1 (modo de cor): atributo aplicado via `b5_theme_mode` (o Bootstrap 5 sobrescrevia `html_attributes`); verificado por requisição HTTP em cópia isolada do Runtime | marcada no código; RUNTIME STATUS DEFERRED |
| 0.5.0-dev.2 | F1 (modo de cor): modo claro, escuro e automático com contrato do Portal; gate ajustado para o bloco automático e o ramo de contrato | marcada no código; RUNTIME STATUS DEFERRED |
| 0.5.0-dev.1 | F1 (modo de cor): medição do modo escuro em navegador, pré-requisito atendido; tokens da barra sem alteração | marcada no código |
| 0.4.5 | fechamento da linha 0.4.x (2026-10-09): 0.4.4 (PR #122), 0.4.5-dev.1 a dev.5 (PRs #124, #129, #130 e o dev.5); DT-T18 pendente, independente do tema | marcada no código (release) |
| 0.4.5-dev.5 | sitemap do validador de navegador delegado ao `aculta_deployer sitemap`; DT-T18 pendente (independente do tema) | marcada no código |
| 0.4.5-dev.4 | validador de navegador: página de apoio no host SUPPORT (DT-T18) | marcada no código |
| 0.4.5-dev.3 | cards de projeto: imagem 256 px com 2x e conteúdo centralizado | marcada no código |
| 0.4.5-dev.2 | imagem dos cards de projeto e grade responsiva (CSS entregue pela biblioteca global) | marcada no código |
| 0.4.5-dev.1 | kit de artes Bloco Sativa 420 (sem consumidor de template; autoria e licença pendentes) | marcada no código | Kit validado offline; QA em navegador DEFERRED |
| 0.4.4-dev.1 | gates executados no runtime e correção do gate institucional | marcada no código |
| 0.4.3 | PR #115 mesclada (kit Baque Sativa; sem consumidor de template) | marcada no código | Kit validado offline; QA visual e runtime DEFERRED |
| 0.4.2 | PR #114 mesclada (assets Podplant420; handoff fora do tema, sem consumidor de template) | marcada no código | Assets de identidade Podplant420 validados offline; QA visual e runtime DEFERRED |

Regras de versão em [`docs/versioning.md`](versioning.md). A versão atual fica marcada em
`aculta420.info.yml`. Tags Git só são criadas sob pedido do responsável.

## Saneamento

### T0 — Higiene documental (DT-T01, DT-T02, DT-T14) — concluída em 0.4.0-dev.2

- Remover "0.1.0" como versão atual em `README.md` e `docs/README.md` (`docs/features.md` já foi removido),
  `docs/design-system.md` e `docs/development.md`. Manter menção histórica quando for histórica.
- Descrever o estado atual do shell (0.2.0 em diante), e não a Foundation.
- Critério: nenhum documento afirma "versão atual 0.1.0"; o gate de documentação passa.

### T1 — Fechar a linha 0.4.0 (DT-T03) — decisão registrada em 0.4.0-dev.2

Estruturas internas permanecem como rich text (ver `components.md`, decisão T1). T2 e T3 foram concluídas em 0.4.0-dev.4 e 0.4.0-dev.5, então a condição de fechamento da linha está cumprida. Como o tema já está em 0.4.3, fechar a linha com a versão `0.4.0` seria um retrocesso de versão; o fechamento sem versão retroativa depende de decisão do responsável.

- Decidir, para cada estrutura interna, entre migrar para SDC ou manter como conteúdo rico:
  `aculta-areas`, `aculta-callout`, `aculta-page-intro`, CTAs (`aculta-editorial-link`) e
  `aculta-institutional-note`.
- Critério (decisão do responsável, 2026-10-09): decisão registrada por estrutura; a linha 0.4.x fecha em `0.4.5` sem versão `0.4.0` retroativa e sem tag. Cumprido.

### T2 — Validação em navegador (DT-T04, DT-T05, DT-T06) — concluída em 0.4.0-dev.4

- Carrossel validado em navegador e reativado na home em 0.4.0-dev.3 (DT-T04).
- Rail validado com quatro cursos reais publicados em 0.4.0-dev.4 (DT-T05): rolagem por teclado,
  sem rolagem horizontal da página e todos os cards alcançáveis por Tab. Os cursos de teste foram
  removidos após a medição.
- Foco por teclado medido com foco real (DT-T06): o card do LMS tinha só a borda amarela, com cerca
  de 1,6:1 sobre superfície clara; recebeu anel com `--aculta-focus-ring`.
- Rótulos em inglês corrigidos (DT-T15) em 0.4.0-dev.3.
- Critério cumprido: cada item com medição registrada no changelog, sem clones no DOM.

### T3 — CSS residual dos padrões (DT-T07) — concluída em 0.4.0-dev.5

- `.aculta-hero` e `.aculta-eyebrow` saíram de `institutional.css` para `components/patterns/hero/hero.css`.
- `.aculta-section-title` saiu de `content.css` para `components/patterns/content-section/content-section.css`.
- `.aculta-project` (moldura do presenter) e seus filhos saíram de `institutional.css`, `content.css` e `drupal-bootstrap.css` para `components/content/project-card/project-card.css`.
- Medição antes/depois: estilo computado idêntico por propriedade em 17 seletores de controle e padrão; 14 capturas de tela idênticas byte a byte (1280 e 390 px, 7 páginas); foco e movimento reduzido do card idênticos.
- Os seletores compartilhados por `.card` continuam globais em `content.css`, porque ainda não têm consumidor de SDC.

### T4 — Validação automatizada (DT-T08, DT-T09) — concluída em 0.4.1

- Schemas dos SDCs validados contra props e slots usados nos templates (`validate-aculta420-sdc-schemas.php`, DT-T08).
- Validadores `.mjs` sem porta nem origem fixas: leem `ACULTA_DEVTOOLS_PORT` e `ACULTA_SITE_ORIGIN` por `scripts/lib/browser-env.mjs`. Sem as variáveis, o validador para antes de conectar (DT-T09).
- Gate `validate-browser-validators.php` reprova endpoint literal nos scripts.
- Critério: schema inválido quebra o gate; nenhuma porta fixa nos scripts. Cumprido.
- Dívida nova: `validate-institution-browser.mjs` espera `/apoie` no host principal e `aculta_favicon.ico`. A página de apoio fica no host SUPPORT (homelab `apoio.aculta.toca.net.br`; produção `apoio.aculta.org`), na rota `/apoio` (Portal 0.2.0-dev.3). Falta a reescrita de `supportLayout` e o favicon oficial (DT-T18, pendente e independente do tema).

### T5 — Portabilidade do conteúdo (DT-T10, DT-O03) — concluída em 0.4.1; reconstrução em ambiente novo verificada em 2026-10-09

- Conteúdo de seções, missão, cabeçalho de projetos e hero declarado em `scripts/content/institution/home-content.json`, com exportador e loader por UUID, dry-run por padrão (`ACULTA_APPLY=1` para gravar).
- Gate `validate-institution-content.php`: o JSON cobre todo UUID referenciado pelas colocações da home e do cabeçalho.
- Dry-run no Runtime atual: 14 entidades sem diferença. Teste negativo: alteração do slogan detectada.
- DEFERRED: reconstrução completa num ambiente novo. O único snapshot em `estados/` é de 2026-10-04 e não tem os campos de seção nem do hero; a verificação exige importar a configuração num ambiente de teste, o que fica para uma tarefa autorizada.
- Critério parcial: versionado e verificável; reconstrução em ambiente novo pendente.

### T6 — Dependência do LMS (DT-T11) — concluída na PR #113 (merge 0.4.1)

- A skin do `lms:course_card` não define nem consome mais as variáveis `--color-*` do módulo. Propriedades com tokens ACULTA, mesmos seletores e cascata.
- Medição: sem diferença de estilo computado no catálogo (padrão e foco, 1280 e 390 px); capturas idênticas.
- Gate `validate-lms-skin.php`: fixa a versão do LMS revisada (1.2.3). Upgrade reprova até revisão da skin e da captura de referência.
- Decisão: cores de status do LMS ficam como fallback do módulo.
- Critério cumprido para o que é visível a visitante; estados de status pendentes (ver CHANGELOG 0.4.1).

## Features de produto (depois do saneamento)

Só começam quando T0–T4 estiverem fechadas, ou quando o responsável priorizar explicitamente.
Nenhuma destas fases tem versão alvo de 1.0.

### F1 — Modo de cor e controle de aparência (DT-T12)

- Seleção claro, escuro e automático com `data-bs-theme`; preferência persistente para usuário
  autenticado na camada de conta, e local e sem quebra de cache para anônimo.
- Pré-requisito: T2 (validação do modo escuro em navegador real).

**Estado: concluída em 0.5.0 (2026-10-09), validada no Runtime oficial.**

- Decisões do responsável: o controle fica em **Minha Conta > Configurações** (`/configuracoes`, no Portal); a preferência é gravada por usuário no banco (`user.data`); há três estados (claro, escuro, automático); o padrão global do site fica em `/admin/config/aculta/aparencia`.
- Portal (0.2.0-dev.18): `ColorModePreference` resolve o modo e publica o contrato `aculta_color_mode`. O tema não lê usuário nem configuração.
- Tema (0.5.0): `ThemeHooks::preprocessHtml` define `b5_theme_mode` (o `html.html.twig` do Bootstrap 5 grava `data-bs-theme` a partir dele). Em `auto` o atributo fica vazio, e `tokens.css` decide por `@media (prefers-color-scheme: dark)` guardado por `:root:not([data-bs-theme="light"])`.
- Sem JavaScript de modo e sem `localStorage`. Tokens da barra institucional não foram alterados.
- Evidência no Runtime oficial (`https://aculta.toca.net.br`, código da `main` em `ce87dcb`):
  - roteiro automatizado: 7 de 7 checagens (padrão global, escolha da pessoa usuária, HTML anônimo e logado, cache invalidado após salvar);
  - HTTP: `light` → `"light"`, `dark` → `"dark"`, `auto` → `""`;
  - automático no navegador: sistema escuro → fundo `rgb(23,21,19)`; claro → `rgb(242,247,240)`;
  - contraste no modo escuro: 52 medidas, pior caso 8,82:1, sem overflow.
- Gates na `main`: design, Foundation (310), SDC, validadores de navegador e Portal, todos PASS.
- Sem teste humano (decisão do responsável). Padrão inicial do site: `light`.

### F2 — Ícones (DT-T13)

**Estado: concluída em 0.5.1 (2026-10-09). Bootstrap Icons 1.11.0; sem dependência nova.**

- Decisão do responsável: ícones pelo Bootstrap Icons.
- Sem dependência nova: a fonte já vem do pacote `bootstrap5` (`dist/icons/1.11.0`) e é carregada em todas as páginas pela biblioteca `bootstrap5/global-styling`. Verificado no site público: a família `bootstrap-icons` carrega e `bi-house` renderiza o glifo.
- Regra: ícone é `<i class="bi bi-NOME" aria-hidden="true"></i>` ao lado de texto visível (ou rótulo acessível no pai). Nenhum SVG inline de ícone nos templates do tema; o gate `validate-aculta420-design-foundations.php` reprova.
- Sem SDC de ícone até haver consumidor com contrato reutilizável (AGENTS: não criar SDC só para substituir uma classe simples).
- Fora do tema: o editor de fotos do Portal (`aculta-portal-photo-editor.html.twig`) tem um SVG inline. Não é tratado aqui, porque o Portal não referencia componentes do tema.

### F3 — Busca e feedback (DT-T13)

- **Decisão do responsável (2026-10-09): busca pelos serviços do banco** (Search API com o backend de banco de dados), sem serviço de busca externo.
- **Estado: backend e página entregues no Portal 0.2.0-dev.19 e validados no Runtime oficial** (`/busca`, sugestões do Core, avisos por Messenger; conteúdo não publicado excluído). Pendências: `content_access` no índice e testes de Kernel.
- Apresentação de busca com Search API e Autocomplete; mensagens por toast e alert com
  Messenger. Backend e índices ficam no Portal e no Core.
- **Apresentação no tema (0.6.0-dev.5):** `css/components/search.css` (página, popup e resultados). Pendente: verificação do popup e do teclado no servidor de testes (`/busca` já verificada em 1280 px) . A remoção da regra com cores fixas no Portal foi feita na `aculta_portal` 0.2.0-dev.46 (PR #186).
- Pré-requisito: F2 (concluída em 0.5.1). Verificar, antes de implementar, se o backend de banco atende a relevância e a latência esperadas.
- **Estado: aprovada no reteste de navegador (2026-10-11).** Verificado no servidor de testes com Claude in Chrome, logado como admin: popup, foco, Tab e Shift+Tab em ciclo, rótulo do botão fechar, Esc, prévia com mínimo de caracteres, setas, Enter e página `/busca` (campo com o termo e dica sem termo). Exceção: T10 (390 px) não executado, porque a janela de teste não redimensiona. Dívida fora do tema: DT-P24 (erro do Core Navigation no Esc, só logado).

### F4 — Padrões de Drupal UI e biblioteca de componentes (DT-T13)

**Estado: concluída em 0.6.0-dev.3 (biblioteca UI Patterns Library com estados documentados).** Consumidores em Views e Manage Display ficam para quando houver uso real.

- UI Patterns para Views e Manage Display onde houver consumidor real.
- Biblioteca navegável dos componentes (UI Patterns Library ou UI Examples), com estados
  documentados.
- Pendente de decisão do responsável: qual módulo usar para a biblioteca. UI Patterns expõe os componentes SDC do tema ao site builder (Layout Builder, Views, formatadores). UI Examples é um módulo de exemplos de referência, para documentar componentes; não muda a renderização. Recomendação: UI Examples para a biblioteca, e UI Patterns só quando houver consumidor real.
- Pré-requisito: T4 (schemas validados).

### F5 — Mega menu

- **Decisão do responsável (2026-10-09): só depois do primeiro RC.** Antes disso não há produção estável para validar a navegação.

## Critério para encerrar o saneamento do tema

Todos os itens de DEBT-REGISTER do tema estão em Resolvida, Decisão registrada ou Aberta com
justificativa aceita pelo responsável. Os gates passam. Não há dívida nova sem registro.
