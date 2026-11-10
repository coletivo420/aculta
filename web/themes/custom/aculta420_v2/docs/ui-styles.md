# UI Styles do tema V2 (Fase 4)

Estilos que o administrador Drupal pode aplicar pelo módulo `ui_styles`, sem
código. Cada opção aponta para uma variante que já existe no componente, com
tokens e contraste medidos. Nenhum estilo novo é criado nesta fase.

## 1. Decisões do responsável (2026-11-05)

- **Escopo:** botões (4 variantes), faixa de acento de cartão e estilos editoriais da Revista.
- **Quem aplica:** por padrão, somente o administrador Drupal. A exceção
  editorial aprovada pela ADR-055 está detalhada na seção 4.
- **Momento:** a F4 entra antes da RC. A RC depende dela.

## 2. Pré-requisitos

Todos são bloqueantes para a RC:

1. Botões sincronizados no servidor de testes, com o CSS legado de
   `src/styles/buttons.css` removido (botão com `--color-brand-highlight` ainda
   existe ali).
2. UI Skins publica as variáveis de identidade visual por Domain ID; aceite visual em claro/escuro e nos dez domínios permanece pendente.
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
- UI Styles não altera a barra institucional, o foco, os neutros nem a resolução
  Domain ID → skin ID. `validate-module-theme-boundary.php` continua obrigatório.
- Botões usam somente tokens semânticos `--button-*`; identidade visual vem de UI
  Skins e não há tokens de cor legados por purpose.
- As opções ficam disponíveis apenas para o papel de administrador Drupal.
- **Ajuste ADR-055 (2026-11-10):** o plugin oficial UI Styles CKEditor 5 de
  bloco fica disponível ao `content_editor` apenas no formato `revista_editorial`.
  Ele expõe as escolhas de superfície do SDC `surface` (tom, espaçamento,
  elevação e raio); o filtro editorial preserva somente os elementos com classe
  permitidos. As demais integrações de UI Styles continuam administrativas.
  Homologação no Runtime aguarda o cutover autorizado. UI Patterns CKEditor 5
  permanece sem configuração.

## 5. Validação e Capturas Visuais

- **Suíte de navegador:** Revista validada no Firefox ESR com submit pelo formulário UI Styles, classe no wrapper e no SDC de todos os quatro teasers, claro/escuro, 320/1440 px e sem overflow. A escolha global voltou a vazia e o banco não tem accent por nó. Manifest/capturas em `tests/browser/capturas/revista-accent/2026-11-06/`. Evidência do shell em `tests/browser/capturas/shell/2026-11-06/` (40 PNGs ignorados pelo Git): F2 segue pendente por contraste/overflow.
  - `ui_styles_light_verde-skunk_320px.png` e `1440px.png`
  - `ui_styles_dark_verde-skunk_320px.png` e `1440px.png`
  - `ui_styles_light_amarelo-lemon-haze_320px.png` e `1440px.png`
  - `ui_styles_dark_amarelo-lemon-haze_320px.png` e `1440px.png`
- **Evidência histórica:** Firefox ESR validou a paleta anterior em 2026-11-06. Essa rodada antecede o corte para UI Skins e não aprova o comportamento atual. O novo render aguarda UI Skins ativo, capturas e homologação nos dez domínios.
- **Gates:** `scripts/validate-aculta420-ui-styles.php` PASS na migração UI Skins; foundations PASS. Render SDC e validação browser de Runtime ainda não executados nesta retomada.
- **Teste Unitário:** `DomainSkinResolverTest.php` cobre resolução do mapeamento por Domain ID; comparação CSS em navegador segue pendente.

## 6. Pendências

- Captura autenticada no servidor de teste / Homelab real após sincronização do
  deployment, incluindo a escolha global da Revista.
