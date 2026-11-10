# ADR-054 - ACULTA420 V2 SDC First e Layout Builder multidominio

Status: aprovado pelo responsavel em 2026-11-09; implementacao por fases, homologacao pendente.
Substitui parcialmente ADR-046 (restricao jQuery), ADR-048 (Layout Builder excluido) e o painel Layout de container.
Preserva ADR-047, ADR-050, ADR-053, seguranca, mobile-first, tokens e contratos do Portal.
Plano: docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md.

## Rodada 1 - decisoes

1. jQuery 4 liberado amplamente em modulos ACULTA, anexado via core/jquery por biblioteca, sem duplicar a distribuicao do Core.
2. Blocos basicos do Drupal permanecem como plugins nativos, configurados via UI Patterns e adaptados visualmente quando necessario.
3. Reduzir regioes e compor a apresentacao prioritariamente por SDC.
4. Shell global compartilhado; composicao interna por dominio.
5. UI Patterns Library como catalogo de SDCs e blocos basicos com previas.

## Rodada 2 - decisoes

1. Cinco regioes finais: institution, header, content, sidebar_first e footer. Preservar page_top/page_bottom como placeholders do Core.
2. Adotar Layout Builder + UI Patterns Layouts.
3. Previas de blocos com dados reais do Drupal.
4. HTMX e jQuery 4 conforme necessidade e decisao documentada. ADR-050 continua priorizando HTMX em novas requisicoes e formularios; isto nao libera genericamente Drupal AJAX.
5. Nova arquitetura implantada em paralelo, substituida apenas apos homologacao.

## Rodada 3 - decisoes

1. Cobertura de todos os conteudos e displays **tecnicamente compativeis**, por inventario e lotes de habilitacao de Layout Builder; nao forcar displays incompativeis.
2. Layouts comuns compartilhados; landing pages proprias para cada purpose.
3. sidebar_first fica como fallback nativo; layouts SDC podem ter barras internas.
4. **Somente administradores** acessam previas reais. Permissao administrativa nao dispensa verificacao de acesso ao bloco e aos dados.
5. jQuery 4 permitido tambem em SDCs do tema, quando necessario e sem carregamento global obrigatorio.
6. Piloto de rollout: Links / LinkWeed.

## Decisao complementar - fim da configuracao Layout de container

Remover a funcao administrativa 'Layout de container' do formulario de configuracoes do tema ACULTA420 V2. A largura, o estilo e a estrutura de cada pagina serao definidos pelos layouts/SDCs e pelo Layout Builder.

Durante a implementacao paralela, **nao** apagar configuracao aculta_purpose_container, schema, classe PurposeContainerLayout ou estilos de compatibilidade antes da homologacao do novo renderizador. A classe tambem possui utilidade de identificacao de rotas da UI Patterns Library; separar esses usos antes de removela. No corte homologado, descontinuar data-purpose-layout, data-purpose-style e variaveis CSS antigas. Documentar rollback.

## Adendo - Sistema de SDCs de Superfície e Conteúdo

1. Criação da família de superfícies SDC no tema `aculta420_v2`:
   - `aculta420_v2:surface`: Superfície base estrutural configurável por propriedades estritas (`tone`, `radius`, `spacing`, `elevation`, `border`) e slot `content`.
   - `aculta420_v2:surface-accent`: Superfície e moldura de destaque implementando fielmente as 4 referências visuais de blocos unitários e tricolor (`green`, `yellow`, `red`, `tricolor`) e a faixa tricolor superior fina das referências neutras institucionais (`neutral`). Geometria semântica em CSS puro, sem assets estáticos rasterizados em produção.
   - `aculta420_v2:surface-panel`: Painel institucional de superfície com bloco superior verde de destaque contínuo (modo claro e escuro) e slots para `header`, `content`, `footer` e `actions`.
2. Integração com UI Styles:
   - Registro de `surface_accent_style`, `surface_elevation_style` e `surface_radius_style` combináveis com `surface_style` em `aculta420_v2.ui_styles.yml`.
3. Integração com Layout Builder:
   - Os componentes de superfície com slots são automaticamente reconhecidos e disponibilizados como Layouts no Layout Builder através do derivador nativo `ComponentLayout` do módulo `ui_patterns_layouts`.
4. Composição com SDCs de conteúdo:
   - Tipografia, botões, formulários, abas, cartões culturais e layouts do ACULTA420 V2 são integrados por composição via slots sem duplicar HTML ou estilos específicos.

## Contratos e limites

- O Portal conhece os dez purposes e as regras de rota, dominio, acesso e cache; o tema recebe contrato neutro sem ler hostname.
- Shell fixo significa estrutura institucional compartilhada, nao eliminar o bloco institucional na regiao institution (ADR-053).
- Domain Config + aculta_domain_config atualmente aplicam overrides para block.block; isso NAO demonstra compatibilidade automatica de overrides de Layout Builder/core.entity_view_display por dominio.
- Preservar plugins e cache metadata dos blocos Core, Views e contrib; compor a apresentacao via SDC.
- Previas reais nunca podem exibir dados privados sem acesso ou em cache publico. Ausencia de contexto/permissionamento implica previa indisponivel.
- Alteracoes em pagamentos, checkout, dados privados e configuracao ativa dependem de escopo e testes proprios.
- Nenhuma nova PR/branch; trabalho em dev com commits atomicos, preservando trabalho paralelo e seguindo AGENTS.md.

## Homologacao bloqueante

Inventario de blocos e regioes sem orfaos; dez purposes, shell, claro/escuro, mobile-first e zoom; testes de Layout Builder, SDC/UI Patterns, permissao de previa, cache cruzado, acesso, import/export de configuracoes em staging, rollback. Teste nao executado = DEFERRED, nao PASS. Sem corte antes do aceite.
