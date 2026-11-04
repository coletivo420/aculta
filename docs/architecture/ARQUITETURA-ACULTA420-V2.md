# Arquitetura ACULTA420 V2

## Objetivo

Estabelecer no tema `aculta420_v2` uma base SDC desacoplada, mobile-first, acessível e sem dependência de Bootstrap ou jQuery. O tema é desenvolvido no Drupal 11.5 e declara compatibilidade com `^11.5 || ^12`, com foco de API no Drupal 12.

## Camadas

```text
Core / módulos de domínio
  └─ Render API, Views, Menu API e templates de tema
       └─ SDC do aculta420_v2 (props/slots com schema obrigatório)
            ├─ tokens e temas de cor (preferência do SO ou data-theme explícito)
            └─ CSS e JS locais, mobile-first e progressivos

aculta_maps (dono de mapa, provedor, privacidade e coordenadas)
  └─ render arrays neutros / Leaflet
       └─ SDC map-popup como contrato visual do tema
```

O tema recebe dados e render arrays preparados pelo Core/módulos. Ele não consulta entidades, Domain ou purpose por conta própria e não decide acesso, cacheabilidade ou privacidade. Os módulos de domínio permanecem donos desses contratos.

## Tokens e temas de cor

`src/styles/tokens.css` define os tokens públicos de superfície, texto, marca, borda, ergonomia, tipografia, espaçamento e raios. O tema não consulta `prefers-color-scheme`: a preferência de aparência existente do Portal chega como `aculta_color_mode`, e `ColorModeHooks` a aplica como `data-theme="light"` ou `data-theme="dark"`. O valor legado `auto` recebido pelo tema usa claro; não ativa detecção do sistema. O seletor existente permanece no Portal.

Os tokens legados usados pelo shell existente são aliases dos novos tokens, para permitir migração gradual. Componentes V2 novos consomem os tokens canônicos. Componentes não criam cores de marca locais. Pares de texto/controle definidos para os temas claro e escuro foram verificados com contraste mínimo 4,5:1.

## Componentes SDC

Cada componente reside em `components/<nome>/` e mantém sua definição, Twig e CSS juntos; JavaScript só existe onde há interação.

| Componente | Papel |
| --- | --- |
| `button` | Link ou botão com variantes, estado desabilitado e alvo mínimo de 44 px |
| `badge` | Categoria/estado com variantes de marca, destaque e neutra |
| `card-cultural` | Teaser de atividade/evento, resumo ou ponto cultural; slots de imagem e ações |
| `audio-player` | Elemento HTML5 `<audio controls preload="none">`, sem script ou dependência externa |
| `hero-banner` | Abertura de página com mídia opcional e CTA; painel de texto opaco preserva contraste sobre imagens |
| `navigation-bar` | Menu progressivamente aprimorado com disclosure móvel, `aria-expanded`, `aria-controls`, Escape e foco restaurado |
| `pagination` | Links de pager com página atual e alvos de toque |
| `filter-chips` | Links filtráveis em faixa horizontal rolável e navegável por teclado |
| `status-messages` | Mensagens status/warning/error com regiões live apropriadas |
| `map-popup` | Cartão reduzido sem largura intrínseca que force overflow do popup Leaflet |

Schemas são obrigatórios via `enforce_prop_schemas: true`. Classes seguem BEM. JS usa Vanilla ES6+, `Drupal.behaviors` e `core/once`.

## Templates de integração

- `templates/navigation/pager.html.twig` adapta o pager do Core para `aculta420_v2:pagination`. O pager padrão não expõe o total de páginas ao template; por isso `total_pages` é opcional e não é inventado nessa integração.
- `templates/misc/status-messages.html.twig` mantém o wrapper `[data-drupal-messages]` e delega cada grupo tipado ao SDC `status-messages`, preservando markup seguro do Drupal.
- `templates/content/node--teaser.html.twig` compõe `card-cultural`, passa título/URL e mapeia campos renderizados conhecidos (`field_category`, datas/local e `field_summary`). Campos ausentes são opcionais; o teaser mostra somente os dados previstos no cartão.

Outros consumers devem chamar os componentes pelo ID namespaced Twig e usar `with_context = false`, passando dados neutros e render arrays apropriados em slots.

## Mapas: fronteira e evolução

`aculta_maps` já é dono do renderer Leaflet, do agrupamento local e da escolha atual de provedor; o padrão vigente é OpenStreetMap e Google Maps é opcional. O SDC `map-popup` fornece o contrato de apresentação compacto, mas não muda acesso, dados, coordenadas ou endpoint do módulo.

CartoDB Voyager/Dark Matter seria uma troca de provedor de tiles em relação ao padrão vigente. Só deve ser adotada após decisão arquitetural, revisão de termos/atribuição e implementação no módulo `aculta_maps`. Da mesma forma, um endpoint sob demanda de pontos culturais precisa de rota nomeada, acesso, minimização de dados, cacheabilidade, isolamento por purpose e proteção de precisão aproximada; um caminho público fixo não é contrato do tema. Marker clustering/chunked loading deve ser avaliado no owner `aculta_maps` com benchmark de volume e dispositivos móveis; o tema não carrega plugin próprio de mapas.

## Acessibilidade e responsividade

- CSS parte de viewport estreito, sem regras `max-width`; expansões usam breakpoints `min-width`.
- Controles definidos pelo tema têm alvo de pelo menos 44 px, foco visível, rótulo acessível e sem depender de cor para comunicar estado.
- Navegação continua disponível sem JavaScript; JS só ativa o disclosure móvel.
- Mídias preservam fluxo e limites do container; conteúdo longo pode quebrar sem criar rolagem horizontal de página.
- O player usa controles nativos, teclado e comportamento do navegador; não há autoplay.
- Contraste de texto para variantes implementadas foi calculado nos temas claro/escuro. A conformidade WCAG AA final ainda requer avaliação das páginas consumidoras, conteúdo real, zoom, teclado e leitores de tela.

## Validação

No checkout Drupal de desenvolvimento:

```sh
php vendor/drush/drush/drush.php cr
php vendor/drush/drush/drush.php scr scripts/validate-aculta420-v2-sdc.php
```

O segundo comando verifica descoberta SDC, schema estrito pelo Core, existência dos assets e renderização isolada dos dez componentes. Os templates do Core também devem ser verificados por smoke HTTP nas páginas que consomem pager, status messages e teasers; o gate isolado não substitui testes visuais ou auditoria assistiva.
