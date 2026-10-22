# GanjaLab420 — Leitor PDF e Biblioteca de Materiais
**Versão visual:** G420-UX 0.4.4-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento, não implementado.
**Referências:** [Sala de Aprendizagem](GANJALAB420-LEARNING-ROOM-VISUAL.md) · [Modelos de aula](GANJALAB420-LESSON-MODELS-VISUAL.md) · [Roadmap LMS](../modules/ACULTA-LMS-ROADMAP.md) · [Contratos PDF](PDF-VIEWER-INTEGRATION.md).

## Missão visual
Permitir que estudantes **leiam e consultem recursos de aula ou curso no celular**, retomem a aula e encontrem materiais sem navegar por gerenciadores de arquivos. Manter `aculta_lms` e os kernels atuais; PDF é servido por `aculta_pdf_viewer`, documentos por Media/File (inclusive Group/private), imagens por `aculta_image`, Gallery como subkernel compartilhado. Nenhuma biblioteca de arquivos ou upload paralelo.

## Três superfícies, uma mesma fonte
1. **Materiais desta aula:** região contextual acessível na Sala, próxima do corpo, com nome, tipo, descrição opcional e ação real “Visualizar”/“Baixar” conforme acesso.
2. **Biblioteca do curso:** lista ou cartões de recursos elegíveis a todo o curso, ordenados por seção/tipo conforme relações existentes; filtros locais/Views existentes, sem contagens falsas.
3. **Leitor de documento:** painel de leitura dedicado no mobile, visualizador integrado no desktop; ao fechar, retornar à aula e posição anterior sem perder respostas não enviadas.

Não pressupor que todo documento está associado a toda turma. Revalidar curso, turma, membership, arquivo e propósito de domínio em cada solicitação.

## Wireframe mobile: materiais
```text
‹ Sala de Aprendizagem
Materiais da aula
Fundamentos · 3 recursos (somente se contagem real)
[PDF] Guia de leitura        [Visualizar]
[Imagem] Infográfico         [Abrir]
[Link] Referência externa    [Visitar]
[Voltar à aula]
```

Cada linha tem alvo confortável, ícone acompanhado de nome/tipo e feedback de erro. Preservar acesso ao recurso a partir da Aula, da Biblioteca e da lista de entregas, sem duplicar objetos de Media.

## Wireframe mobile: leitor PDF
```text
‹ Voltar à aula          Guia de leitura    [Mais]
[PDF.js  — página e controles do viewer real]
[Controles: zoom, página, busca, download se permitido]
[Indicador de carregamento/erro acessível]
```

Usar **o componente real do `aculta_pdf_viewer`**, não reconstruir barra de ferramentas nem integrar uma segunda cópia de PDF.js. Mostrar ações suportadas pelo renderer, com controles que não cubram páginas ou teclado. Sem exigir download para ler PDF autorizado. Arquivo privado jamais usar link de objeto público, cache indevido ou URLs compartilháveis sem verificação. A persistência de página/zoom é opcional e depende de suporte real; não inventar “continuar leitura”.

## Desktop / tablet
Biblioteca em lista ou grade enxuta com tipos, agrupamentos contextuais e busca somente se implementável com API atual. Leitor PDF pode ocupar painel central, com índice da aula recolhível e botão claro “Voltar à aula”; não exibir simultaneamente barra global, sidebar e viewer que deixem texto ilegível. No tablet, optar por vista de leitor inteiro quando a área útil for pequena.

## Integrações
| Tipo | Viewer/comportamento | Condição |
| --- | --- | --- |
| PDF | `aculta_pdf_viewer` + PDF.js integrado | permissões Media/File/Group e política de derivados |
| Imagem | `aculta_image`/Media styles e alt | sem duplicar upload, com dimensões responsivas |
| Galeria | referências ao `aculta_gallery` | acesso e domínio reais; não clonar imagens |
| Vídeo | Media/player real, legenda/transcrição se disponíveis | privacidade e acessibilidade |
| H5P | `aculta_lms_h5p` como atividade/interatividade, não arquivo PDF | player/contexto autorizado, CSP |
| Links externos | HTML link com origem, alvo explícito e política de segurança | não tratar URL como arquivo protegido |
| Arquivo entregue pelo estudante | File Upload/Media privados do LMS | leitura só por participantes autorizados |

## Biblioteca: filtros propostos
Todos / Documentos / Vídeos / Imagens e Galerias / Links e Recursos Interativos **apenas para tipos existentes**; podem ser controles de filtro, não necessariamente novas rotas. Ordem padrão curricular ou por tipo confirmada pelo provider. Sem biblioteca paralela de referência. Em turmas diferentes, recursos privados e deadlines continuam isolados.

## Estados e acessibilidade
- Biblioteca vazia: “Ainda não há materiais disponíveis nesta aula/curso”, sem CTA de envio para estudante.
- Arquivo indisponível/acesso revogado: erro amigável, sem mostrar URL privada, título restrito ou detalhes de storage.
- PDF grande/carregamento demorado: progressão/placeholder; fallback abrir/baixar somente quando permitido.
- Erro de renderizador: expor alternativa segura, sem bloquear restante da aula.
- PDF inacessível a leitor de tela: indicar limitações e oferecer HTML/texto acessível quando disponível; PDFs publicados como materiais obrigatórios devem ter alternativa ou acessibilidade validada.
- 320/360/390/768/1024/1440px, dark/light, zoom 200–400%, teclado, foco, leitores de tela, no-JS, rede lenta, páginas longas, documentos privados, acesso A/B e Group A/B.

## Componentes ACULTA420 candidatos
`learning-resource-card`, `learning-resource-list`, `learning-material-filter`, `learning-document-shell`, `learning-resource-empty-state`. Reutilizar primeiro SDC e Bootstrap existentes, com tokens light/dark. A barra PDF é do kernel PDF Viewer, não do tema. Twig não consulta storage, permissões ou referências.

## Plano G420-UX 0.4.4
| Subetapa | Escopo | Gate |
| --- | --- | --- |
| 0.4.4-A | inventário de tipos, relacionamentos Media/Group, viewers e rotas | provar fontes e acesso |
| 0.4.4-B | wireframes mobile/desktop materiais + PDF | UX, 320px, light/dark |
| 0.4.4-C | compositores/SDC sobre render arrays nativos | sem storage paralelo |
| 0.4.4-D | integração autorizada PDF/Media/Gallery | política de privado e cache |
| 0.4.4-E | fallback, erros, acessibilidade e regressões | Runtime e Group A/B |

Este documento é **somente a especificação inicial**. Não instalar dependências, criar rotas, alterar módulos ou migrar arquivos nesta fase.

## Refinamento 0.4.4-B — wireframes e organização pedagógica (2026-10-22)

### Regra de organização: contexto, não formato
O estudante encontra o recurso **a partir do curso → módulo → aula** a que está vinculado, e a Biblioteca do Curso agrega os mesmos recursos sem duplicá-los. Uma mídia pode estar referenciada em diversas aulas, mas continua sendo o mesmo objeto canônico Media/File/Gallery. Preferir consultas por relações existentes e render arrays dos providers. Se faltar relação entre recurso e contexto, levantar lacuna na etapa 0.4.4-A; não criar tabela, campo ou taxonomia sem nova decisão arquitetural.

**Escopos:**
- **Aula:** “Materiais desta aula”, com referências, arquivos e links ligados à atividade.
- **Módulo:** coleção dos recursos das aulas do módulo, somente se o vínculo curricular puder ser identificado.
- **Curso:** índice agregado do conteúdo autorizado e deduplicado com contexto de origem visível.
- **Turma:** materiais exclusivos da turma aparecem somente para integrantes autorizados; não são promovidos para a biblioteca geral do curso.
- **Minha aprendizagem:** atalho contextual para materiais de cursos com matrícula/acesso atual; nunca pesquisa global de arquivos privados.

**Metadados visíveis quando reais:** título, tipo, breve descrição, aula/módulo de origem, indicação de material complementar/obrigatório **somente se existente**, idioma e acessibilidade disponível. Evitar tamanho/duração, quantidade, progresso e estado “lido” sem dados oficiais. Se um recurso for reutilizado em várias aulas, mostrar referências de origem ou agrupá-las em vez de multiplicar arquivos.

### Wireframe — biblioteca mobile
```text
‹ Meu curso                        [Buscar?]
Materiais de estudo
[Todos] [Documentos] [Vídeos] [Imagens]
Módulo 1 · Fundamentos
[PDF] Guia de redução de danos    [Visualizar]
[LINK] Referência externa         [Abrir]
Módulo 2 · Práticas
[VIDEO] Entrevista                [Assistir]
[GALERIA] Infográficos            [Explorar]
[H5P] Exercício interativo        [Abrir atividade]
```
Filtros e busca apenas quando os providers sustentarem esses atributos e permissões. H5P pode aparecer como recurso pedagógico navegável, **não** como arquivo downloadável nem duplicação de atividade.

### Wireframe — leitor PDF mobile
```text
‹ Voltar à aula   Guia de redução de danos    [Mais]
┌────────────────────────────────────────────┐
│ PDF.js via aculta_pdf_viewer               │
│ [página/zoom/busca/ações permitidas]       │
│ documento responsivo                      │
└────────────────────────────────────────────┘
[Voltar à aula e contexto anterior]
```
Não renderizar controles de PDF fictícios pelo Studio: o visualizador real gerencia zoom, páginas e busca. No desktop o viewer usa a área central com índice recolhível; mobile dá prioridade à página. Se houver resposta não enviada na aula, preservar/avisar sobre perda de edição ao navegar.

### Aceite específico de contexto
- Aula A e B podem referenciar a mesma Media sem copiar File; a Biblioteca lista a referência sem duplicar o arquivo.
- Estudante da Turma A não recebe documentos privados da Turma B, nem por listagem, URL de derivado, cache ou link direto.
- Remover uma referência curricular não apaga a mídia se outros contextos ainda a utilizam.
- Abrir e voltar ao PDF preserva URL, foco e contexto; sem JavaScript, links/rotas oficiais permanecem utilizáveis.
- Lista, busca e filtros devem aplicar **access antes da paginação/contagem**, com contextos e tags de cache corretos.
- Layout light/dark, leitor de tela, zoom/reflow e 320–1440px; PDFs sem acessibilidade requerem alternativa de conteúdo quando forem obrigatórios.

**Status:** planejamento visual e contrato de acesso; não implementado.

## Refinamento 0.4.4-C — duas explorações, mídia única (2026-10-22)

**Navegação principal por contexto pedagógico:** curso → módulo → aula → recursos associados. Em paralelo, um filtro **Por tipo** (documento/PDF, vídeo, imagens/Gallery, links e interatividade H5P) permite localizar material conhecido, sem criar cópias. Ambos são apresentações da mesma coleção autorizada. A UI deverá manter a origem curricular visível mesmo na vista Por tipo.

**Wireframe compacto:**
```text
GanjaLab420 / Curso / Biblioteca
[Por aula] [Por tipo]
POR AULA: Fundamentos → Aula 1 → Guia.pdf · Infográfico
POR TIPO: [Todos] [PDF] [Vídeo] [Galeria] [H5P]
          Guia.pdf — Fundamentos / Aula 1
```

Uma Media referenciada em diversas aulas continua **uma Media/File**, com associações curriculares independentes e visíveis. A biblioteca pode agregá-la em uma entrada e indicar aulas de origem; não prometer remoção global ao desvincular uma aula. Se o provider não oferecer relações contextuais suficientes, registrar lacuna técnica em 0.4.4-A antes de criar novos campos. **H5P deve abrir o player/atividade autorizada, nunca ser tratado como download de documento.**

O Course Builder do GanjaLab poderá futuramente oferecer **Adicionar material existente** consumindo os mesmos serviços, com permissão de editor e validação de Group. Reusar um recurso não altera automaticamente sua publicação ou visibilidade. Retornar do leitor PDF ou outro recurso à aula de origem, restaurando contexto, foco e histórico; cuidar de formulários não enviados.

**Aceite:** busca/filtros com autorização antes de contagem/paginação; deduplicação de File/Media; isolamento de materiais exclusivos por turma; preservação de cache contexts/tags por usuário/Group; leitor PDF único; mobile 320px, teclado e sem JS. Este refinamento permanece **somente documental**.
