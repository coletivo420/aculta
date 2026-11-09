# ACULTA420 V2 — P5: landing pages por purpose (estado e bloqueio)

**Data:** 2026-11-09 · **Estado:** PARCIAL — composição SDC da landing Links entregue no tema; Layout Builder por purpose **DEFERRED** (depende de conteúdo editorial e importação de configuração em ambiente isolado).

## 0. Entregue nesta fase

- O tema passou a sobrescrever `aculta-links-landing.html.twig` e compõe a landing com o layout SDC `aculta420_v2:layout-section` (slot `header` para a descrição e slot `content` para os links).
- Os dados, o acesso e o cache continuam no `aculta_linkweed`; o tema só apresenta.
- Verificado nos shells clássico e v2: HTTP 200 no host Links, 1 H1, 1 `<main>` e `.layout-section` presente.
- O `LinksLandingController` não foi alterado; a variante de nó com `field_domain_source` continua funcionando.

## 1. O que o código faz hoje

- Cada purpose tem um host e um caminho de entrada em `DomainSurfaceMap::FRONT_PATHS` (`/apoio`, `/wiki`, `/cursos`, `/loja`, `/discussoes`, `/redes-sociais`, `/eventos`); `main` e `magazine` servem a raiz.
- A raiz do host é resolvida pelo Portal (Domain + `aculta_domain_config`), não pelo tema. O tema recebe `aculta_purpose` e `domain_presentation` e não compara hostname.
- O piloto Links usa `LinksLandingController`: se houver um nó `page` com `field_domain_source` do domínio, renderiza esse nó no view mode `full`; caso contrário, monta a landing gerada a partir dos links sociais (`aculta_linkweed.social_links`).
- Nenhum dos 95 displays do Config Sync tem Layout Builder habilitado (`scripts/audit-aculta420-v2-inventory.php`). O `ui_patterns_layouts` 2.0.23 descobre os seis layouts SDC do tema, mas a composição por display ainda não foi habilitada.
- A ponte `aculta_domain_config` aplica overrides de configuração de `block.block.*` por domínio. Ela **não** aplica overrides de `core.entity_view_display` nem de `layout_builder__layout`; essa compatibilidade não está demonstrada.

## 2. Por que não foi executado agora

1. A composição de cada landing é decisão editorial (quais seções, em que ordem, com quais blocos e conteúdo real). Criar conteúdo fictício violaria a regra das prévias e do projeto.
2. Habilitar Layout Builder em um display altera `core.entity_view_display.*` no Config Sync. A importação exige ambiente isolado, teste de renderização e rollback; não se executa `drush cim` no Runtime de desenvolvimento com drift.
3. No caso do Links, a rota atual é atendida por controller. Layout Builder só se aplica a um display de entidade; é preciso antes definir se a landing Links será um nó `page` com `field_domain_source` (composição por display) ou uma composição própria do controller.

## 3. Mecanismo recomendado (quando o bloqueio for removido)

**Fonte explícita:** um nó `page` por purpose, com `field_domain_source` apontando para o domínio e `layout_builder` habilitado no display `full` desse bundle. O Portal já usa esse padrão no `LinksLandingController` para a variante de nó.

Fluxo por purpose:

1. Autor cria/edita o nó de landing no MAIN, com o `field_domain_source` do purpose.
2. O display `node.page.full` (ou um view mode dedicado, por exemplo `landing`) tem Layout Builder habilitado com `allow_custom: false`.
3. A composição usa os layouts SDC (`layout-section`, `layout-two-columns`, `layout-grid`, `layout-narrow`, `layout-full`) e blocos existentes, sem duplicar plugins.
4. O resolver do Portal entrega ao tema apenas o render array do nó; o tema continua sem regra de domínio.
5. Cache: o render array do nó mantém tags/contextos do Domain; a landing não é cacheada como bloco estático.

**Se o nó não existir**, a rota continua no fallback atual (landing gerada), sem erro. O piloto Links mantém o rodapé social e as URLs existentes.

**Por que não usar Domain Config para o display:** a ponte atual opera sobre `block.block.*`. Estender para `core.entity_view_display` exigiria um adaptador novo, com testes próprios; só faz sentido se o padrão de nó por domínio não bastar. Preferir a solução que usa as APIs nativas de Domain e de Layout Builder.

## 4. Critérios de aceite do piloto Links

- Landing Links responde 200 no host do purpose, com o mesmo rodapé social e links existentes.
- Um único H1 e um único `<main>` (corrigido no P3).
- Composição visível em claro/escuro, 320/390/768/1024/1440 px, zoom 200%, sem rolagem horizontal.
- Cache aquecido no MAIN não aparece no host Links e vice-versa.
- Override de bloco por domínio continua funcionando; rollback restaura a landing anterior.

## 5. Próximos passos concretos

1. Definir com o responsável o conteúdo de cada landing (especialmente Links).
2. Preparar o view mode/display e a configuração de Layout Builder em ambiente isolado.
3. Importar, testar e só então avaliar a expansão para os outros nove purposes.
