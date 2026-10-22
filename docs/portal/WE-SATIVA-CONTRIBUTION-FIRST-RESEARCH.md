# WE SATIVA — Pesquisa comparativa e contribuição primeiro

**Data:** 2026-10-22 · **Versão:** WES-UX 0.2.0-dev.1 · **Estado:** pesquisa e especificação; não implementado.
**Relacionado:** [WE SATIVA](WE-SATIVA-VISUAL-EDITOR.md), [Wiki pública](WIKI420-VISUAL-EXPERIENCE.md), [SDC do tema](../../web/themes/custom/aculta420/docs/visual-cross-product-roadmap.md).

## Decisão principal

**A edição nasce da leitura, não de um painel administrativo.** No verbete publicado, oferecer ação contextual `Contribuir` somente quando a capacidade pertinente existir. Abrir WE SATIVA com o contexto do verbete e intenção selecionada, preservando URL de retorno; rota/URI canônica a decidir após mapear providers/access. O visitante sem permissão vê orientação e, se cabível, autenticação com retorno seguro — nunca um formulário cujo envio falhará por falta de ACL. Não acionar o GanjaLab nem o GanjaLab420.

## Referências externas (padrões a adaptar, não dependências)

| Experiência | Padrão relevante | Aplicação WE SATIVA |
| --- | --- | --- |
| Wikimedia Growth / Suggested Edits | tarefas pequenas, tutoriais contextuais, links/imagens/referências | cartão `Melhorar este verbete` e tarefas guiadas |
| MediaWiki Structured Tasks | dividir um fluxo complexo em operações pequenas no mobile | wizard curto por intenção, não o editor inteiro |
| BookStack | WYSIWYG padrão, rascunhos e atalhos familiares | CKEditor 5 nativo, comandos visuais, botão explícito de salvar |
| Notion | sugestões de mudanças e comentários vinculados a texto | inspiração de UX; **não declarar diff inline já disponível no Drupal** |
| Slab | leitura e edição próximas e alternáveis | transição direta do verbete para WE SATIVA com retorno |
| Drupal Core | Content Moderation + revisions + CKEditor 5 + Media Library | fontes de verdade de workflow, versão, formulário e mídia |

Fontes consultadas:
- https://www.mediawiki.org/wiki/Help:Growth/Tools/Newcomer_Tasks/en
- https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Structured_tasks
- https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Structured_tasks/Add_a_link
- https://www.bookstackapp.com/docs/user/wysiwyg-editor/
- https://www.notion.com/help/suggested-edits
- https://www.notion.com/help/writing-and-editing-basics
- https://help.slab.com/en/articles/7922471-post-page-basics
- https://www.drupal.org/docs/core-modules-and-themes/core-modules/media-library-module/embedding-media-with-ckeditor-5
- https://www.drupal.org/docs/8/core/modules/content-moderation/overview
- https://www.drupal.org/project/autosave_form

## Entrada a partir de verbete — contrato de interação

```text
Wiki420 / verbete publicado
  [Contribuir]  (se houver acesso real)
    ├─ Corrigir uma informação
    ├─ Adicionar uma referência
    ├─ Melhorar um trecho
    ├─ Sugerir imagem ou legenda
    └─ Editar verbete completo (se permitido)
         ↓
   WE SATIVA / tarefa do verbete
   [Alteração] [Fonte ou justificativa] [Prévia]
   [Salvar rascunho / Enviar para revisão conforme backend]
         ↓
   Confirmação real e acompanhamento em Meu trabalho
         ↓
   [Voltar ao verbete]
```

**Não é edição inline improvisada.** O botão pode levar a formulário contextual dedicado ou wizard em rota do kernel Wiki. Fluxos rápidos devem produzir alterações rastreáveis e autorizações verificadas. Uma sugestão sem permissão de editar o Node não deve fingir ser uma nova revisão do Node; avaliar mecanismo de proposta explicitamente antes de criar entidade/storage. Proteção antiabuso, rate limits e moderação necessários para contribuintes amplos. Evitar colocar texto não publicado em URLs, logs ou cache público.

## Mobile-first: regras concretas

- **320–767 px:** leitor ocupa uma coluna; botão `Contribuir` visível no contexto do título sem cobrir busca, Institution Bar nem Domain Header. Ao tocar, escolher UMA tarefa, não exibir dezenas de campos.
- **Tarefa curta:** contextualizar trecho existente, propor alteração, campo de fonte/justificativa e revisar; poucos controles, ação explícita e rótulos humanos. Nunca obrigar preenchimento de taxonomias ou metadados técnicos para simples correção.
- **Editor completo:** CKEditor 5 em coluna única; configurações secundárias em disclosure, erro abre seção correspondente; toolbar mobile acessível ao teclado/toque, sem hover; input >=16px, foco, safe-area, zoom 200/400%, no-JS.
- **Desktop:** duas colunas só quando espaço real permitir; leitura/referência pode aparecer ao lado, nunca desviar do editor principal. Mesmo conteúdo e sequência semântica do mobile.
- **Claro/escuro:** mesmas posições/dimensões, variando somente tokens semânticos.
- **Segurança UX:** permitir voltar sem descartar alterações silenciosamente; mostrar `não salvo`, `rascunho salvo`, `em revisão`, `publicado` apenas a partir do backend; deixar claro que envio não é publicação.

## Camadas de edição (ordem de entrega)

**C0 — contribuição de baixa fricção:** contextualizar link do verbete, oferecer intents com URLs reais; redirecionar a Form API de forma autorizada. Provar retorno e permissão.

**C1 — microtarefas:** corrigir texto, inserir fonte, ajustar link interno, sugerir imagem. Implementar um fluxo por vez e com provider apropriado. Se não há backend de sugestão, não oferecer ação fictícia.

**C2 — editor visual:** formulário nativo Node/CKEditor 5, título, resumo, corpo, referências, mídia/categorias de acordo com campos existentes; preview antes da transição.

**C3 — revisão e aprendizado:** fila por permissão, diferenças de versões se Core oferecer, feedback contextual, tarefas sugeridas oriundas de necessidades realmente catalogadas, ajuda curta e exemplos.

**C4 — qualificação avançada (opcional):** estudar Autosave Form Drupal 11 (projeto contrib; storage próprio e limites de conflitos). Não instalar automaticamente, não prometer autosave, bloqueio colaborativo, sugestão inline ou colaboração simultânea sem análise de conflitos e segurança.

## Padrões SDC/UI Suite e responsabilidades

- `aculta420:context-header` = título/contexto da página sem duplicar H1.
- `aculta420:context-tabs` = navegação por URL real; não usar `product-tabs`.
- `aculta420:card` / `editorial-card` = lista de tarefas/sugestões quando houver dados.
- `aculta420:empty-state` = nenhuma contribuição/tarefa, se confirmado pelo provider.
- Form API + CKEditor 5 + Media Library = conteúdo e operações; SDC encapsula apenas a apresentação.
- `aculta_wiki` = acesso, rotas, criação de revisões, workflow e estado; `aculta_user` = identidade e perfil; `aculta_messenger` = avisos se integrados; `aculta420` = UI/tokens.
- **Não criar componente novo** até provar que Core SDC/UI Patterns/UI Styles e componentes atuais não suprem a necessidade. Não copiar Twig legado nem duplicar engines.

## Próximas microfases versionadas

| Fase | Entrega | Gate |
| --- | --- | --- |
| WES-UX 0.2-A | matriz de entrada por verbete, permissões, retorno e rota real | link autorizado; sem bypass |
| 0.2-B | protótipos mobile `Contribuir` + seleção de tarefa + correção de trecho | menor número de campos e escolhas |
| 0.2-C | primeiro microfluxo funcional sobre Drupal Form API/revision | publicação somente via workflow permitido |
| 0.2-D | integração editor completo e preview, referências e Media Library | CKEditor e formatos autorizados |
| 0.2-E | painel pessoal, pendências de revisão e ajuda progressiva | estados reais, privacidade e acessibilidade |
| 0.2-F | testes com participantes novatos: sucesso/tempo/erros e responsividade | WCAG 2.2 AA e medição de conclusão |
| 0.2-R | homologação no homelab | Core SDC, UI Patterns/UI Styles, ACL/cache, no-JS, browsers e domínio |

**Regra estrutural permanente:** Wiki pública e WE SATIVA 100% mobile-first. Institution Bar + Domain Header multidomínio preservados em todas as telas. Nada desta documentação implanta UI ou muda permissões.


## Decisão aprovada — fundação iniciada

**Aprovados:** contribuição contextual a partir do verbete, microtarefas progressivas, WE SATIVA independente, mobile-first em editor e Wiki pública, contratos SDC/UI Suite e shell multidomínio. O [contrato de fundação do subkernel Wiki](../../web/modules/custom/aculta_portal/modules/aculta_wiki/docs/WE-SATIVA-FOUNDATION.md) governa a primeira execução. **Autosave Form é recurso futuro fora do escopo atual:** não instalar nem implementar autosave, mesmo que referenciado como opção de pesquisa acima. As ações de edição só serão expostas com backend/access/rota reais.
