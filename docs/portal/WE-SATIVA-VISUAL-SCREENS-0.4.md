# WE SATIVA — Visual mobile-first por estruturas

**Fase:** WES-UX 0.4.0-dev.1 · 2026-10-22 · **Status:** planejamento, não implementado.

## Princípios

Editor independente de GanjaLab/GanjaLab420. Wiki pública e editor mobile-first desde 320px; no desktop, apenas ampliar. Preservar Institution Bar e Domain Header multidomínio. Acessibilidade WCAG 2.2 AA; claro e escuro têm idêntica geometria. Form API e CKEditor 5 controlam o formulário, Drupal governa workflow/acesso; tema oferece somente Core SDC/UI Patterns/UI Styles.

## Catálogo de componentes e telas

| ID | Tela | Composição visual | Dependências |
| --- | --- | --- | --- |
| V01 | Convite Contribuir no verbete | CTA único no context-header.actions após H1 | render array e access do Wiki; não duplicar content.links |
| V02 | Seletor de contribuição | título/retorno + cards de corrigir, fonte, trecho, imagem, edição completa | links de tarefas reais, allowlist e permissões |
| V03 | Início | boas-vindas, continuar edição, novo verbete e ajuda | conteúdo autorizado; SDC card e empty-state |
| V04 | Meu trabalho | status, filtros por links HTTP, cartões de próprios rascunhos/revisões | Views/cache por usuário e Entity Access |
| V05 | Microtarefa | trecho original, campo da alteração, fonte, revisão antes de envio | modelo de proposta segura para verbete alheio |
| V06 | Criar verbete | título, verificação de duplicatas, resumo, editor, fontes, prévia | Node wiki_entry/Form API |
| V07 | Editor completo | corpo CKEditor amplo, metadados em disclosure mobile | acesso Node e workflow reais |
| V08 | Prévia | renderização não publicada, voltar a editar, salvar | Core preview e revisão |
| V09 | Revisão editorial | fila com status, comparação e decisões autorizadas | content_editor e content_moderation |
| V10 | Confirmação | status confirmado e retorno ao verbete/Meu trabalho | resultado real do servidor |
| V11 | Ajuda contextual | orientação curta, exemplos e fontes confiáveis | conteúdo editorial autorizado |
| V12 | Fontes e imagens | cartões de referência e mídia com alt/licença/origem | Link, Media, aculta_image |

## Fluxos visuais

**V01→V02:** leitor abre ação Contribuir perto do título, sob o shell global, nunca dentro do Domain Header. Seletor começa com uma questão: 'Como você quer ajudar?', e cada escolha tem rótulo simples, explicação curta e navegação HTTP acessível. Só mostrar ações com rota e acesso reais.

**V03→V04:** início exibe CTA Novo verbete e o último trabalho autorizado. Meu trabalho oferece filtros Em andamento / Em revisão / Publicados de acordo com o workflow real e cards de acesso privado. Não apresentar fila de terceiros a colaboradores comuns.

**V05:** corrigir informação usa uma intenção por vez, antes/depois, fonte quando cabível e confirmação 'Sua sugestão será moderada'. Não fingir storage ou autosave. No mobile uma coluna com ações visíveis, no desktop largura de leitura limitada.

**V06→V08:** novo verbete ou edição própria apresentam título, síntese, conteúdo CKEditor 5, referências, imagem, categorias e relacionados em seções progressivas. Botão Salvar rascunho separado de Enviar para revisão; nenhum botão Publicar a email_confirmed. Prévia explicita 'não publicado'.

**V09:** revisor vê item, fonte, diferenças de revisão e transições existentes (devolver para rascunho ou publicar). Uma decisão por vez no mobile. Conteúdo de terceiros não vira revisão pública antes da aprovação.

**V10→V12:** após envio real, status e volta segura; ajuda não bloqueante; mídia e fontes com metadados autorizados. Avisos futuros serão consumidos de aculta_messenger, identidade/participação do aculta_user.

## Responsividade e tokens

Mobile 320/360/390px: uma coluna, campos ≥16px, alvos de toque preferencialmente 44px, foco visível, toolbar sem hover, teclado/safe-area e disclosures que abrem em erro. Tablet 768px: reflow; desktop 1024/1280/1440px: 65–75% escrita e lateral opcional, sem sidebar obrigatória. Zoom 200/400%, sem JS, leitores de tela, contraste, reduzir movimento e light/dark sem alterar layout.

## Roadmap

| Marco | Entrega |
| --- | --- |
| V-0.4-A | inventário visual das 12 partes e contratos deste documento |
| V-0.4-B | histórias SDC para V01–V04: entrada, início, Meu trabalho |
| V-0.4-C | fluxos de microtarefa, criar, editor e prévia V05–V08 |
| V-0.4-D | fila, acompanhamento, ajuda, fontes e mídia V09–V12 |
| V-0.4-R | homologação em runtime, acessos, WCAG, cache e visual mobile/desktop |

**Gate:** nenhuma UI planejada autoriza nova rota, permissão, entidade ou aprovação. Primeiro verbete de email_confirmed e sugestões são moderados por content_editor; política comum em aculta_user e workflow Wiki em aculta_wiki. Autosave Form continua futuro.

## WES-UX V-0.4-B — entrada, início e Meu trabalho

[Especificação detalhada das telas V01–V04](WE-SATIVA-V-0.4-B-ENTRY-DASHBOARD-WORK.md): convite contextual único, seletor de intenções com ações realmente disponíveis, início com criação/continuação autorizadas e lista própria de trabalhos com filtros HTTP. Histórias WES-B-01 a WES-B-10, cenários vazios/erro/acesso, mobile-first 320px e claro/escuro. Somente Core SDC/UI Suite, sem rotas fictícias ou novas permissões. Próximo marco V-0.4-C (microtarefas e editor).

## WES-UX V-0.4-C — microtarefas, novo verbete, editor e prévia

[Especificação visual e funcional V05–V08](WE-SATIVA-V-0.4-C-EDITOR-FLOWS.md): fluxos progressivos, 12 stories de aceite, Form API com CKEditor 5 **já configurado para o formato wiki**; toolbar exportada, Media integrado e upload direto de imagem desativado. Contas email_confirmed criam e enviam à moderação, content_editor publica. Sugestões de alteração de terceiros dependem de backend seguro. SDC/UI Suite, mobile-first, sem autosave nesta fase. Próximo V-0.4-D.


## Correção de ownership V-0.4-D — fila e moderação no GanjaLab

**Decisão superior às descrições antigas V09:** a [janela de moderação Wiki](MARLEY-WIKI-MODERATION.md) pertence ao **GanjaLab (`aculta_studio`)**, não ao WE SATIVA. V09 designa agora a experiência GanjaLab, e V10–V12 são devolutivas, ajuda e recursos do colaborador no WE SATIVA. O subkernel Wiki mantém workflow, fila lógica, acesso e dados; o Studio só apresenta e delega. Nenhum painel ou transição de content_editor deve ser criado dentro do WE SATIVA. Plano V-0.4-D-A–R antes de Runtime.

## Decisão normativa: ACULTA Studio (2026-10-22)

The statement 'independent of GanjaLab' means **different user experience and permissions**, not independent of aculta_studio. WE SATIVA, GanjaLab and GanjaLab420 belong to the ACULTA Studio interface platform and share its neutral UI contracts; user flows remain distinct.

[Contrato e roadmap transversal](../architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md). Esta decisão prevalece sobre terminologia anterior que equiparava o submódulo exclusivamente a GanjaLab. **Não declarar consumidores planejados como rotas implementadas.**
