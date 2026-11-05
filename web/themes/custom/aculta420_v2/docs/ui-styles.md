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

### Regra de Precedência (Decisão do responsável em 2026-11-05)
Opção A adotada: A escolha do administrador via UI Styles (`card-cultural--accent-{green|yellow|red}`) tem precedência sobre o `accent` vindo do Portal/backend, garantindo flexibilidade editorial ao administrador Drupal.

## 4. Regras

- Cada opção de UI Styles aponta para uma classe que já existe no mapa de
  `PortalUiHooks::TOKENS` e no CSS do tema.
- UI Styles não altera a barra institucional, o foco, os neutros nem a lógica de
  purpose. `validate-module-theme-boundary.php` continua obrigatório.
- Botões usam só os tokens `--button-*`. Nunca `--purpose-*` nem
  `--color-brand-highlight`.
- As opções ficam disponíveis apenas para o papel de administrador Drupal.

## 5. Validação e Capturas Visuais

- **Suíte de navegador:** Executada em Firefox ESR visível (`DISPLAY=:0`), com artefatos salvos em `$SUITE_OUT` (`/home/piradopirata/aculta-suite-out/aculta420-v2-ui-styles-20261105/`):
  - `ui_styles_light_verde-skunk_320px.png` e `1440px.png`
  - `ui_styles_dark_verde-skunk_320px.png` e `1440px.png`
  - `ui_styles_light_amarelo-lemon-haze_320px.png` e `1440px.png`
  - `ui_styles_dark_amarelo-lemon-haze_320px.png` e `1440px.png`
- **Invariância e responsividade:** Zero rolagem horizontal comprovada em 320 CSS px em todos os cenários. Faixa de destaque de purpose com valor computado exato conforme esquema selecionado.
- **Gates:** `scripts/validate-aculta420-ui-styles.php` validando conformidade estrita entre catálogo YAML, variantes nos componentes CSS e mapa de tokens.
- **Teste Unitário:** `PurposePaletteResolutionUnitTest.php` cobrindo resolução de tokens e html_attributes nos dois modos.

## 6. Pendências

- Captura em servidor de teste / Homelab real após sincronização do deployment.
