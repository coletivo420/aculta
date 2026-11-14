# ACULTA420 V2 - SDCs de superfície

**Status:** inventário e comando publicados. Os arquivos PNG de referência ainda NÃO foram enviados ao GitHub; o agente deve enviá-los antes da homologação visual.

## Arquivos originais (14 PNGs)

Origem: `aculta420_sdc_blocos_referencias(1).zip`. Destino: `neutras-institucionais/`, preservando subpastas:

- referencias/01-neutra-claro-desktop.png
- referencias/02-neutra-claro-mobile.png
- referencias/03-neutra-escuro-desktop.png
- referencias/04-neutra-escuro-mobile.png
- referencias/05-institucional-claro-desktop.png
- referencias/06-institucional-claro-mobile.png
- referencias/07-institucional-escuro-desktop.png
- referencias/08-institucional-escuro-mobile.png
- originais/comparativo-neutral.png
- originais/comparativo-institucional.png
- LEIA-ME.txt

Origem: `aculta420_ultimos_blocos_sdc_vazios(1).zip`. Destino: `molduras/`:

- 01_bloco_verde.png
- 02_bloco_vermelho.png
- 03_bloco_tricolor.png
- 04_bloco_amarelo.png

## Comando de execução

Implemente no repositório `coletivo420/aculta`, branch `dev`, os SDCs de superfícies e conteúdo do ACULTA420 V2. Leia `AGENTS.md`, ADR-054 e documentação do tema, UI Library, UI Styles, UI Patterns e Layout Builder. Inspecione HEAD, worktrees e alterações paralelas antes de modificar. Não abra PR, não use force push e publique commits atômicos em `origin/dev`.

**Etapa zero:** enviar os 14 PNGs e o LEIA-ME ao caminho documentado, usando os ZIPs originais. Não inventar ou substituir arquivos. Sem os PNGs, não declarar homologação visual. Os PNGs são referências documentais, não fundos do tema.

**Inventário:** inspecionar SDCs existentes como `card-cultural`, `layout-section`, `layout-grid`, `dialog`, `auth-dialog`, `button`, `tabs`, `form-field`, `author-card`, `forum-post-card`, `revista-teaser`, `event-page-layout`, `reaction-emoji`. Reutilizar componentes atuais.

**Componentes:** implementar `aculta420_v2:surface` com slot content; `aculta420_v2:surface-accent` com variantes green, yellow, red, tricolor, neutral e molduras dos PNGs; `aculta420_v2:surface-panel` com slots header, content, footer, actions e cabeçalho institucional. Criar outros SDCs apenas se não forem variantes reutilizáveis. Propriedades com schema estrito, valores enumerados, CSS por tokens semânticos, sem HTML arbitrário nem lógica de negócio. Modais mantêm comportamento separado de superfícies.

**SDCs de conteúdo:** compor com tipografia, botões, alertas, formulários, abas, metadados, cartões, fórum, revista, wiki, eventos e LMS existentes. Criar apenas componentes ausentes e comprovadamente necessários; os dados e as permissões permanecem nos módulos funcionais.

**Integração:** UI Patterns (props, slots, sources), UI Styles (variantes), UI Library (catálogo administrativo com prévias reais vazias/preenchidas, light/dark e mobile/desktop), Layout Builder (quando suportado; adaptadores se necessário). Não restaurar a antiga configuração administrativa Layout de container. Preservar cinco regiões, barra multidomínio, dez purposes, displays e prévias reais restritas a administradores.

**Stories e testes:** demonstrar molduras verde, amarela, vermelha, tricolor, neutra e institucional; composições com texto, formulário, cartão, abas, grid e sidebar. Validar schema, slots vazios, XSS, cache, acessibilidade, foco, contraste, 320/390/768/1024/1440 px, zoom 200%, tema claro/escuro, integrações, regressões e Config Sync. Comparar screenshots com PNGs originais.

**Entrega:** atualizar documentação, roadmap e adendo ADR-054; realizar commits de referências, SDCs, integrações, stories/testes e homologação; push em `origin/dev`; apresentar HEAD, commits, arquivos, evidências, resultados e bloqueios reais. Não declarar concluído sem testes.
