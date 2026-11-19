# Prompt de Execução: Sistema de Emojis Fluent no CKEditor 5 (Abordagem Híbrida 420)

Você é um agente sênior Drupal 12 / CKEditor 5 especialista no ACULTA.
Sua missão é implementar o sistema de emojis do CKEditor 5 conforme especificado no plano arquitetural em `docs/portal/CKEDITOR5-FLUENT-EMOJI-PLANO.md`.

---

## CONTEXTO E REGRAS MANDATÓRIAS
1. **Regras do Projeto:** Leia `AGENTS.md` e siga estritamente os padrões Drupal 12 (`docs/portal/DRUPAL-12-STANDARDS.md`).
2. **Abordagem Híbrida:**
   - O CKEditor 5 e o banco de dados armazenam **caracteres Unicode nativos**. Não insira tags HTML diretamente pelo editor no banco.
   - Padrão de tom de pele: todo emoji com tom de pele usa **tom pardo** (Fitzpatrick Tipo 4 / *Medium*, modificador Unicode `🏽` `\u{1F3FD}`).
   - Front-end: um filtro de texto Drupal (`Drupal\aculta_editor\Plugin\Filter\FluentEmojiFilter`) renderiza os glifos como SVGs Coloridos através do componente SDC `reaction-emoji` do tema `aculta420_v2`.
3. **Escopo dos Arquivos:** Alterações restritas a `web/modules/custom/aculta_portal/modules/aculta_editor/`, `web/themes/custom/aculta420_v2/assets/icons/fluent-emoji/` e configurações de formato de texto em `config/sync/`.

---

## TAREFAS DE EXECUÇÃO

### Fase 1: Assets e Catálogo
1. A partir de `web/libraries/fluentui-emoji/assets/`, verifique e copie os SVGs coloridos necessários para `web/themes/custom/aculta420_v2/assets/icons/fluent-emoji/color/`, garantindo que gestos/mãos utilizem a variante `Medium/Color` (pele parda).
2. Crie o catálogo de metadados em `web/modules/custom/aculta_portal/modules/aculta_editor/js/ckeditor5/fluent-emoji-catalog.js` (ou JSON equivalente) contendo:
   - `id`: identificador de máquina (ex: `herb`, `raised_fist_medium`, `fire`).
   - `name`: rótulo legível em português (ex: "Erva", "Punho Erguido (Pardo)", "Fogo").
   - `glyph`: o caractere Unicode nativo exato (ex: `🌿`, `✊🏽`, `🔥`).
   - `category`: categoria (`cultivo`, `vibe`, `saude`, `direitos`).
   - `keywords`: lista de palavras-chave para busca (incluindo termos canábicos, antiproibicionistas e gírias).
   - `svg`: caminho relativo ou SVG inline para pré-visualização no seletor.

### Fase 2: Plugin CKEditor 5 em `aculta_editor`
1. Crie o script `web/modules/custom/aculta_portal/modules/aculta_editor/js/ckeditor5/aculta-fluent-emoji.js`:
   - Construído sobre `window.CKEditor5.dll` (utilizando `Plugin`, `createDropdown`, `DropdownButtonView`, `ViewModel`).
   - Implemente um painel de seleção com busca em tempo real e visualização em grade com ícones coloridos.
   - Ao selecionar um emoji, insira o caractere Unicode no modelo de documento.
2. Registre a biblioteca em `web/modules/custom/aculta_portal/modules/aculta_editor/aculta_editor.libraries.yml` (`ckeditor5.fluent_emoji`).
3. Declare o plugin em `web/modules/custom/aculta_portal/modules/aculta_editor/aculta_editor.ckeditor5.yml` vinculando o item de toolbar `acultaFluentEmoji`.

### Fase 3: Filtro de Renderização Drupal (`FluentEmojiFilter`)
1. Crie o plugin `Drupal\aculta_editor\Plugin\Filter\FluentEmojiFilter` em `web/modules/custom/aculta_portal/modules/aculta_editor/src/Plugin/Filter/FluentEmojiFilter.php`:
   - Anotação `#[Filter]` com tipo `FilterInterface::TYPE_TRANSFORM_REVERSIBLE`.
   - Faz o replace dos glifos Unicode mapeados pelas marcações do componente `reaction-emoji`, garantindo `aria-label` e acessibilidade.
   - Trate adequadamente sequências ZWJ (`❤️‍🔥`, `😮‍💨`, `😶‍🌫️`) e modificadores de tom de pele (`\u{1F3FD}`).

### Fase 4: Formatos de Texto e Ativação
1. Atualize as configurações em `config/sync/`:
   - Formatos: `usuario_aculta`, `revista_editorial` e `wiki`.
   - Adicione o botão `acultaFluentEmoji` à toolbar em `editor.editor.<formato>.yml`.
   - Habilite o filtro `fluent_emoji` em `filter.format.<formato>.yml`.

### Fase 5: Testes e Validação
1. Crie teste unitário `web/modules/custom/aculta_portal/modules/aculta_editor/tests/src/Unit/FluentEmojiFilterTest.php` cobrindo conversão de texto com glifos simples, glifos com modificador de pele e glifos compostos ZWJ.
2. Execute os gates:
   - `php scripts/validate-aculta-portal-drupal12.php`
   - `php scripts/validate-project-status-index.php`
   - Execução da suíte PHPUnit unitária do filtro.
3. Faça commit atômico no padrão ACULTA e push em `origin/dev`.
