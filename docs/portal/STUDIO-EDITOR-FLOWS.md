# ACULTA Studio — telas de criação, edição e revisão
**Versão:** 0.2.0-dev.1 · **Estado:** especificação UX/UI; nenhuma funcionalidade implementada por este texto.
**Documento pai:** [Conceito visual](STUDIO-VISUAL-CONCEPT.md). **Direção arquitetural:** [ADR-011](../decisions/ADR-011-aculta-kernels-studio.md).

## 1. Jornada centrada em conteúdo próprio
**Entrada padrão:** Área → “Minhas publicações” (ou o equivalente conforme o provider) → Criar/Continuar/Editar. Mesmo roles com acesso amplo começam no seu espaço pessoal; “Equipe”, “Fila de revisão”, “Moderação” e “Todos autorizados” são contextos separados. Um editor que abre conteúdo de outra pessoa deve ver “Editando conteúdo de [autor]” e um link de volta para a fila/equipe; ações continuam condicionadas a Entity Access e workflow.

Fluxo nominal: **Minhas publicações → Criar publicação → preencher → Salvar rascunho → Pré-visualizar → Enviar para revisão (quando disponível e autorizado) → publicação (somente quem puder)**. Não pressupor que todo workflow contenha todos os estados. “Salvar” jamais deve significar automaticamente “Publicar”. Em edição de publicado, informar claramente o impacto da revisão e a publicação da nova versão. Os verbos variam conforme a transição real.

## 2. Padrão de criação — desktop
```text
ACULTA / Estúdio                                       [Conta]
Revista  /  Minhas publicações  /  Nova publicação

Nova publicação                     [Pré-visualizar] [Salvar rascunho]
Crie conteúdo para a Revista        Estado: Não salvo

┌────────────────────────────────────────────┬───────────────────────┐
│ CONTEÚDO                                   │ PUBLICAÇÃO            │
│ Título [______________________________]    │ Estado atual          │
│ Resumo [______________________________]    │ Categoria*            │
│ Editor CKEditor 5 [...................]     │ Imagem de destaque*   │
│ Mídia e documentos via integrações reais   │ SEO / opções avançadas│
│ Campos específicos da área ...             │ Ações permitidas      │
└────────────────────────────────────────────┴───────────────────────┘
*Somente se o provider expuser esse campo e a pessoa puder editá-lo.
```
Coluna principal flexível (aprox. 65–75%), painel de publicação secundário (25–35%) e opcionalmente sticky dentro da região do Studio, sem cobrir cabeçalhos, teclado ou mensagens. Campos de texto não devem ficar estreitos. Evitar duplicar botões nas duas colunas. Ao rolar, barra de ações discreta pode permanecer acessível, mas não deve reduzir significativamente a área de escrita. Headings editoriais preservam identidade ACULTA; labels e ajudas são de interface.

## 3. Padrão de criação — mobile
```text
‹ Minhas publicações            Nova publicação
Revista · Rascunho não salvo
Título [...............................]
Resumo [...............................]
Conteúdo
[ CKEditor 5 responsivo .................]
[ Inserir mídia ▾ ]  [ Mais opções ▾ ]
Configurações da publicação  [Expandir]
Categoria, imagem, metadados, SEO conforme provider
─────────────────────────────────────────
[Salvar rascunho]   [Mais ações ▾]
```
Uma coluna, labels sempre visíveis, inputs >= 16px para evitar zoom involuntário em iOS, alvos primários preferencialmente 44px, espaçamento suficiente para o teclado virtual. Organizar configurações de menor frequência em disclosure/accordion com validações acessíveis; campos obrigatórios e avisos não podem ficar ocultos após erro. O editor não ultrapassa viewport; barra do CKEditor deve quebrar/rolar de forma acessível e não depender de hover. Barra de ação sticky inferior é **opcional** e deve acomodar safe-area, teclado e não disputar espaço com navegação fixa.

## 4. Padrão de edição
Abertura por item pessoal (ou por fila explicitamente rotulada). Cabeçalho: título, estado humano, indicação de autor, data de modificação e, se disponível no provider, última revisão e mensagem de revisão. CTA principal conforme permissão e workflow: Continuar / Salvar alteração / Enviar para revisão / Publicar nova revisão. Exibir separadamente “Ver publicado”, “Pré-visualizar alterações” e “Histórico de revisões”, somente se disponíveis e autorizados. Em revisão de terceiros, mostrar o contexto e autoria; jamais fingir que a pessoa é dona.

**Rascunho:** retorno à lista pessoal, feedback de salvamento; não presumir autosave. **Em revisão:** se o provider não permitir edição, apresentar somente leitura e ações autorizadas. **Publicado:** avisar se alteração cria revisão pendente, substitui versão pública ou exige aprovação conforme workflow. **Arquivado:** restaurar apenas com transição permitida.

## 5. Telas e variantes obrigatórias
| Tela | Objetivo | Ação central | Estado de exceção |
| --- | --- | --- | --- |
| Meu painel da área | trabalho próprio em primeiro plano | Criar ou Continuar | primeiro item ausente |
| Criar | inserir conteúdo com campos do provider | Salvar rascunho, se permitido | validação e permissão |
| Editar | retomar trabalho e revisões | Salvar/Enviar conforme workflow | alteração concorrente |
| Pré-visualizar | conferir experiência final sem publicar | Voltar a editar | mídias não disponíveis |
| Histórico de revisões | inspecionar versões autorizadas | Ver/Comparar se suportado | revisão sem acesso |
| Fila de revisão | contexto de equipe distinto do pessoal | Abrir revisão | fila vazia |
| Moderação | avaliar, aprovar, rejeitar ou solicitar mudanças | Transição permitida | conflito/status mudou |
| Biblioteca de mídia | localizar ou associar recursos | Selecionar recurso autorizado | upload falhou |
| Confirmação de ação destrutiva | evitar exclusão acidental | Confirmar explicitamente | item vinculado/sem acesso |

Não inventar rotas ou APIs para completar esta matriz: cada ação abre a rota/formulário existente do dono. Onde não houver suporte real, marcar “planejado”, esconder CTA ou apresentar estado honesto, não mock funcional em produção.

## 6. Campos e integrações por área
| Área | Conteúdo próprio em foco | Criar/editar | Painéis opcionais conforme provider |
| --- | --- | --- | --- |
| Revista | Minhas publicações | Form API do bundle `revista` + CKEditor 5 | categoria, mídia via Gallery/Image, SEO, workflow |
| Fórum | Meus tópicos e minhas contribuições em seções distintas | formulários reais do `aculta_forum` | categoria/tópico, contexto, anexos, moderação |
| Wiki | Minhas contribuições; verbetes sob minha responsabilidade se aplicável | editor do provider Wiki | fontes, propostas, comparação de revisões |
| Cursos | Meus cursos criados/geridos; turmas autorizadas separadas de matrícula | LMS/Group e formulários do dono | currículo, aulas, materiais, visibilidade de turma |
| Galerias | Minhas galerias/álbuns | `aculta_gallery` + Media | seleção, upload via `aculta_image`, organização |
| Projetos/Biblioteca/Eventos futuros | propriedade ou responsabilidade definida pelo dono | somente onde houver provider ativo | anexos e workflows próprios |

**Reutilização:** `aculta_user` apresenta identidade/autor; `aculta_image` processa mídia; `aculta_document_viewer` cuida de prévias compatíveis; `aculta_messenger` mantém notificações; Fórum/Galeria são subkernels independentes. O Studio não cria tabelas espelho, upload paralelo, workflows próprios nem edição sem acesso do dono. CKEditor 5 é ferramenta de texto rico onde o dono já o emprega; não forçar em títulos, respostas curtas ou campos estruturados.

## 7. Estados, feedback e erros
- **Salvo:** confirmação acessível via status live, com distinção entre rascunho/revisão/publicação. **Não salvo:** indicação clara; se não houver detecção segura de alterações, não fingir status ou autosave.
- **Validação:** resumo vinculado a campos, foco no primeiro campo inválido, preservar entradas e mídia válida sem exposição de dados.
- **Conexão perdida/AJAX falhou:** fallback de navegação e formulário nativo, preservar alterações quando tecnicamente possível, não garantir persistência sem mecanismo real.
- **Conflito de revisão:** não sobrescrever silenciosamente; usar mecanismo de revisão/concorrência do provider, com recuperação contextual.
- **Sem permissão:** não renderizar ação e validar no servidor; ao perder acesso durante edição, mensagem genérica sem vazamento de conteúdo.
- **Ação destrutiva:** diálogo explícito e confirmação de consequência, inclusive vínculos, quando conhecido. Nunca excluir Media compartilhada implicitamente.
- **Preview:** deve respeitar acesso, propósito do domínio e proteção de rascunhos; não liberar URL pública inadvertidamente.

## 8. Contrato de implementação e qualidade
O provider fornece render arrays de Form API, links/autorização por ação, estados reais de workflow, informação cacheável e metadados seguros. O `aculta_studio` coordena navegação e semântica, o ACULTA420 implementa composição/SDC e tokens light/dark. Proibido usar Twig para consultar storage, access ou serviço; proibido reconstruir campos manualmente no tema.

**Aceite:** testes com autor comum, editor, moderador e docente, incluindo conteúdo próprio e de terceiros, no domínio de conta e nos purposes dos donos; cada perfil enxerga apenas seu conteúdo na vista “Meus”; alternância consciente para trabalho de equipe; cada CTA opera apenas onde autorizado; revisão/publicação nunca contorna workflow; layout de formulário funciona em 320/360/390/768/1280 px, com 200% de zoom, teclado virtual, leitor de tela, dark/light, sem JS e com falha de rede; upload/mídia não duplica recursos. Adicionar snapshots/regressões em `tests/browser/aculta-studio/` e atualizar READMEs após implementação.

## 9. Backlog visual priorizado
1. Wireframes validados com dados reais da Revista; ação principal e “Minhas publicações”.
2. Shell responsivo de edição e agrupamento de campos (sem alterar os widgets funcionais).
3. Estados de salvamento, prévia e revisão com texto claro e acessível.
4. Padronizar integração dos formulários de Fórum, Wiki, LMS e Galerias quando providers estiverem prontos.
5. Revisar escopo equipe/moderação, permissões cross-domain e comportamento móvel em Runtime real.

Este documento **não** altera banco, roles, fluxos existentes nem autoriza exclusões; é especificação para execução em etapas.

## Planejamento visual específico — Revista

A proposta mobile-first detalhada para a experiência de post, criação e edição da Revista está em [STUDIO-REVISTA-EDITOR-VISUAL.md](STUDIO-REVISTA-EDITOR-VISUAL.md). O documento detalha o shell CKEditor 5, configurações progressivas, prévia, fluxos editoriais, a prioridade de “Minhas publicações”, separação de fila de equipe e os gates de acessibilidade. **É planejamento; nenhuma tela foi implementada nesta entrega.**

## Sequenciamento aprovado — GANJALAB E-0.1 (2026-10-22)

Priorizar o GanjaLab editorial em `conta.aculta.*/studio/ganjalab` enquanto a homologação G420-C2-T aguarda consumidores reais de LMS e ACULTA420. E-A inventaria rotas/forms e remove divergências documentais; E-B consolida “Meus conteúdos” com autorização/paginação/estado por revisão; E-C integra criação, rascunho, edição, prévia e submissão pelo workflow do módulo dono; E-D aplica somente SDC/UI Suite com fallback HTTP e edição no celular. A fila Wiki e experiências de outros donos só entram após providers especializados e testes de isolamento. Separar meus itens, equipe e moderação também nos modelos de autorização, nunca só em abas visuais. Ver roadmap do submódulo Studio.

## E-A.1 — primeira entrega de simplicidade

A home GanjaLab passou a oferecer a pergunta “O que vamos criar hoje?” e cartões com as ações reais de criação já permitidas, via `StudioPresenter` e Core SDC do ACULTA420. O formulário de autoria ainda pode abrir no domínio do módulo dono; isso é etapa intermediária, não editor integrado pronto. O caminho de volta, 'Meu trabalho', navegação por tarefas em dispositivos pequenos e criação/edição inline ficam para E-A.2/E-B/E-C após auditoria de URLs, grants e Form API. Inventário detalhado no roadmap do Studio.

## E-B.1 — Meu trabalho em implementação

Na área Revista, a navegação passa a iniciar em `Meu trabalho → Meus conteúdos`, filtrando por autor antes de paginar. O fluxo básico de criar permanece ligado à rota real do módulo dono. Filtros por estado ainda têm contagem/paginação parcial por serem resolvidos depois da busca de revisões autorizadas; não considerar o painel finalizado. Próximo passo: provider com semântica de revisões e acesso, seguido por cartões SDC mobile-first e continuidade de rascunhos.
