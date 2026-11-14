# Plano de Implementação: Sistema de Emojis Fluent no CKEditor 5 & Filtro Híbrido

Data: 2026-11-09
Status: Aprovado pelo Responsável · Pronto para Execução
Escopo: `aculta_editor` (Plugin CKEditor 5 + Filtro Drupal) e `aculta420_v2` (SVGs Fluent e SDC `reaction-emoji`)

---

## 1. Visão Geral e Arquitetura

O sistema implementa um seletor nativo de emojis da biblioteca **Microsoft Fluent UI Emoji** (`web/libraries/fluentui-emoji/`) no **CKEditor 5** para formatos autorizados, com foco em uma curadoria canábica, de cultivo, medicinal, de direitos e antiproibicionista.

### Abordagem Híbrida (Storage Limpo + Visual Rico)
1. **No Editor / Banco de Dados:** O editor insere e grava o **caractere Unicode nativo** (ex: `🌿`, `🔥`, `✊🏽`, `⚖️`).
   - Mantém o HTML e o banco de dados leves, sem risco de corrupção ou incompatibilidade com filtros de segurança.
   - Textos permanecem 100% pesquisáveis e acessíveis para leitores de tela.
   - Preserva o copiar/colar em texto puro e compatibilidade com feeds e integrações.
2. **Padrão de Tom de Pele Parda:**
   - Todo emoji com variação de tom de pele (mãos, gestos e figuras humanas) adota por padrão o **tom de pele pardo** (Fitzpatrick Tipo 4 / *Medium* da biblioteca Fluent, modificador Unicode `🏽` `U+1F3FD`).
3. **No Front-end (Filtro Drupal `fluent_emoji`):**
   - Um filtro de processamento de texto converte os emojis suportados do catálogo em tags estruturadas do SDC `reaction-emoji` do tema `aculta420_v2`, renderizando os vetores oficiais coloridos (`Color`) da biblioteca da Microsoft.

---

## 2. Catálogo Curado 420 & Antiproibicionista

O catálogo reúne emojis organizados em 4 categorias, com termos de busca bilíngues (incluindo gírias e vocabulário comunitário):

| Categoria | ID / Nome | Glifo Unicode | SVG na biblioteca (`web/libraries/fluentui-emoji/assets/`) |
| :--- | :--- | :--- | :--- |
| **Cultivo & Natureza** | `herb` | 🌿 | `Herb/Color/herb_color.svg` |
| | `seedling` | 🌱 | `Seedling/Color/seedling_color.svg` |
| | `potted_plant` | 🪴 | `Potted plant/Color/potted_plant_color.svg` |
| | `leaf_fluttering_in_wind` | 🍃 | `Leaf fluttering in wind/Color/leaf_fluttering_in_wind_color.svg` |
| | `fallen_leaf` | 🍂 | `Fallen leaf/Color/fallen_leaf_color.svg` |
| | `four_leaf_clover` | 🍀 | `Four leaf clover/Color/four_leaf_clover_color.svg` |
| | `sun` | ☀️ | `Sun/Color/sun_color.svg` |
| | `droplet` | 💧 | `Droplet/Color/droplet_color.svg` |
| **Consumo & Vibe 420** | `fire` | 🔥 | `Fire/Color/fire_color.svg` |
| | `heart_on_fire` | ❤️‍🔥 | `Heart on fire/Color/heart_on_fire_color.svg` |
| | `face_exhaling` | 😮‍💨 | `Face exhaling/Color/face_exhaling_color.svg` |
| | `face_in_clouds` | 😶‍🌫️ | `Face in clouds/Color/face_in_clouds_color.svg` |
| | `dashing_away` | 💨 | `Dashing away/Color/dashing_away_color.svg` |
| | `melting_face` | 🫠 | `Melting face/Color/melting_face_color.svg` |
| | `relieved_face` | 😌 | `Relieved face/Color/relieved_face_color.svg` |
| | `smiling_face_with_sunglasses` | 😎 | `Smiling face with sunglasses/Color/smiling_face_with_sunglasses_color.svg` |
| | `zany_face` | 🤪 | `Zany face/Color/zany_face_color.svg` |
| | `sparkles` | ✨ | `Sparkles/Color/sparkles_color.svg` |
| **Medicinal, Saúde & Ciência** | `pill` | 💊 | `Pill/Color/pill_color.svg` |
| | `adhesive_bandage` | 🩹 | `Adhesive bandage/Color/adhesive_bandage_color.svg` |
| | `stethoscope` | 🩺 | `Stethoscope/Color/stethoscope_color.svg` |
| | `microscope` | 🔬 | `Microscope/Color/microscope_color.svg` |
| | `test_tube` | 🧪 | `Test tube/Color/test_tube_color.svg` |
| | `brain` | 🧠 | `Brain/Color/brain_color.svg` |
| | `green_heart` | 💚 | `Green heart/Color/green_heart_color.svg` |
| **Direitos, Justiça & Comunidade** | `balance_scale` | ⚖️ | `Balance scale/Color/balance_scale_color.svg` |
| | `peace_symbol` | ☮️ | `Peace symbol/Color/peace_symbol_color.svg` |
| | `raised_fist` (Pardo) | ✊🏽 | `Raised fist/Medium/Color/raised_fist_color_medium.svg` |
| | `victory_hand` (Pardo) | ✌🏽 | `Victory hand/Medium/Color/victory_hand_color_medium.svg` |
| | `folded_hands` (Pardo) | 🙏🏽 | `Folded hands/Medium/Color/folded_hands_color_medium.svg` |
| | `thumbs_up` (Pardo) | 👍🏽 | `Thumbs up/Medium/Color/thumbs_up_color_medium.svg` |
| | `clapping_hands` (Pardo) | 👏🏽 | `Clapping hands/Medium/Color/clapping_hands_color_medium.svg` |
| | `handshake` | 🤝 | `Handshake/Color/handshake_color.svg` |
| | `speaking_head` | 🗣️ | `Speaking head/Color/speaking_head_color.svg` |
| | `loudspeaker` | 📢 | `Loudspeaker/Color/loudspeaker_color.svg` |
| | `light_bulb` | 💡 | `Light bulb/Color/light_bulb_color.svg` |
| | `hundred_points` | 💯 | `Hundred points/Color/hundred_points_color.svg` |

---

## 3. Componentes e Arquivos a Serem Criados/Alterados

### A. Assets de Ícones no Tema (`web/themes/custom/aculta420_v2`)
- **Destino dos SVGs:** `web/themes/custom/aculta420_v2/assets/icons/fluent-emoji/color/`
  - Copiar os arquivos SVG listados acima a partir de `web/libraries/fluentui-emoji/assets/`.
  - Para os emojis com tom de pele, nomear com sufixo ou manter identificador claro (ex: `raised-fist-medium.svg`, `victory-hand-medium.svg`, `folded-hands-medium.svg`, `thumbs-up-medium.svg`, `clapping-hands-medium.svg`).

### B. Módulo `aculta_editor`
1. **Manifesto do Catálogo:**
   - Arquivo estático JSON ou JS em `web/modules/custom/aculta_portal/modules/aculta_editor/js/ckeditor5/fluent-emoji-catalog.json` (ou export direto) mapeando glifo, nome amigável em pt-BR, keywords para busca instantânea e caminho do SVG.
2. **Plugin CKEditor 5:**
   - `web/modules/custom/aculta_portal/modules/aculta_editor/js/ckeditor5/aculta-fluent-emoji.js`:
     - Registra o componente `acultaFluentEmoji` via `CKEditor5.dll`.
     - Cria um `DropdownView` com painel contendo barra de filtro/busca, abas de categorias e grade de botões com os SVGs Fluent em miniatura.
     - Ao clicar no emoji, executa `editor.execute('input', { text: emoji.glyph })` ou insere diretamente no model o caractere Unicode nativo.
3. **Declaração de Biblioteca e Plugin:**
   - `web/modules/custom/aculta_portal/modules/aculta_editor/aculta_editor.ckeditor5.yml`:
     - Registra plugin `acultaFluentEmoji` e botão `acultaFluentEmoji` na toolbar.
   - `web/modules/custom/aculta_portal/modules/aculta_editor/aculta_editor.libraries.yml`:
     - Define `ckeditor5.fluent_emoji` com dependências para `core/ckeditor5`.
4. **Filtro Drupal (`FluentEmojiFilter`):**
   - `web/modules/custom/aculta_portal/modules/aculta_editor/src/Plugin/Filter/FluentEmojiFilter.php`:
     - Filtro tipo `FilterInterface::TYPE_TRANSFORM_REVERSIBLE` ou `TYPE_TRANSFORM_IRREVERSIBLE`.
     - Substitui as ocorrências dos glifos Unicode mapeados pelo render array ou HTML seguro do componente SDC `reaction-emoji`.
     - Preserva atributos de acessibilidade (`role="img"`, `aria-label="<nome do emoji>"`).

### C. Configurações dos Formatos de Texto (`config/sync/`)
- Ativar o item de toolbar `acultaFluentEmoji` em:
  - `editor.editor.usuario_aculta.yml`
  - `editor.editor.revista_editorial.yml`
  - `editor.editor.wiki.yml`
- Ativar o filtro `fluent_emoji` em:
  - `filter.format.usuario_aculta.yml`
  - `filter.format.revista_editorial.yml`
  - `filter.format.wiki.yml`

---

## 4. Plano de Validação e Gates

1. **Testes Unitários:**
   - `FluentEmojiFilterTest.php` em `aculta_editor/tests/src/Unit/`: testa substituição correta de glifos Unicode comuns e compostos (incluindo tom de pele pardo e ZWJ como `❤️‍🔥` e `😮‍💨`).
2. **Validação de Sintaxe e Padrões:**
   - `php scripts/validate-aculta-portal-drupal12.php`
   - `php scripts/validate-project-status-index.php`
3. **Validação em Navegador:**
   - Execução no Firefox ESR (`DISPLAY=:0`) para verificar a abertura do dropdown no CKEditor 5 e inserção do emoji no editor.
