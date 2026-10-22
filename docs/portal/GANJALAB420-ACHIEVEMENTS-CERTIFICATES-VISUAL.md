# GanjaLab420 — Conquistas, Certificados e Histórico
**Versão visual:** G420-UX 0.7.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento, não implementado.
**Relacionados:** [Jornada](GANJALAB420-LEARNING-JOURNEY-VISUAL.md) · [Turmas](GANJALAB420-CLASSES-COMMUNITY-VISUAL.md) · [Roadmap LMS](../modules/ACULTA-LMS-ROADMAP.md).

## Objetivo
Celebrar a conclusão sem falsificar conquistas. Exibir **certificados realmente emitidos**, forma verificável de apresentá-los e histórico educacional autorizado. **Não implementar gradebook, pontuação, badge ou certificado paralelo**. Drupal LMS/`aculta_lms` são fontes de cursos e registros; certificados usam emissão, verificação, PDF e QR existentes (`aculta_qrcode` e `aculta_pdf_viewer` conforme contratos atuais); `aculta_game` fornece conquistas opcionais somente se houver provider pronto. O módulo técnico **continua `aculta_lms`**.

## Mapa das telas
- **Conquistas (início):** certificados emitidos, cursos concluídos e histórico pessoal, com estado vazio honesto.
- **Meus certificados:** cartões por curso com título, data real de emissão, visualização PDF e acesso à verificação, sujeitos a permissão/status.
- **Detalhe do certificado:** documento oficial, status de validade (incluindo eventual revogação conforme backend), ações seguras para abrir/baixar e URL pública de verificação quando existente; não publicar dados privados do aluno na interface.
- **Meu histórico:** lista cronológica ou por curso com matrícula, aulas/atividades concluídas e resultados liberados, sem confundir conclusão com certificado emitido.
- **Conquistas opcionais:** representações celebrativas somente de eventos reais fornecidos pelo `aculta_game`, sem ranking competitivo.

## Wireframe mobile
```text
‹ GanjaLab420
Conquistas e histórico
[Certificados emitidos] [Meu histórico]
Seu percurso merece reconhecimento.
[🏅 Certificado · Formação antiproibicionista]
    Emitido em data real · status atual
    [Ver certificado PDF] [Verificar autenticidade]
[Curso concluído sem certificado]
    Conclusão registrada; certificado não emitido
[Meu histórico →]
```

O cartão certificado tem nome do curso, data e estado **quando disponíveis**. Não colocar certificado como concluído a partir apenas de percentual de aulas. Estudante sem certificado vê uma mensagem neutra e link à Jornada ou aos cursos; não criar documento ilustrativo como se fosse real.

## Visual desktop
Coluna principal com cartões oficiais de certificados e histórico; painel auxiliar opcional com curso e próxima ação. Evitar “mural de troféus” com KPIs supérfluos. A identidade visual compartilha ACULTA420: selo/ícone de certificado, verde institucional e acentos botânicos moderados, layout idêntico nos modos claro/escuro. Preparar preview com página PDF oficial ou capa neutra apenas quando permitido.

## Distinções funcionais essenciais
| Estado | Significado na interface |
| --- | --- |
| Aula concluída | Estado de aula segundo o LMS |
| Atividade enviada | Entrega registrada, possivelmente sem avaliação |
| Curso concluído | Regra de conclusão oficial satisfeita |
| Certificado emitido | Há entidade/documento de certificado existente |
| Certificado revogado | Verificação aponta status de revogação; não apresentar como válido |
| Conquista opcional | Prova de integração real com `aculta_game` |

Progresso e emissão não devem ser calculados ou persistidos no tema. Emissão/revogação fica no fluxo autorizado de educadores GanjaLab, não na interface pessoal do estudante.

## Compartilhamento e verificação
Oferecer botão “Verificar autenticidade” usando **a rota pública de verificação já existente**; compartilhar somente URL de verificação aprovada, sem expor tokens privados de arquivo, links temporários ou informação além do contrato público. QR reutiliza kernel e fluxo de verificação, não um QR com dados pessoais brutos. PDF deve manter acesso e fallback de leitura. Diferenciar comprovante válido, revogado e não encontrado sem divulgar registros adicionais.

## Privacidade, UX e acessibilidade
Isolamento entre estudantes A/B, turmas A/B, histórico e certificados, com cache apropriado. Revalidar permissões em detalhes, downloads e QR. Eventual verificação pública revela exclusivamente campos aprovados pelo backend. Estado sem cursos e certificado não emitido; PDF indisponível; revogação; erro de verificação; dados parciais. 320/360/390/768/1024/1440px; teclado, leitor de tela, foco, zoom/reflow, WCAG 2.2 AA e modos light/dark. Não marcar status somente com cor ou ícone.

## Candidatos de SDC
`learning-achievement-overview`, `learning-certificate-card`, `learning-certificate-detail`, `learning-history-row`, `learning-achievements-empty-state`. Reutilizar componentes de documentação, PDF e cartões existentes antes de criar novos; tema recebe render arrays/dados autorizados e não consulta Entity Storage.

## Plano 0.7
| Etapa | Entrega | Gate |
| --- | --- | --- |
| 0.7-A | inventário de conclusão, entidades de certificado, emissão, QR e verificações existentes | fonte canônica e acesso |
| 0.7-B | wireframes mobile/desktop para conquistas, detalhe e histórico | identidade/UX |
| 0.7-C | componentes SDC reaproveitados, sem duplicar certificados | acessibilidade |
| 0.7-D | vínculos PDF/QR, estados revogados e histórico autorizado | privacidade + cache |
| 0.7-E | testes A/B, variações de emissão, claro/escuro e sem JS | evidências Runtime |

Este documento não modifica código, rotas, entidades ou nomes técnicos e não declara implementadas as telas planejadas.
