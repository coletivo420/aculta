# WE SATIVA — V-0.4-C: microtarefas, criação, editor e prévia

**Versão:** WES-UX 0.4-C · **Data:** 2026-10-22 · **Status:** contrato visual documentado, não implementado.
**Cobertura:** V05, V06, V07 e V08. **Base:** [V-0.4-B](WE-SATIVA-V-0.4-B-ENTRY-DASHBOARD-WORK.md) · [12 estruturas](WE-SATIVA-VISUAL-SCREENS-0.4.md).

## Constatações verificadas no código

- `config/sync/editor.editor.wiki.yml` **já declara CKEditor 5** para o formato `wiki`: negrito, itálico, código, links, listas, citação, headings H2–H6, code block, Drupal Media e source editing; `image_upload.status: false`. Não alegar ausência do editor; conferir sua execução e o acesso ao text format por role na fase Runtime.
- `core.entity_form_display.node.wiki_entry.default.yml` já possui `title`, `field_wiki_summary`, `body`, `field_wiki_categories`, `field_wiki_image`, `field_wiki_references` (Link), `field_wiki_related` e campos técnicos. O visual WE SATIVA simplificará a apresentação sem duplicar os campos ou criar outro editor.
- `workflows.workflow.wiki_editorial.yml`: `draft → review → published`, com `review_to_draft`, `create_new_draft` e transições autorizadas. `email_confirmed` envia, **content_editor publica**. Primeiro verbete exige moderação.
- `wiki-entry` SDC oferece slots de artigo e ações, e `context-header` é dono do H1 da página pública: evitar dois títulos e controles duplicados.

## V05 — microtarefas guiadas

**Papel visual:** contribuição em 2 etapas: **Escrever → Revisar e enviar**. Um objetivo por tela, sem a complexidade de um form Node completo.

1. Cabeçalho contextual: voltar ao verbete, título simples da tarefa, nome seguro do verbete e indicação de etapa textual.
2. Conteúdo existente: mostrar trecho de referência quando acessível; preservar estrutura sem permitir que texto de terceiro seja atualizado por POST.
3. Formulário específico: `Corrigir` (texto/justificativa), `Fonte` (link/título/contexto), `Melhorar` (trecho proposto), `Imagem` (Media existente + alt/crédito/licença conforme política).
4. Pré-envio: resumo lado a lado em desktop e empilhado em mobile, com `Antes`/`Depois`, justificativa e confirmação de moderação.
5. Ações: `Voltar e ajustar`, `Enviar para revisão`; enviar não equivale a publicar. Não permitir envio sem provider de proposta moderável para Node alheio.

**Estados:** inicial, campos com erro, fonte inválida, sem permissão, versão-base ultrapassada, upload negado, envio concluído, erro recuperável, reenvio idempotente. O usuário deve conseguir voltar mantendo o texto enquanto o formulário existir; **não prometer autosave**.

**Componente:** Form API com wrapper de aparência Core SDC/UI Suite. Só extrair novo SDC se histórias e consumidores reais demonstrarem lacuna. AJAX é melhoria progressiva, HTTP sem JS funciona.

## V06 — criação de verbete

**Primeira dobra mobile:** `Novo verbete` (H1) + orientação `Comece com título, síntese e fontes`. Organização proposta:
- **Essencial:** título e síntese, com busca de duplicatas não bloqueante e resultados acessíveis (backend autorizado).
- **Conteúdo:** `body` CKEditor5 com formato `wiki` autorizado e toolbar responsiva.
- **Referências:** links e títulos, repetição via campo Drupal; indicar o que torna fonte verificável.
- **Complementos:** categorias, imagem e relacionados em disclosure visual; revelar campos com erro de validação.
- **Ações:** `Salvar rascunho` versus `Enviar para revisão`, apresentadas separadamente e só quando as transições reais existirem.

O primeiro verbete de toda conta `email_confirmed` vai à moderação. A confirmação do envio usa o retorno real do servidor; sem publicação automática.

## V07 — editor completo

**Objetivo:** uma superfície de escrita desimpedida, não uma área administrativa. Coluna de leitura em mobile; desktop com corpo dominante e painel de metadados **secundário e opcional** (não sidebar rígida).

**Ordem semântica:** cabeçalho da tarefa → status oficial → título → resumo → corpo editor → referências → mídia → categorias e relacionados → ações. Campos de autoria, domínio, URL e moderação são apresentados **somente se a pessoa tiver capacidade efetiva**; atributos ocultos visualmente não são substitutos de autorização.

**Toolbar:** reproduzir o conjunto real do `editor.editor.wiki.yml`, com agrupamento responsivo sem remover recursos permitidos. `drupalMedia` usa Media e permissões existentes; **não habilitar upload direto de imagem**, pois `image_upload.status: false`.

**Estados:** rascunho não publicado, revisão pendente, publicado com nova revisão separada, erro de formato, perda de permissão, conflito de versões, conteúdo longo, mídia indisponível. Não permitir que o próprio layout esconda erro de campo em disclosure fechado.

## V08 — pré-visualização

**Princípio:** `Prévia — ainda não publicado` inequívoco. Exibir título, resumo, corpo filtrado, mídias e referências como apareceriam na Wiki, conforme Core preview realmente autorizado. Distanciar ações `Voltar a editar`, `Salvar rascunho` e `Enviar para revisão`; **não usar Publicar para email_confirmed**.

**Navegação:** back seguro para formulário e preservação do estado por Form API; não simular URL pública de revisão não publicada. Moderação posterior por content_editor usa transições existentes; rascunho não sobrescreve a versão pública.

## Mapeamento responsivo

| Largura | Comportamento |
| --- | --- |
| 320/360/390px | uma coluna, labels permanentes, campos ≥16px, botões em bloco, original/proposto empilhados, nenhum overflow horizontal |
| 768px | mais respiro e prévia com largura de leitura estável; metadata ainda abaixo do corpo |
| 1024/1280/1440px | corpo prioritário ~65–75% da área útil; metadados em painel lateral opcional; before/after pode usar duas colunas |
| Zoom 200/400%, teclado, sem JS | reflow sem perder ação, foco e erros visíveis, toolbar navegável, ordem linear intacta |

**Tema:** SDC Core + UI Patterns/UI Styles e tokens semânticos; light/dark mudam contraste/superfícies, não geometria. Institution Bar e Domain Header multidomínio **sempre mantidos**.

## Stories e testes de aceite planejados

| ID | Cenário |
| --- | --- |
| WES-C-01 | título/síntese validam com mensagens acessíveis |
| WES-C-02 | duplicata sugerida sem bloquear criação autorizada |
| WES-C-03 | formato Wiki CKEditor 5 coincide com exportado, sem upload direto |
| WES-C-04 | conta email_confirmed cria rascunho mas não publica |
| WES-C-05 | primeiro verbete passa a `review`; publicação só content_editor |
| WES-C-06 | proposta para Node alheio sem `update`: sem provider, nenhum POST habilitado |
| WES-C-07 | conflito de revisão apresenta resolução segura |
| WES-C-08 | preview não publica e não revela privado em cache |
| WES-C-09 | antes/depois legível com leitor de tela e 320px |
| WES-C-10 | campos com erro em disclosures são expostos |
| WES-C-11 | Media respeita licença, alt e acesso |
| WES-C-12 | no-JS/zoom/light-dark preservam fluxos e shell |

## Microfases seguintes

- **V-0.4-C1:** stories de criação e editor completo, usando Form API/CKEditor reais e testes de permissão.
- **V-0.4-C2:** prévia e estados de revisão com revision access e domínio.
- **V-0.4-C3:** microtarefa de fonte/correção **apenas após** ADR de propostas para verbetes de terceiros.
- **V-0.4-D:** fila editorial, notificações, confirmação, ajuda e mídia contextual.
- **V-0.4-R:** homologação visual real e testes WCAG por papel.

Nenhum recurso descrito foi habilitado por este documento. `Autosave Form` permanece no backlog futuro.
