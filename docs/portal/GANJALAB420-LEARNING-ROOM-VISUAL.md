# GanjaLab420 — Sala de Aprendizagem (Learning Room)
**Versão visual:** G420-UX 0.4.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento, não implementado.
**Relacionados:** [Jornada GanjaLab420](GANJALAB420-LEARNING-UX.md) · [Roadmap LMS](../modules/ACULTA-LMS-ROADMAP.md) · [Integração LMS](LMS-INTEGRATION-CONTRACTS.md) · [Identidade](GANJALAB-BRANDING.md).

## Propósito
Criar um espaço mobile-first para estudar, ler, assistir, fazer atividades, consultar materiais e participar de conversas contextuais. Prioridade: **continuar de onde parou** e **completar a tarefa atual**, sem aparência de administração. O nome técnico **`aculta_lms` fica preservado**; Drupal LMS, Group e seus providers são fontes canônicas de matrículas, progresso, atividades, prazos, notas e autorizações. A experiência pública é GanjaLab420; ACULTA420 detém o visual. Não implementar outro LMS, módulo, gradebook ou storage.

## Modelo de navegação
Dashboard `/ganjalab420` → Meu curso autorizado → Sala (atividade/aula permitida) → anterior/próxima **permitida** → retorno ao curso. URLs reais e acessíveis sem JS vêm das rotas/providers nativos; não inventar neste planejamento novos paths para aula.

- **Mobile 320–767px:** cabeçalho curto com voltar ao curso, título truncado de forma acessível; progresso real contextual; botão “Conteúdo do curso” revela índice (accordion/offcanvas acessível); aula em **uma coluna**; guias contextuais “Aula”, “Materiais” e “Discussão” quando há conteúdo; rodapé com anterior/próxima, sem botão falso de conclusão.
- **Tablet 768–1023px:** índice recolhível, reader amplo, ações ao alcance.
- **Desktop ≥1024px:** índice curricular lateral (~25–32%) e reader central (~68–75%), leitura com largura máxima confortável; índice recolhível, posição da aula atual sempre perceptível; não prender scroll do leitor em container estreito.
- Não duplicar a navegação global nem criar barra inferior separada se já houver equivalente. Histórico/back/forward, foco e `aria-live` em transições AJAX; fallback HTTP normal e respeito a `prefers-reduced-motion`.

### Wireframe mobile
```text
‹ Meu curso                      [Mais ⋯]
GanjaLab420 / Curso: Redução de danos
Progresso real do curso (quando disponível)
[Conteúdo do curso ▾]
Módulo 1 · Aula 2 de 8
História e contexto
[Leitor de conteúdo: vídeo / texto / PDF / H5P]
[Aula] [Materiais] [Discussão] (só quando aplicável)
[← Anterior]           [Próxima →]
```

### Wireframe desktop
```text
GanjaLab420 / Meus cursos / Curso
┌────────────────────────────┬───────────────────────────────────┐
│ Índice curricular          │ Módulo 1 · Aula 2                │
│ ▾ Módulo 1                 │ Título: História e contexto      │
│  ✓ Boas-vindas             │ Leitor (texto/vídeo/PDF/...)     │
│  ▶ História [atual]        │ Materiais e discussão contextual │
│  ○ Reflexão               │ ← Anterior              Próxima →│
│ ▸ Módulo 2                 │                                   │
└────────────────────────────┴───────────────────────────────────┘
```

## 1. Leitor universal — formatos e comportamentos

| Conteúdo | Composição | Responsável funcional |
| --- | --- | --- |
| Texto/CKEditor renderizado | tipografia editorial, headings, links, imagens responsivas; sem editor no estudante | render/filters existentes do Drupal LMS |
| Vídeo | player responsivo, legenda/transcrição quando disponíveis, fallback | Media/player autorizado e origem |
| PDF | viewer responsivo, abrir em nova aba ou fallback acessível; arquivos protegidos sob Group | `aculta_pdf_viewer` / Media/File |
| H5P | player/atividade somente quando integração estiver habilitada e homologada | `aculta_lms_h5p` e LMS |
| Questionário/entrega | formulários nativos, instruções, feedback e resultado real | LMS Answers/Webform Score/File Upload |
| Materiais | lista de recursos vinculados e autorizados, sem copiar anexos | Media/File e adapters |
| Discussão | Fórum contextual desta aula/turma, separado do fórum público | `aculta_forum_lms` e Group |

Nenhum widget de UI deve inferir permissão, estado de conclusão ou nota. A substituição de leitor por AJAX não pode duplicar submissão ou contornar Form API/CSRF.

## 2. Progresso e conclusão
Indicador de progresso **apenas se serviço LMS confiável retornar valores**; mostrar estado textual junto à barra, jamais porcentagem inventada. “Próxima” só navega à próxima aula autorizada; **não conclui** a anterior. Botões como “Marcar como concluída” só existem quando houver transição oficial, acesso e feedback do backend. Respeitar pré-requisitos, janelas de turma, tentativas, prazos e disponibilidade real. Não renderizar IDs/títulos de conteúdos bloqueados que o estudante não tem direito a conhecer.

## 3. Atividade e avaliação
Fluxo: instruções → preenchimento/upload autorizado → revisão do envio → submissão no Form API → confirmação servida pelo backend → resultado/feedback quando liberado. Mensagem de envio é distinta de “avaliado”. Avisar mudança de prazo pelo fuso da conta, sem expor respostas/notas de outras pessoas. Em erro de rede, preservar resposta quando viável, sem fingir entrega.

## 4. Discussões e materiais
Materiais complementares por aula e/ou curso: ícone + nome + tipo + acesso (visualizar/baixar) revalidado; PDF privado nunca recebe URL pública indevida. Conversa ligada à aula/turma via Fórum subkernel, com contexto de membership e moderação; nenhuma duplicação como tópico no fórum público. O conteúdo da discussão pode ser uma região sob a aula ou rota contextual real.

## 5. Estados especiais
- **Primeiro acesso:** acolhimento e orientação de como começar, sem tour bloqueador.
- **Aula indisponível:** explicar motivo seguro (pré-requisito, turma, não publicada) sem revelar conteúdo protegido.
- **Conteúdo vazio:** mensagem e navegação, sem player fictício.
- **Sem acesso/expirado:** acesso negado pelo servidor, não somente CSS/JS.
- **Falha de rede/player/PDF:** mensagem acessível, tentar novamente e fallback conforme recurso.
- **Sem JS:** navegar por URLs reais, ver conteúdo e submeter formulários nativos.
- **Em andamento e concluído:** estados reais de atividade, com texto além de cor/ícone.

## 6. SDC, tokens e acessibilidade
Componentes *candidatos* no ACULTA420: `learning-room-shell`, `learning-course-outline`, `learning-content-reader`, `learning-progress`, `learning-resource-list`, `learning-context-discussion`, `learning-lesson-actions`, `learning-feedback-state`. Reutilizar SDC e Bootstrap existentes antes de criar novos; temas não consultam Group/Entity Storage/serviços. Mesmo layout e tipografia nos modos claro/escuro, só tokens semânticos mudam. Contraste WCAG 2.2 AA, botões 44px quando viável, inputs ≥16px mobile, headings, foco, tab-order, legenda/transcrição, zoom 200%/reflow 400%, teclado e leitor de tela. O índice em offcanvas deve restaurar foco ao fechar e anunciar seleção.

## 7. Etapas internas da fase G420-UX 0.4
| Etapa | Trabalho | Gate |
| --- | --- | --- |
| 0.4-A | inventariar rotas renderizadas, Form API, LMS course outline, Group, formatos e permissões | mapa real, sem novas entidades |
| 0.4-B | wireframes acessíveis/light-dark em 320/390/768/1280px | revisão de desenho |
| 0.4-C | shell, índice e leitor SDC responsivos | sem JS e reflow |
| 0.4-D | vídeo/PDF/H5P, materiais, fórum contextual, submissões reais | access, CSRF e Group |
| 0.4-E | progresso, navegação e estados de erro | conclusão e cache reais |
| 0.4-F | browser/Kernel/Unit, acessibilidade e documentação | aceite Runtime, Group A/B e domínio |

**Matriz de homologação:** 320, 360, 390, 768, 1024, 1280 e 1440px; dark/light; estudante A/B; grupos A/B; roles docente/estudante, autenticação, conclusão/nota, H5P, PDF privado, voltar/avançar, erro/retry e teclado. A implementação respeitará gates existentes F6/F7/F8/F9/F10/F12/F15/F16/RC do roadmap LMS. Nenhum desses marcos é declarado concluído por este plano.

## Cinco modelos de conteúdo — evolução 0.4.1

O contrato de [modelos de aula (leitura, vídeo e H5P)](GANJALAB420-LESSON-MODELS-VISUAL.md) define cinco variantes do mesmo shell: **leitura, vídeo, atividade, documento/PDF e H5P interativo**. H5P tem exigências próprias de escala no mobile, teclado, tentativas, CSP, arquivos privados, fallback e homologação; não pode ser confundido com um embed de vídeo. O módulo interno continua `aculta_lms`, a integração H5P continua `aculta_lms_h5p`.

## Planejamento de atividades e avaliações — G420-UX 0.4.3

[Atividades e Avaliações](GANJALAB420-ACTIVITIES-ASSESSMENTS-VISUAL.md) apresenta questionários, envios de documentos, H5P avaliativo, feedback e estados reais do LMS, preservando Form API, notas, tentativas e Group. A experiência permanece dentro do shell compartilhado da Sala.
