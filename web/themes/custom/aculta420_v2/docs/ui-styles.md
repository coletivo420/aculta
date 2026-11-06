# UI Styles do tema V2 (Fase 4)

Estilos que o administrador Drupal pode aplicar pelo módulo `ui_styles`, sem
código. Cada opção aponta para uma variante que já existe no componente, com
tokens e contraste medidos. Nenhum estilo novo é criado nesta fase.

## 1. Decisões do responsável (2026-11-05)

- **Escopo:** botões (4 variantes), faixa de acento de cartão e cores por
  purpose.
- **Quem aplica:** somente o administrador Drupal. Editores comuns não
  escolhem estilo.
- **Momento:** a F4 entra antes da RC. A RC depende dela.

## 2. Pré-requisitos

Todos são bloqueantes para a RC:

1. Botões sincronizados no servidor de testes, com o CSS legado de
   `src/styles/buttons.css` removido (botão com `--color-brand-highlight` ainda
   existe ali).
2. Escolha de esquema por purpose ligada ao runtime. Hoje
   `aculta_purpose_palette` é salva, mas não é lida por nenhum código de
   renderização. Sem isso, a opção "cores por purpose" não tem efeito.
3. Servidor de testes na versão atual de `origin/dev`, confirmada por hash.

## 3. Inventário das opções

| Opção | Componente | Variantes existentes | Situação |
| --- | --- | --- | --- |
| Botão primário | `button` | `primary` | Implementado em `aculta420_v2.ui_styles.yml` (.button--primary) |
| Botão secundário | `button` | `secondary` | Implementado em `aculta420_v2.ui_styles.yml` (.button--secondary) |
| Botão contorno | `button` | `outline` | Implementado em `aculta420_v2.ui_styles.yml` (.button--outline) |
| Botão fantasma | `button` | `ghost` | Implementado em `aculta420_v2.ui_styles.yml` (.button--ghost) |
| Faixa de acento verde | `card-cultural` | `accent: green` | Implementado em `aculta420_v2.ui_styles.yml` (.card-cultural--accent-green) |
| Faixa de acento amarela | `card-cultural` | `accent: yellow` | Implementado em `aculta420_v2.ui_styles.yml` (.card-cultural--accent-yellow) |
| Faixa de acento vermelha | `card-cultural` | `accent: red` | Implementado em `aculta420_v2.ui_styles.yml` (.card-cultural--accent-red) |
| Cores por purpose | tema (faixa `--purpose-*`) | 10 esquemas | Implementado em `aculta420_v2.ui_styles.yml` (esquemas oficiais Skunk Family) |

`revista_accent` é aplicado uma única vez nas configurações do ACULTA420 V2 e
é consumido por todos os teasers da Revista; o preprocess aplica a classe ao
wrapper do node e ao SDC. O campo legado
`field_revista_accent` não participa mais do fluxo; permanece instalado para
evitar remoção destrutiva de conteúdo e será removido em uma migração própria,
com inventário e autorização.

### Regra de Precedência (decisão do responsável)
Opção A: a escolha global do administrador `revista_accent` tem precedência sobre o `accent` do Portal e é aplicada pelo preprocess como `revista-teaser--accent-{green|yellow|red}`. Não foi criada opção nova no catálogo UI Styles. `field_revista_accent` continua instalado, fora do fluxo e sem remoção destrutiva.

## 4. Regras

- Cada opção de UI Styles aponta para uma classe que já existe no mapa de
  `PortalUiHooks::TOKENS` e no CSS do tema.
- UI Styles não altera a barra institucional, o foco, os neutros nem a lógica de
  purpose. `validate-module-theme-boundary.php` continua obrigatório.
- Botões usam só os tokens `--button-*`. Nunca `--purpose-*` nem
  `--color-brand-highlight`.
- As opções ficam disponíveis apenas para o papel de administrador Drupal.

## 5. Validação e Capturas Visuais

- **Suíte de navegador:** Revista validada no Firefox ESR com submit pelo formulário UI Styles, classe no wrapper e no SDC de todos os quatro teasers, claro/escuro, 320/1440 px e sem overflow. A escolha global voltou a vazia e o banco não tem accent por nó. Manifest/capturas em `tests/browser/capturas/revista-accent/2026-11-06/`. Evidência do shell em `tests/browser/capturas/shell/2026-11-06/` (40 PNGs ignorados pelo Git): F2 segue pendente por contraste/overflow.
  - `ui_styles_light_verde-skunk_320px.png` e `1440px.png`
  - `ui_styles_dark_verde-skunk_320px.png` e `1440px.png`
  - `ui_styles_light_amarelo-lemon-haze_320px.png` e `1440px.png`
  - `ui_styles_dark_amarelo-lemon-haze_320px.png` e `1440px.png`
- **Invariância e responsividade:** gate de catálogo PASS (24 verificações). Runtime Firefox ESR verificou `data-purpose-scheme` e `--purpose-accent` nos dez purposes, claro/escuro e 320/1440; 358/360 checks passaram. Overflow do Fórum a 320 px mantém F3 pendente; capturas em `tests/browser/capturas/purpose-buttons/2026-11-06/`.
- **Gates:** `scripts/validate-aculta420-ui-styles.php` PASS nesta rodada; foundations PASS. O gate de render SDC e validação browser de runtime ainda não foram executados nesta retomada.
- **Teste Unitário:** `PurposePaletteResolutionUnitTest.php` cobrindo resolução de tokens e html_attributes nos dois modos.

## 6. Pendências

- Captura autenticada no servidor de teste / Homelab real após sincronização do
  deployment, incluindo a escolha global da Revista.
