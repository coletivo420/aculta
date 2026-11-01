# ACULTA Studio — Revista: interface visual de posts e edição

**Versão de planejamento:** 0.2.0-dev.2 · **Data:** 2026-10-22 · **Estado:** conceito aprovado para orientar implementação; não implementado.

**Contratos relacionados:** [Conceito visual do Studio](STUDIO-VISUAL-CONCEPT.md) · [Fluxos editoriais](STUDIO-EDITOR-FLOWS.md) · [Roadmap do Studio e Revista](STUDIO-MAGAZINE-ROADMAP.md) · [ADR de kernels](../decisions/ADR-011-aculta-kernels-studio.md).

## 1. Visão e princípios

**Editor editorial progressivo:** escrita simples por padrão; recursos avançados sob demanda. O foco principal da Revista é **Minhas publicações**; acesso a itens de terceiros por papéis privilegiados pertence a vistas separadas e identificadas (“Equipe”, “Fila de revisão”, “Moderação”). A experiência deve funcionar inteiramente no telefone; desktop expande a área de trabalho, sem retirar recursos mobile.

A interface combina identidade editorial ACULTA420 (tipografia expressiva, tokens institucionais, superfícies tipo papel) com controles de produtividade acessíveis. Não criar visual administrativo genérico, nem um novo design system. ACULTA420 é dono do layout/SDC; Studio articula navegação e presentation model; `aculta_magazine` e Drupal Core são donos de formulário, campos, acesso, estados e revisões.

## 2. Mapa das telas

| Tela | Primeiro foco | Ação principal | Secundárias |
| --- | --- | --- | --- |
| Minhas publicações | próprios rascunhos, revisão e publicados | Nova publicação | buscar, ordenar, filtrar e continuar |
| Nova publicação | título e texto | Salvar rascunho quando disponível | prévia, configurar, opções |
| Editar publicação | retomar texto e ver estado | salvar alteração/transição disponível | ver publicado, prévia, histórico |
| Configurações | categoria, capa, metadados expostos | Voltar ao texto | SEO e opções avançadas |
| Pré-visualizar | resultado real renderizado | Voltar a editar | avançar à conferência |
| Conferência e transição | efeitos do workflow | Enviar à revisão ou publicar **se autorizado** | salvar, corrigir pendências |
| Revisões | histórico autorizado | comparar/ver se suportado | retomar revisão permitida |
| Equipe/Moderação | conteúdo de terceiros explicitamente identificado | analisar item da fila | transições autorizadas |

Não representar recurso inexistente como funcional. Não acrescentar etapa obrigatória a salvamento que já seja válido. As ações reais respeitam estados e permissões do workflow configurado.

## 3. Mobile first — editor 320–767px

Hierarquia vertical:
1. Cabeçalho compacto: voltar a **Minhas publicações**, “Nova publicação” ou título abreviado, acesso a Prévia e ações secundárias.
2. Identificação inequívoca do estado: “Rascunho não salvo”, “Alterações não salvas”, “Salvo” **somente com confirmação real**, “Em revisão” etc. Não prometer autosave.
3. Campo Título com destaque editorial e label acessível; Resumo com orientação sucinta e contagem opcional apenas se houver regra real.
4. **CKEditor 5** em largura integral, barra de ferramentas responsiva: estilos, negrito/itálico, link, listas, mídia e menu “+ Inserir” condicionado a integrações realmente habilitadas. Sem toolbar horizontal que estoure viewport; teclado virtual não deve cobrir controles. Atalhos como “/” são opcionais, nunca pressupostos.
5. Configurações em seções expansíveis: categoria, imagem de capa, opções editoriais, SEO e avançado, apenas se existirem e o usuário puder editá-las. Validações expandem automaticamente o grupo que contém o campo inválido.
6. Salvar rascunho acessível durante o trabalho. CTA secundário “Conferir/Próximo” direciona à prévia ou preparação, sem transformar fluxo em wizard obrigatório. Sticky bottom action bar **opcional**, respeitando safe areas e teclado; nunca duplicar navegação global.

Inputs >= 16 CSS px; touch targets preferencialmente 44px, zoom e reflow sem perda de funcionalidades; inserir mídia usando seleção/upload dos kernels `aculta_image`/`aculta_gallery`, com alt text obrigatório onde aplicável. Mídia pode abrir diálogo/offcanvas responsivo; não criar upload ou biblioteca paralelos.

### Wireframe textual — mobile
```text
‹ Minhas publicações              Prévia ⋯
Nova publicação
Rascunho ainda não salvo

[Título da sua publicação                 ]
[Resumo editorial                         ]

[H1] [B] [I] [link] [lista] [+ Inserir ...]
┌─────────────────────────────────────────┐
│ Conte sua história...                   │
│                                         │
└─────────────────────────────────────────┘
▸ Configurações da publicação
    Categoria / Capa / SEO (se disponíveis)

[Salvar rascunho]        [Conferir →]
```

## 4. Desktop ≥1024px; tablet intermediário

Cabeçalho do editor com breadcrumbs discretos (“Revista / Minhas publicações / Editar”), estado e ações Prévia / Salvar. Coluna principal **flexível 65–75%** para título, resumo e corpo; lateral complementar **25–35%** para publicação, categoria, capa, SEO e opções avançadas. Largura útil mínima do texto e overflow controlado são mais importantes do que proporção rígida. Sidebar sticky **somente** se não cobrir mensagens, foco, navegação ou teclado. Em tablet, colapsar painel como drawer/accordion.

Uma única hierarquia de ações. Não replicar Salvar em vários lugares com efeitos divergentes. Tipo e posição dos controles seguem os tokens e padrões do ACULTA420. Dark/light usam os mesmos componentes, proporções e hierarquia; apenas tokens de apresentação mudam.

### Wireframe textual — desktop
```text
Revista / Minhas publicações / Nova publicação    [Prévia] [Salvar]
┌────────────────────────────────────────┬──────────────────────┐
│ Título                                 │ Publicação: Rascunho │
│ Resumo editorial                       │ Categoria            │
│ ────────────────────────────────────── │ Imagem de capa       │
│ CKEditor 5 / Inserir mídia e galeria   │ SEO / Avançado       │
│                                        │ Opções permitidas    │
└────────────────────────────────────────┴──────────────────────┘
```

## 5. Experiência de escrita

- **Escrita limpa:** editor ocupa o primeiro plano; plugins, blocos e comandos avançados surgem de forma contextual ou por “+ Inserir”, sempre com navegação de teclado e nome acessível.
- **Formatação sob controle:** Markdown ou slash commands somente após verificação de compatibilidade; o editor oficial continua CKEditor 5, não inventar outro modelo de conteúdo.
- **Imagens/galerias/documentos:** pesquisar e reutilizar Media autorizadas; upload pelo serviço próprio; processamento otimizado de `aculta_image`; Gallery referenciada, sem duplicação; documentos pelos viewers existentes.
- **Semântica e leitura:** hierarquia de headings correta, texto alternativo nas imagens e prévia fiel ao tema da Revista, sem reimplementar rendering no JavaScript.
- **Erros e recuperação:** foco no primeiro erro, preservar entradas, avisos claros de falhas AJAX/rede, sem alegar salvamento quando não confirmado. Alteração concorrente nunca é sobrescrita silenciosamente.

## 6. Prévia e preparação da publicação

Prévia usa a apresentação real da Revista, protegendo rascunhos e respeitando entity/revision access e domain purpose. Oferecer alternância de largura desktop/mobile como **simulação visual**, não como publicação independente. Concluir abre quadro de conferência com itens dinâmicos que refletem regras reais: título, categoria, imagens com alternativa textual, campos exigidos e opções de publicação. Não inventar validações ou pontuação SEO.

Mostrar consequência da ação: “Salvar rascunho”, “Enviar para revisão”, “Publicar”, “Publicar nova revisão” ou “Arquivar” **apenas** quando a transição existe e a pessoa tem permissão. Não equiparar salvamento a publicação. Para autores comuns, evitar CTA de publicação caso dependam de aprovação.

## 7. Minha área, listas e equipe

Na entrada da Revista, selecionar “Minhas publicações”. Filtros **Todos / Rascunhos / Em revisão / Publicados / Arquivados** aplicam-se somente aos **meus** itens nesse contexto. Buscar e ordenar por título/atualização; contadores opcionais apenas de consultas reais e autorizadas. Mobile: cartões com título, status em linguagem humana, data e “Continuar/Editar/Ver”, menu contextual para demais ações. Desktop: tabela legível, evitando autor repetido em contexto pessoal; o autor passa a ser coluna relevante na lista de equipe.

**Atenção ao código atual:** `RevistaTab.php` já fornece a estrutura de seções/estados e ação de criar via rota do Node `revista`. `StudioListingBuilder.php` já filtra acesso por `accessCheck(TRUE)`, mas **não** aplica escopo explícito de autoria à listagem padrão; também renderiza tabela que rola horizontalmente no mobile. Implementar a restrição pessoal no servidor antes de paginação e contagem, além de `user` cache context e respectivos metadados cacheáveis. `accessCheck(TRUE)` não substitui condição pessoal. Conteúdo de terceiros com permissão deverá ficar em vista distinta.

## 8. Implementação incremental sugerida (apenas planejada)

| Fase | Entregável | Gate |
| --- | --- | --- |
| RV-UX.1 | inventário de campos/widgets/tokens e fixtures representativas; variantes light/dark | contratos de provider e estados reais documentados |
| RV-UX.2 | shell de criação/edição mobile-first e composição desktop | 320–1440px, keyboard, sem JS |
| RV-UX.3 | CKEditor 5 responsivo, mídia e configurações progressivas | upload/alt/galeria, no overflow |
| RV-UX.4 | prévia fiel, feedback de salvamento e conferência contextual | revisão, rascunho e permissões |
| RV-UX.5 | “Minhas publicações”, cartões, filtros e contexto de equipe | escopo server-side, cache, cross-domain |
| RV-UX.6 | regressões, acessibilidade WCAG 2.2 AA e documentação | screenshots de runtime, testes por role |

**Testes-alvo:** 320, 360, 390, 768, 1024, 1280, 1440px; zoom 200%/reflow, leitor de tela, teclado virtual, modo claro/escuro, `prefers-reduced-motion`, sem JS, back/forward AJAX, rede falha, textos longos, autoria própria versus terceiros e workflow. Verificar que editor de outros autores exibe explicitamente autor verdadeiro.

## 9. Fronteiras e restrições

O `aculta_studio` não mantém dados de posts ou workflows próprios. Respeitar Form API, revisions, Workflows/Content Moderation, Drupal Entity Access, domínio e Group quando houver. `aculta_magazine` permanece dono do conteúdo `revista`. Apresentação exclusiva em ACULTA420 via tokens/SDC/Bootstrap 5, sem classes de design no módulo e sem acesso a storage pelo Twig. Não refatorar permissões, remover conteúdo legado, migrar entidades nem criar nova PR por causa deste planejamento.

**Esta versão registra decisões de experiência visual; não certifica nenhuma tela implementada.**
