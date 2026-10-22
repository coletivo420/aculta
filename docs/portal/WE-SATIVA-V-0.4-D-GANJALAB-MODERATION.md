# V-0.4-D — WE SATIVA colaborador e moderação Wiki no GanjaLab

**Data:** 2026-10-22 · **Versão:** WES-UX 0.4-D / GANJALAB-WIKI-MOD 0.1 · **Estado:** especificação visual, sem implementação.
**Decisão nova:** **WE SATIVA não hospeda fila nem janela de decisão editorial.** Todas as tarefas de revisor/moderador acontecem em **GanjaLab**, que consome as ações autorizadas do `aculta_wiki`. WE SATIVA permanece experiência do colaborador, inclusive autor de verbete, com status e devolutivas, mas sem painel de moderação.

## Fronteira de três camadas

| Responsável | Possui | Não possui |
| --- | --- | --- |
| `aculta_wiki` | Nodes `wiki_entry`, workflow Content Moderation, revisões, access, fila/provider, proposta moderável de terceiros (futura), decisões servidor | segundo sistema de moderação visual |
| `aculta_user` | email_confirmed, política de primeira participação, conta, autor, preferências de identidade | workflow Wiki, storage de propostas |
| `aculta_studio` (GanjaLab) | **experiência de trabalho** do editor: aba Wiki, submenu Moderação, fila, janela, filtros, ações que delegam ao Wiki | estado autoritativo de publicação, grants alternativos ou inbox |
| `aculta_messenger` | avisos por mudança de estado e retorno ao colaborador, após adaptação aprovada | workflow editorial |
| `aculta420` | Core SDC/UI Suite, tokens, layouts responsivos | ACL e moderação |
| WE SATIVA | criação, sugestão, prévia, envio, **Meu trabalho**, respostas e ajuste do colaborador | ações de publicar/rejeitar e fila de outros colaboradores |

**GanjaLab é transversal** e continua usando os plugins `StudioTab` existentes, com navegação por áreas → seções → abas, AJAX progressivo e links HTTP. Não duplicar o shell Institution Bar + Domain Header. A aba Wiki só existe se o provider estiver habilitado e o usuário tiver access verdadeiro. Não implantar uma UI própria no site público da Wiki para revisão.

## V09 — Janela de moderação Wiki no GanjaLab

### Localização lógica (não rota habilitada)
Área `Wiki` → seção `Moderação` → abas `Aguardando`, `Em análise`, `Devolvidos`, `Histórico` **somente** com providers e estados persistidos reais; rótulos de filtro que não correspondam a estados existentes são filtros de consulta, não novos estados Content Moderation. Se a aba Wiki já existir, estender a seção, sem registro duplicado de StudioTab.

### Painel principal
- Faixa de contexto: `GanjaLab / Wiki / Moderação`, total real de pendentes visíveis, filtros por tipo (primeiro verbete, revisão, proposta), data e busca autorizada; sem métricas inventadas.
- Lista de revisão: cartão/linha com título, autor respeitando privacidade, tipo, origem/revisão-base, estado real, momento do envio e ação `Analisar`. Paginação, ordenação estável e filtros HTTP. Nenhuma inclusão de rascunhos de outros usuários sem acesso.
- **Janela de moderação** abre item no GanjaLab (modal responsivo no desktop somente quando a11y/foco funcionarem; no mobile, **página/painel full-screen** com URL canônica e fallback sem JS).
- Topo da janela: título e status, breadcrumb/retorno à fila, badge de tipo `Primeiro verbete` ou `Alteração proposta`, aviso de revisão concorrente, autores e timestamps seguros.
- Corpo: duas vistas `Conteúdo atual` / `Proposta` ou comparação lado a lado desktop e empilhada mobile; mudanças destacadas por rótulo e sem depender só de cor. Para verbete novo, mostrar `Primeiro envio`, sem original inventado. Seções de fontes, imagem/alt/licença, categorias e observações.
- Lateral contextual desktop opcional: checklist editorial (neutralidade, fontes verificáveis, direitos de imagem, duplicatas, acessibilidade), histórico oficial e contexto de publicações anteriores. Não construir tabela paralela de histórico.
- Rodapé de decisão: `Devolver para ajustes`, `Publicar`, `Voltar` condicionados às transições reais `review_to_draft` e `review_to_published`. Não inventar estado `Rejeitado` no workflow existente; rejeição definitiva somente após ADR.
- Justificativa editorial: ao devolver, pedir motivo objetivo e persistir de maneira segura usando campo/entidade suportada; se não houver campo, **não anunciar comentário persistente** antes do contrato e schema. Não expor notas privadas ao público.

### Transições e segurança
Servidor no `aculta_wiki` valida o `content_editor` **com confirmação verdadeira**, acesso à revisão/Node, purpose e domínio, estado `review`, autorização da transição e token CSRF/Form API. `aculta_studio` nunca efetua publish bypass nem atualiza Node diretamente por AJAX sem serviço do Wiki. Após POST o servidor devolve estado persistido; o Studio atualiza a fila com cache invalidado. Se revisão-base estiver obsoleta, bloquear ação, reabrir comparação e oferecer resolução, não sobrescrever silenciosamente.

### Responsividade
320–390px: página única sem duas colunas, barra contextual com voltar, detalhes primeiro, conteúdo atual/proposta empilhados e decisões ao final; não usar modal sobre teclado. ≥1024px: modal acessível amplo ou painel dividido, lista à esquerda apenas se espaço e foco permitirem. 200/400% zoom, Tab/Escape/retorno foco, no-JS, WCAG 2.2 AA, contrastes e dark mode coerentes.

## V10 — Confirmação, devolutiva e acompanhamento WE SATIVA

Após salvar `draft` ou enviar `review` de verdade, apresentar confirmação com título, estado oficial, link `Meu trabalho` e link ao verbete se permitido. Devoluções aparecem em Meu trabalho, com `Ajustes solicitados` como texto de produto mapeado à transição `review_to_draft` e anotação existente, **não** estado novo. O colaborador não vê fila de terceiros ou botões de publicação.

Avisos de recebido/devolvido/publicado devem ser integrados ao `aculta_messenger`, mediante evento idempotente com revalidação de acesso; `aculta_user` segue dono da identidade. Sem notificação enviada por WE SATIVA diretamente.

## V11 — Ajuda contextual

Entrada não bloqueante por seção: fonte confiável, linguagem neutra, direitos de imagem, privacidade, diferença entre salvar/enviar/publicar e o que acontece na revisão. Varia entre colaborador (WE SATIVA) e moderador (GanjaLab) sem criar duas fontes de verdade; conteúdo de ajuda tem ownership Wiki e apresentação SDC.

## V12 — Fontes, imagens e documentos

Usar `field_wiki_references` e Media autorizada, `aculta_image` para processamento e viewer central de documentos/PDF. Exibir URL/título da fonte, licença/crédito, alt e status de validação. O moderador confere fonte e mídia na janela GanjaLab; o colaborador ajusta no WE SATIVA. Não implementar novo upload direto no CKEditor: `editor.editor.wiki.yml` tem `image_upload.status: false`.

## Stories / gates

| Story | Evidência esperada |
| --- | --- |
| WES-D-01 | colaboração concluída e confirmação com estado real |
| WES-D-02 | devolução visível somente ao autor autorizado e editor |
| WES-D-03 | alerta via Messenger sem aviso duplicado |
| GL-WIKI-01 | aba Wiki somente para `content_editor` autorizado |
| GL-WIKI-02 | fila mostra somente revisões acessíveis, sem dados de terceiros vazados |
| GL-WIKI-03 | janela de primeiro verbete não simula comparação original |
| GL-WIKI-04 | desktop comparativo, mobile empilhado, fallback HTTP |
| GL-WIKI-05 | `review_to_draft` e `review_to_published` apenas no backend Wiki |
| GL-WIKI-06 | perda de permissão e revisão concorrente bloqueiam POST |
| GL-WIKI-07 | uma ação não gera notificação repetida no Messenger |
| GL-WIKI-08 | título único, teclado, zoom 400%, light/dark e shell íntegro |
| WES-D-04 | fontes, Media, alt/licença respeitam permissões e formatos existentes |

## Roadmap em peças

1. **D-A:** contratos de ownership Studio ↔ Wiki, inventariar StudioTab existente, fluxos de confirmação e campos reais de devolutiva.
2. **D-B:** stories SDC/UI Suite da fila e janela, layout mobile/desktop e teste de a11y sem publicar ação fictícia.
3. **D-C:** provider de fila Wiki + navegação Studio por rota real, access e cache.
4. **D-D:** formulário de revisão/devolução/publicação delegado ao Wiki, idempotência e concorrência.
5. **D-E:** feedback WE SATIVA e integração Messenger; fontes e mídia.
6. **D-R:** testes Drupal, homelab e verificação visual multirresolução, sem JS e por perfil.

**Invariantes:** primeiro verbete de email_confirmed moderado; novas alterações/sugestões continuam revisadas, publicadas apenas por content_editor. Autosave Form permanece futuro. Nenhuma implementação decorre automaticamente deste documento.
