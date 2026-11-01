# ACULTA Studio Ganjalab — Atividades e Avaliações
**Versão visual:** G420-UX 0.4.3-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento; nenhuma interface implementada.
**Referências:** [Sala](GANJALAB-LEARNING-ROOM-VISUAL.md) · [Modelos de aula](GANJALAB-LESSON-MODELS-VISUAL.md) · [LMS](../modules/ACULTA-LMS-ROADMAP.md).

## Objetivo
O estudante deve compreender **o que fazer, até quando, se enviou e qual é o retorno**. O centro da experiência é a tarefa, não o gradebook. Usar dados, tentativas, notas e Form API do Drupal LMS; `aculta_lms` permanece o nome técnico. Group mantém contexto e prazo por turma; H5P usa `aculta_lms_h5p`; arquivos via Media/privado e viewer compartilhado; feedback/avisos via contratos existentes.

## Organização e entrada
- No dashboard ACULTA Studio Ganjalab, seção “Próximas atividades” mostra **somente pendências próprias e autorizadas** com data/fuso da conta, nunca contadores fictícios.
- Em “Atividades”, filtros **Pendentes / Entregues / Avaliadas / Todas** conforme estados realmente disponíveis; separar datas próximas e contexto do curso.
- Dentro da Sala, abrir atividade contextual e retornar à mesma aula/posição. O índice curricular apresenta estado textual além de ícone; não expor atividade bloqueada.
- Na experiência do educador, correções pertencem ao ACULTA Studio Marley, não ao painel de estudante.

## Modelo A — avaliação objetiva
**Mobile:** cabeçalho curso/módulo, título e instrução, prazo/tentativas quando existentes, uma questão ou grupo pequeno por seção, escolhas com alvos de toque, navegação entre questões acessível, revisão final e **Enviar respostas**. Evitar um wizard próprio quando Form API/atividade nativa controla fluxo; adaptar apenas apresentação. Após submeter, mostrar recibo confirmado pelo servidor e resultado apenas quando o provider liberar. Erro de rede nunca equivale a entrega.

```text
‹ Sala de aprendizagem
Módulo 2 · Atividade 1
Questionário: Fundamentos
[Prazo / tentativas se disponíveis]
Questão 1 de N
Enunciado ...
( ) Resposta A
( ) Resposta B
[Anterior] [Próxima questão]
[Revisar envio] → [Enviar respostas]
```

## Modelo B — entrega de texto e documentos
Instruções claras, campos do LMS, upload usando recurso File Upload/Media autorizado, extensão/tamanho conforme validação real, lista de arquivos anexados, confirmação antes de envio se operação for irreversível. PDF na entrega pode abrir no `aculta_pdf_viewer` com acesso revalidado. Não criar storage alternativo nem expor URL de arquivo privado. Se edição após envio for permitida, exibir regra e prazo reais.

## Modelo C — H5P interativo
H5P é quinta variante de aula e **também uma modalidade possível de avaliação**. Diferenciar “interação educacional” de “avaliação com nota oficial”. Player tem foco, redimensionamento dinâmico e fluxo de finalização próprio. Nunca transformar evento client-side `xAPI` em nota oficial sem contrato backend autenticado/validado; não presumir retomada ou autosave. Tipos não acessíveis exigem alternativa equivalente para uso obrigatório. Bibliotecas/CSP/Group devem cumprir gates previstos.

## Modelo D — feedback e resultados
Após submissão bem-sucedida: “Enviado”, timestamp real, referência/identificador de tentativa quando existente; “Aguardando avaliação” distinto de “Corrigido”. Ao receber avaliação, exibir nota/critério somente para o estudante autorizado e quando liberados; comentários pedagógicos e anexos com proteção Group. Evitar gamificação punitiva ou ranking de notas.

## Layout desktop
Coluna principal para perguntas/atividade (70–75% flexível) e painel contextual discreto (25–30%) contendo progresso das questões, prazo, tentativas e link a materiais autorizados. Em tablets/mobile colapsar o painel em disclosures, sem fragmentar o formulário ou perder valores em navegação AJAX. Não criar panel lateral se redundante para atividades simples.

## Estados essenciais
Não iniciada, em andamento (somente onde existe estado persistido), enviada, aguardando avaliação, avaliada, prazo encerrado, indisponível e falha de envio. Indicar conflitos de tentativa e acesso revogado sem revelar respostas/notas alheias. Ação destrutiva exige confirmação real. Nunca imprimir nota ou estado fictício como persistido.

## Critérios de acessibilidade e segurança
WCAG 2.2 AA, suporte a teclado/leitor de tela e touch, labels visíveis, agrupamento fieldset/legend, erros vinculados aos campos, foco e aria-live na confirmação real. Zoom/reflow, 320/360/390/768/1024/1440px, dark/light, no-JS, envio com CSRF do Form API, tentativas concorrentes, estudante A/B, Group A/B, user/cache isolation. Não confiar em progresso ou score enviados pelo navegador.

## Microetapas futuras (somente planejamento)
| Subetapa | Entrega | Gate |
| --- | --- | --- |
| 0.4.3-A | inventário dos formulários/answer plugins/estados de tentativa e prazos | contratos reais |
| 0.4.3-B | wireframes de quiz, upload, H5P e feedback | 320px + desktop |
| 0.4.3-C | composição visual sobre render arrays nativos | sem storage/nota paralelos |
| 0.4.3-D | regressões H5P, Group, formulário, arquivos e rede | segurança/privacidade |
| 0.4.3-E | auditoria UX, acessibilidade e documentação | evidência Runtime |

**Não instalar novo módulo, renomear `aculta_lms` ou implementar workflows nesta etapa.**

## Refinamento de interface — avaliação guiada e confirmação (2026-10-22)

O protótipo mobile apresenta **uma questão por vez**, progresso local de navegação (“questão 2 de N”), alternativas em grupos semânticos, botões anterior/próxima e etapa explícita **Revisar envio**. A revisão lista respondidas/pendentes, oferece retorno a cada questão e só apresenta “Enviado” após resposta confirmada pelo LMS.

**Não confundir protótipo e recurso implementado:** o sequenciamento de perguntas, rascunho entre etapas e edição pré-envio são condicionados às capacidades do Form API/answer plugin existente. Não quebrar a submissão nativa em múltiplas telas sem garantia de preservação, validação, CSRF e tentativas. Progresso de navegação entre perguntas não é progresso curricular. Em perda de rede, não declarar respostas enviadas.

### Wireframe da navegação mobile
```text
‹ Sala · Questionário de fundamentos
QUESTÃO 1 DE N           Respondidas X/N
[Enunciado e instruções]
(o) Alternativa A
( ) Alternativa B
( ) Alternativa C
[Anterior]       [Próxima]
       ↓
Revisar antes de enviar
Questão 1: respondida     [Editar]
Questão 2: sem resposta   [Editar]
[Confirmar envio pelo formulário real]
       ↓
Recibo confirmado no backend: enviada / aguarda correção
```

Documentos anexados usam o serviço privado já contratado; H5P preserva seu próprio player e resultados reais. O feedback docente fica visível apenas no estado e autorização correspondentes. A interface não calcula notas, não cria gradebook, nem substitui `aculta_lms`.
