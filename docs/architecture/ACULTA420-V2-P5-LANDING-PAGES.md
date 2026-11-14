# ACULTA420 V2 — P5: landing pages por purpose (estado e bloqueio)

**Data:** 2026-11-09 · **Estado:** piloto Links **entregue e validado** nos shells clássico e v2. As dez landings por purpose dependem de conteúdo editorial para cada purpose.

## 0. Entregue nesta fase

- O tema sobrescreve `aculta-links-landing.html.twig` e compõe a variante gerada com `aculta420_v2:layout-section`.
- A landing editorial do Links foi criada no desenvolvimento: nó `page` **635**, título **Links da ACULTA**, `field_domain_source = links_aculta_org`, alias `/redes-sociais`, publicado.
- Composição no Layout Builder em duas seções SDC (`ui_patterns:aculta420_v2:layout_section`):
  1. apresentação e chamada (campo `body`, formato `aculta_editorial`) + links sociais (campo `field_social_links`, Instagram real);
  2. apoio (`aculta_donation_quick`) + documentos institucionais (`views_block:aculta_documents-block_1`) + menu institucional (`system_menu_block:aculta-footer-institution`, com URLs absolutas para o MAIN).
- `node.page.full` tem `allow_custom: true`; o override por nó foi testado e comprovado via HTTP autenticado.
- Validação: 35 checks Playwright em 320/390/768/1024/1440 px, claro e escuro, shells clássico e v2; 1 H1, 1 `<main>`, sem rolagem horizontal, duas seções, Instagram, apoio e links absolutos para o MAIN.
- **Os dados editoriais (nó 635 e alias) vivem no banco do desenvolvimento, não no Git.** O conteúdo deve ser revisado pelo responsável antes de qualquer promoção.

## 1. O que o código faz hoje

- Cada purpose tem um host e um caminho de entrada em `DomainSurfaceMap::FRONT_PATHS` (`/apoio`, `/wiki`, `/cursos`, `/loja`, `/discussoes`, `/redes-sociais`, `/eventos`); `main` e `magazine` servem a raiz.
- A raiz do host é resolvida pelo Portal (Domain + `aculta_domain_config`), não pelo tema. O tema recebe `aculta_purpose` e `domain_presentation` e não compara hostname.
- O piloto Links usa `LinksLandingController`: se houver um nó `page` com `field_domain_source` do domínio, renderiza esse nó no view mode `full`; caso contrário, monta a landing gerada a partir dos links sociais (`aculta_linkweed.social_links`).
- O lote inicial do P4 habilitou Layout Builder em 22 displays. O `ui_patterns_layouts` 2.0.23 descobre os seis layouts SDC do tema.
- A ponte `aculta_domain_config` aplica overrides de configuração de `block.block.*` por domínio. Ela **não** aplica overrides de `core.entity_view_display` nem de `layout_builder__layout`; por isso a composição por landing usa um nó por purpose com override nativo no Layout Builder.

## 2. O que foi executado

1. O responsável aprovou as seções da landing Links: apresentação e chamada, links sociais, apoio e institucional/transparência.
2. O nó 635 foi criado publicado no host Links, com alias `/redes-sociais`, corpo no formato `aculta_editorial`, Instagram real no campo de links sociais e duas seções SDC no Layout Builder.
3. O `node.page.full` recebeu `allow_custom: true` e o override por nó foi comprovado por HTTP autenticado.
4. A landing foi validada nos shells clássico e v2, claro e escuro, em cinco larguras, sem overflow e com 1 H1/1 `<main>`.

## 3. Mecanismo para os demais purposes

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

1. Revisar o conteúdo editorial da landing Links (nó 635) com o responsável e publicar a redação final.
2. Repetir a mesma composição para os demais purposes, um por vez, com nó `page` + `field_domain_source` e override próprio no Layout Builder.
3. Manter o fallback do controller para purposes sem landing editorial.
