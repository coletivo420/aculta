# GanjaLab420 — Modelos de aula: leitura, vídeo e H5P
**Versão:** G420-UX 0.4.1-dev.1 · **Data:** 2026-10-22 · **Estado:** contrato visual; sem implementação.
**Ver:** [Sala de Aprendizagem](GANJALAB420-LEARNING-ROOM-VISUAL.md) · [Roadmap LMS](../modules/ACULTA-LMS-ROADMAP.md).

## Decisão de família de modelos

Cinco variantes convivem sob o **mesmo shell de Sala de Aprendizagem**: **leitura editorial**, **vídeo/multimídia**, **atividade de avaliação**, **documentos/PDF** e **H5P interativo**. H5P é um modelo próprio de experiência visual, mas **não presumir novo bundle ou storage**; o conteúdo segue pertencendo à integração existente `aculta_lms_h5p`/Drupal LMS. O shell é ACULTA420; dados, ações e permissões continuam nos donos.

## Modelo 1 — aula de leitura (mobile-first)
Aula de texto deve parecer uma boa matéria editorial, sem controles administrativos. Hierarquia: voltar ao curso; módulo e posição real; título; duração **somente se houver metadado real**; corpo renderizado pelo Drupal (CKEditor 5 apenas na autoria GanjaLab); elementos media responsivos; recursos complementares; discussão contextual; ação de conclusão **se suportada** e navegação anterior/próxima **permitida**.

```text
‹ Meu curso                   [Conteúdo do curso ▾]
Módulo 1 · Aula 2
História e contexto
[Imagem/vídeo introdutório opcional]
[Título interno e parágrafos editoriais]
[Citação / imagem acessível / referências]
[Materiais ▾]    [Discussão ▾]
[← Anterior]     [Próxima →]
```

**Mobile:** uma coluna, linha de texto confortável, imagens com texto alternativo, notas/links bem espaçados, índice sob demanda, botões fora da região de leitura quando possível. **Desktop:** índice à esquerda e conteúdo em reader central com `max-width` tipográfico; margens laterais não devem alongar demais os parágrafos. Sumário interno opcional para lições longas, com âncoras acessíveis. Scroll do corpo permanece nativo. Sem overlay sticky sobre texto/teclado.

## Modelo 2 — aula em vídeo

Compartilha cabeçalho/índice/rodapé do modelo de leitura. O player ocupa o topo da região de conteúdo, em proporção 16:9 quando apropriada ao recurso, sem altura fixa que corte mídias verticais. Depois do player: resumo, transcrição e legendas, recursos vinculados, discussão e navegação. No mobile, oferecer acesso à transcrição sem precisar reproduzir vídeo; fullscreen nativo, velocidade/legendas quando suportados pelo player; não presumir reprodução automática.

```text
Módulo 1 · Aula 3   História das políticas de drogas
[ PLAYER responsivo com controles acessíveis ]
[Resumo] [Transcrição] (se disponíveis)
[Material complementar]
[Discussão contextual]
[Anterior] [Próxima]
```

Vídeos privados respeitam token/origem e acesso atual, sem embed público acidental. Reprodução **não implica conclusão**: somente manager oficial LMS pode confirmá-la. Queda de conexão mantém navegação e transcrição/recurso alternativo quando existir.

## Modelo 3 — H5P interativo (tratamento dedicado)

H5P **não será tratado como vídeo comum ou embed genérico**. A sala deve reservar região própria, dimensionada dinamicamente, para jogos educativos, apresentações interativas, quizzes e demais tipos habilitados na biblioteca H5P. A integração é por `aculta_lms_h5p` quando ativa e homologada; seus desafios de segurança/CSP, bibliotecas, scripts, permissões, persistência e notas devem passar nos gates do LMS.

### Mobile
- Uma tarefa por vez; enunciado e controles completamente visíveis em 320–390px.
- Player pode exigir scroll vertical natural e dimensionamento automático; evitar iframe com altura rígida, cortes, scroll duplo e botões fora de tela.
- Áreas clicáveis e interações drag-and-drop devem ter equivalente acessível por teclado/touch quando o **tipo H5P** suportar; tipos sem acessibilidade comprovada não devem ser publicados como atividade obrigatória sem alternativa equivalente.
- Botão “Retomar” e feedback do exercício dependem de suporte real a estado de tentativa; **não prometer** autosave H5P, nota ou continuação se a API não os fornecer.
- Mudança de aula com interação em andamento requer alerta de perda de progresso quando não há salvamento confirmado.

### Estados
**Ainda não iniciado**, **em andamento** e **concluído** só quando disponíveis pelo provider; **resultado enviado** ≠ **resultado aprovado**; erro de inicialização, biblioteca indisponível e bloqueio por Group recebem fallback legível sem exposição de URLs/arquivos privados. Feedback pode ser exibido pelo próprio player ou adaptador autorizado, sem calcular nota pelo navegador.

### Segurança e integridade
Não conectar automaticamente eventos JavaScript H5P a notas oficiais sem um contrato confiável e verificações do backend. Revalidar usuário, course/class Group, Entity Access, tentativas e disponibilidade em cada ação de persistência. Respeitar políticas de CSP, bibliotecas H5P e arquivos privados. Discutir atividade H5P usa fórum contextual conforme `aculta_forum_lms`, não cria tópico público. H5P beta/integração incompleta deve permanecer recurso condicional até homologação.

### Desktop
Player em largura central adequada ao tipo (evitar expandir um questionário estreito a 100% de monitor); índice lateral recolhível; detalhes e recursos abaixo. A alternância para fullscreen segue a capacidade do tipo H5P e os padrões do navegador.

## Contrato de shell compartilhado

O leitor tem slots semânticos para `course_context`, `outline`, `lesson_header`, `content`, `resources`, `discussion`, `progress`, `actions` e `feedback`. Variantes de tipo não duplicam título, navegação, histórico e permissões. Candidatos SDC ACULTA420: `learning-text-lesson`, `learning-video-lesson`, `learning-h5p-lesson`; apenas visuais, sem consultas no Twig ou JavaScript. Tema controla espaçamento/cores por tokens e variantes dark/light com geometria idêntica.

## Gate visual e funcional específico

Testar 320/360/390/768/1024/1440px, reflow/zoom, teclado/touch, leitor de tela, legendas/transcrições quando houver, sem JS com fallback honesto, back/forward, longas leituras e PDFs/mídia; para H5P: bibliotecas/tipos concretos, escala de iframe, CSP, ausência de scroll duplo, foco e alternativa acessível, conclusão/notas oficiais, retomada só onde suportada, turma A/B, tentativa A/B, acesso revogado, rede interrompida. Não declarar H5P aprovado com screenshot estático; requer testes de interação autorizada no Runtime.

## Próximas etapas

1. G420-UX 0.4.1: wireframes de leitura e vídeo com conteúdo real de teste e variantes light/dark.
2. G420-UX 0.4.2: estudo de tipos H5P disponíveis, fallback, acessibilidade e integração do player.
3. G420-UX 0.4.3: atividades e avaliações Form API; envio/feedback/progresso.
4. G420-UX 0.4.4: leitor PDF, materiais e discussões.
5. G420-UX 0.4.5: regressões integradas.

**Nenhuma dessas etapas altera o módulo técnico `aculta_lms` ou implanta uma UI neste commit.**

## Refinamento de wireframes — leitura, vídeo e H5P (2026-10-22)

### Leitura: composição detalhada
**Mobile:** topo compacto “Meu curso”/índice, trilha Módulo/Aula, título editorial de duas ou mais linhas, imagem de abertura opcional, corpo de texto em coluna única, citações e referências, bloco de materiais autorizado e anterior/próxima. Não permitir que cabeçalho sticky ou toolbar encubra a leitura. **Desktop:** índice à esquerda, conteúdo de largura tipográfica controlada, mídias responsivas dentro do reader e materiais contextuais. CKEditor 5 é editor de autoria no GanjaLab, nunca aparece como editor para o estudante.

### Vídeo: composição detalhada
Player central responsivo (16:9 somente quando combinar com a mídia), sem autoplay, seguido de resumo, transcrição/legendas se fornecidas, materiais complementares, discussão contextual e navegação. Oferecer acesso a material textual sem obrigar reprodução e não derivar conclusão apenas de iniciar ou concluir playback. Mídias privadas devem preservar autorização e política do provider.

### H5P: exemplo de quiz
Uma aula com pergunta e alternativas exemplifica a **região de player H5P**, não um formulário próprio da Sala. O iframe/container deve dimensionar-se conforme a biblioteca e a tela, sem cortar controles ou criar scroll horizontal/duplo. As alternativas, validações, tentativas, pontuação e feedback são renderizados/controlados pelo H5P e a integração LMS autorizada, não pelo shell da Sala. Questões não acessíveis via teclado/leitor de tela exigem avaliação específica e alternativa pedagógica equivalente antes de se tornarem obrigatórias.

**Contrato comum:** curso/módulo/posição, leitor, materiais, discussão, anterior/próxima e estado real de conclusão compõem um shell único. Preservar as variantes light/dark, com geometria e hierarquia idênticas. Este refinamento é documentação, não novas funcionalidades.
