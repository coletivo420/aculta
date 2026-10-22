# GanjaLab420 — Consolidação Visual e Gates de Homologação
**Versão:** G420-UX 0.8.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento de consolidação; nenhuma implementação funcional declarada.

## Objetivo
Unificar visualmente as experiências de estudante do **GanjaLab420** sob o Design System **ACULTA420**, sem tocar na identidade técnica `aculta_lms`, nem replicar dados de LMS, Group, PDFs, imagens, mensagens ou Fórum. Os contratos anteriores permanecem fontes de verdade por tela.

## Contratos de experiência
| Experiência | Documento | Fonte e gate |
| --- | --- | --- |
| Dashboard / Meus Cursos | [Dashboard](GANJALAB420-DASHBOARD-VISUAL.md) | matrícula e próximo passo reais, ACL/cache por pessoa |
| Sala / Leitura / Vídeo / H5P | [Sala](GANJALAB420-LEARNING-ROOM-VISUAL.md) e [Modelos](GANJALAB420-LESSON-MODELS-VISUAL.md) | conteúdo acessível, sem avanço/conclusão fictícios |
| Atividades e avaliações | [Atividades](GANJALAB420-ACTIVITIES-ASSESSMENTS-VISUAL.md) | Form API, tentativas, resposta/nota reais |
| Materiais e PDF | [Biblioteca](GANJALAB420-MATERIALS-PDF-VISUAL.md) | Media/File/Group, PDF Viewer sem duplicação |
| Minha Jornada | [Jornada](GANJALAB420-LEARNING-JOURNEY-VISUAL.md) | progresso oficial, próximos passos |
| Turmas e Comunidade | [Turmas](GANJALAB420-CLASSES-COMMUNITY-VISUAL.md) | Group + Fórum contextual + Messenger + User |
| Certificados e histórico | [Conquistas](GANJALAB420-ACHIEVEMENTS-CERTIFICATES-VISUAL.md) | emissão/verificação/QR e histórico autorizados |

## Sistema visual transversal
- **Shell:** contexto de conta/GanjaLab420; breadcrumb curto, identidade do curso/turma e retorno consistente. Respeitar shell existente do ACULTA420; evitar barras de navegação duplicadas.
- **Navegação principal candidata:** Início, Meus Cursos, Atividades e Conquistas; Turmas e Materiais disponíveis no curso e em contexto, sem obrigar nova barra fixa. Somente montar itens autorizados e reais.
- **Ação primária:** uma por vista (“Continuar”, “Enviar”, “Voltar à aula”, “Abrir documento”), nunca fake submit ou ações sem backend. Navegar ≠ concluir e enviado ≠ avaliado.
- **Tipografia:** títulos curtos, reader de parágrafos com largura confortável, legendas transcritivas, texto de estado legível; tipografia e spacing por tokens existentes.
- **Cartões:** uma família para cursos, aulas, atividades, avisos, materiais, participantes e certificados, variando apenas detalhes semânticos. Reutilizar cartões de usuário `aculta_user` e fórum do subkernel.
- **Modos claro/escuro:** mesma estrutura, grid, ícones e posição de controles, apenas tokens semânticos e contraste adaptados.
- **H5P:** quinta variante da Sala, player e estados próprios, tipos auditados individualmente, altura responsiva, acesso Group, validação de tentativas/nota no backend; não inferir xAPI como nota válida sem contrato.
- **PDF:** viewer existente `aculta_pdf_viewer`; toolbar, zoom e pesquisa pertencem ao viewer. Não criar segunda biblioteca PDF.js.
- **Mensagens/avisos:** `aculta_messenger` e Fórum contextual são donos; sem feeds/inboxes paralelos.

## Breakpoints e QA de layout
| Largura | Conduta |
| --- | --- |
| 320–390px | uma coluna, índice de curso/turma colapsável, ações de toque, sem scroll horizontal |
| 391–767px | leitura confortável, menus compactos, cards flexíveis |
| 768–1023px | reader ampliado, sidebar opcional/recolhível |
| ≥1024px | leitor central com largura limitada e sidebar de índice quando útil |
| 1440px | não esticar linhas de texto, evitar vazios e densidade excessivos |

Testar 320, 360, 390, 768, 1024, 1280, 1440px; zoom 200/400%, toque/teclado/screen reader, safe areas, dark/light, reduced motion e sem JS. WCAG 2.2 AA: rótulos, heading hierarchy, foco restaurado após AJAX, erro associado ao campo, contraste, progresso com texto além de ícone/cor, legenda/transcrição quando fornecidas e alternativas para H5P/PDF que não atendam acessibilidade.

## Matriz obrigatória de segurança/integração
1. Estudante A vs B: nenhum vazamento de atividade, tentativa, nota, matrícula, histórico, certificado ou materiais privados.
2. Turma Group A vs B: sem mural, participantes, documentos ou prazo de turma cruzados.
3. Autenticado vs anônimo; docente no GanjaLab vs estudante no GanjaLab420; propósito de domínio `account`.
4. Sessão revogada, matrícula encerrada, pré-requisito indisponível, tentativa finalizada e recurso removido.
5. Cache contexts/tags em todas as listagens, blocos e contadores de usuário/Group; acesso antes de contagem/paginação.
6. AJAX e fallback HTTP: URL/back/forward, foco, CSRF, state de submissão e mensagens de erro.
7. LMS, H5P, PDF Viewer, Messenger, Fórum, aculta_user e Gallery: nenhuma duplicação de storage ou políticas.

## Governança e rastreabilidade
- Inventariar rotas, render arrays, providers existentes, componentes SDC, dados e estados antes de criar UI.
- Cada componente visual deve ter dono, dados necessários, access/cache, variante mobile/desktop, light/dark e cenários de erro documentados.
- **Evidência:** screenshots 320/390/768/1280 light/dark, relatório de acessibilidade, testes A/B, rotas e regressões dos providers. Não declarar PASS sem evidência do Runtime.
- Limites: especificação UX não aprova alteração funcional, criação de módulo, mudança de rota ou renomeação de `aculta_lms`.

## Sequência de implementação futura (não executada)
| Marco | Escopo | Gate |
| --- | --- | --- |
| 0.8-A | auditoria cruzada de todas as especificações, inventário técnico e SDC reutilizáveis | matriz sem lacunas críticas |
| 0.8-B | protótipo coeso de shell + navegação + estados globais | mobile, teclado, no-JS |
| 0.8-C | harmonizar reader, atividades/H5P, biblioteca/PDF, jornada e turma | dados/acesso reais |
| 0.8-D | integração de certificados e avisos | privados/emitidos verdadeiros |
| 0.8-E | gates completos e homologação controlada | evidências do servidor de desenvolvimento |

**Próximo passo recomendado após 0.8:** comando de execução incremental começando por inventário e componentes reutilizáveis, sem reabrir o redesign de cada tela. Neste commit, planejamento apenas.

## Resultado preliminar 0.8-A — auditoria cruzada de reutilização

[AUDITORIA CRUZADA: GanjaLab420 × ACULTA420](GANJALAB420-CROSS-VISUAL-AUDIT.md) registra os SDCs existentes, lacunas transversais, matriz de consumidores e regra **reutilizar → estender → criar padrão compartilhado**. Evidência estática concluída; Runtime/stories/browser e gate de homologação ainda pendentes. Novos padrões devem beneficiar outras áreas (Revista, Wiki, Fórum, Galeria, Conta e GanjaLab) antes de serem especializações LMS.
