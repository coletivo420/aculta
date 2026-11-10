---
name: ui-styles
description: Define e integra escolhas de estilo reutilizáveis no UI Styles do ACULTA, com classes e tokens já existentes.
---

# UI Styles no ACULTA

Use para criar/revisar estilos editoriais reutilizáveis em `web/themes/custom/aculta420_v2/aculta420_v2.ui_styles.yml`. Para markup e contrato visual, use `sdc`; para CSS e tokens, `css`; para expor componente, `ui-patterns`.

## Escolha a abstração

- **Componente SDC:** markup semântico próprio, estados ou conteúdo composto, props/slots e comportamento que formam uma peça reutilizável.
- **Variante/prop do SDC:** muda um estado ou opção que pertence ao contrato daquele componente e não é um estilo genérico aplicado a alvos variados.
- **UI Style:** grupo de classes CSS independentes de markup específico, com opções documentadas e mutuamente exclusivas, configuráveis por editores nos alvos suportados.
- **Utilitário CSS:** regra pequena de implementação ou layout que não deve virar opção editorial pública. Não exponha cada classe como UI Style.

Não converta todos os SDCs em styles nem todos os styles em SDCs. Se a classe exige HTML específico, fica no componente; se é uma escolha visual independente e reutilizável em vários contextos, considere UI Style.

## Regras

1. Leia o `aculta420_v2.ui_styles.yml`, `docs/ui-styles.md`, `docs/architecture.md` seção 7 e o CSS que define as classes antes de editar.
2. Reuse token/classe existente. Não adicione paleta, escala, nome, preview ou dependência Bootstrap paralelos ao design system.
3. Agrupe opções alternativas no mesmo style plugin; se combinações podem coexistir, separe em grupos com responsabilidade diferente. Marque somente opções válidas para cada alvo.
4. Preserve `data-theme`, `data-purpose`, light/dark e responsividade do token CSS. UI Styles escolhe uma classe, mas quem define aparência é o tema.
5. Considere `previewed_as: hidden` quando o catálogo não puder representar a aparência no formulário; siga convenção local.
6. O export deste checkout habilita `ui_styles`, `ui_styles_block`, `ui_styles_layout_builder`, `ui_styles_ui_patterns` e `ui_styles_views`. Isso não prova estado no runtime: confirme plugin e alvo no código instalado.
7. Evite duplicar classes do SDC, classes de módulo ou CSS já incluído em biblioteca condicional.
8. O YAML atual contém `button`, `card_accent`, `revista_accent`, `purpose_palette`, `spacing_section`, `surface_style`, `surface_accent_style`, `surface_elevation_style` e `surface_radius_style`. Confira essas opções e o CSS antes de propor outra classe.
9. Mapeie superfície, espaçamento, borda/raio, elevação/sombra e cor por purpose ao CSS existente. Verifique claro/escuro, estados, alvos de bloco/layout/Views/Patterns e consistência das previews.
10. `ui_styles_page` e `ui_styles_library` não aparecem habilitados no export. `ui_styles_page` é o nome técnico documentado para integração global/page; confirme módulo, origem e comportamento no release instalado antes de usar.
11. Respeite permissões administrativas e evite opções de classes arbitrárias ou CSS livre.

## Validação

- `php scripts/validate-aculta420-ui-styles.php`.
- Rode schema SDC se também alterar contrato de componente.
- Verifique contraste e layout com `aculta-gates-por-escopo`; preview visual precisa ser executado nos modos e larguras aplicáveis.

## Referência sob demanda

Veja `references/decisao-de-abstracao.md` para critérios ACULTA e nomes observados. API/YAML deve ser conferido na documentação e no código da versão UI Styles efetivamente instalado.
