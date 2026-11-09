# Seletor de layout de container (aculta420_v2)

Onde fica: Aparência > aculta420_v2 > subaba **Layout de container**. Um bloco por purpose.

Configuração salva em `theme_get_setting('aculta_purpose_container')`, validada por
`Drupal\aculta420_v2\Layout\PurposeContainerLayout`.

## Campos por purpose

| Campo | Valores | Padrão | Observação |
| --- | --- | --- | --- |
| Modo | `default` (Padrão), `full` (Largura total), `narrow` (Estreito) | `default` | Define a largura do `.layout-page` e do conteúdo. |
| Estilo | `boxed` (Caixa), `unboxed` (Sem caixa) | `boxed` | Vale para todos os modos. Caixa usa fundo, borda e faixa `--purpose-sub-2`. |
| Largura do container (px) | 960 a 1600 | 1200 | Usada no modo Padrão. |
| Largura estreita (px) | 600 a 900 | 750 | Usada no modo Estreito. |

Valores gravados com os modos antigos são convertidos: `boxed` vira `default`, `unboxed` vira `full`,
`contained` e `max_full_width` viram `default`.

## O que cada valor mapeia

Ao renderizar, `SurfaceSettingsHooks::preprocessHtml` emite no `<html>`:

- `data-purpose-layout`: modo (`default`, `full`, `narrow`).
- `data-purpose-style`: estilo (`boxed`, `unboxed`). Só é emitido quando o purpose tem configuração de layout.
- `--aculta-container-width` e `--aculta-narrow-container-width`: larguras em px.
- `--aculta-studio-light-sub-2` e `--aculta-studio-dark-sub-2`: cor de faixa do purpose `account`, para as caixas que acompanham o Studio.

No CSS (`src/styles/layout.css`):

- `html[data-purpose-layout="default"]` e `narrow` limitam `.layout-page` a `--aculta-container-width`.
- `html[data-purpose-layout="narrow"] .layout-page__content` limita o conteúdo a `--aculta-narrow-container-width`, centralizado.
- `html[data-purpose-layout="full"]` remove o limite de `.layout-page` e de nós e componentes de conteúdo (publicação da Revista, verbete, evento, aula).
- `html[data-purpose-style="boxed"] .layout-page__content` aplica caixa: fundo, borda e faixa superior `--purpose-sub-2`.

## Exceções

- **Conta (`account`)**: layout fixo pelo código, igual ao Fórum: modo Padrão (1200 px, estreito de
  750 px) e estilo sem caixa. Não aparece no seletor e não tem entrada editável em
  `aculta_purpose_container`; valor salvo é ignorado. O purpose está em
  `PurposeContainerLayout::ALLOWED_PURPOSES`, então o `<html>` recebe `data-purpose-layout="default"`
  e as variáveis de largura em todas as páginas do domínio. A raiz do domínio é `/conta`
  (`system.site.page.front` da coleção do Domain), mas a variante de página segue a rota — Minha
  Conta usa `aside` no desktop — e nunca cai na variante `full` por ser front page.
  O CSS centraliza um único container para todas as páginas do purpose (`html[data-purpose="account"]`)
  e usa o mesmo `padding-inline` da barra institucional, para marca, ações, título e conteúdo
  começarem e terminarem na mesma linha. Páginas de leitura (`stack`, como Marley e Perfil) mantêm a
  medida estreita centralizada dentro desse container. O Studio continua usando a paleta do purpose
  `account` para as cores de destaque (`--aculta-studio-*`).
- **UI Patterns (biblioteca de componentes)**: rotas `ui_patterns_library.overview`, `.provider` e
  `.single` continuam fora do layout por purpose: o atributo `data-purpose-layout` não é aplicado e a
  variante é `full`.
- **Purposes com paleta mas sem layout**: a lista de paleta de cores (`aculta_purpose_palette`) ainda
  inclui `account`. Isso é independente do layout.
- **Páginas fora de purpose**: sem atributo de layout; vale o padrão do template e do CSS.
- **Escopo por página ou item**: não existe ainda. O layout é por purpose.

## Referências no código

- Formulário: `theme-settings.php`, bloco "Layout de container por purpose".
- Modelo e validação: `src/Layout/PurposeContainerLayout.php`.
- Atributos no `<html>`: `src/Hook/SurfaceSettingsHooks.php`.
- Regras de largura e estilo: `src/styles/layout.css`.
- Testes: `tests/src/Unit/PurposeContainerLayoutUnitTest.php`, `tests/src/Kernel/ThemeSettingsContainerFormKernelTest.php`.
