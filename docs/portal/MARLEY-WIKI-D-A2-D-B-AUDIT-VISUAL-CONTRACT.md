# ACULTA Studio Marley Wiki — D-A.2 auditoria de listagem + D-B design da moderação

**Fase:** V-0.4-R / ACULTA Studio Marley D-A.2 e D-B · **Data:** 2026-10-22 · **Situação:** auditoria estática e contrato visual concluídos; correções de código e testes Runtime pendentes.

## D-A.2 — achados verificáveis em MarleyListingBuilder

| ID | Evidência do código | Risco | Ação obrigatória |
| --- | --- | --- | --- |
| SEC-01 | build(), buildComments(), buildRevisions(), buildGroups() usam cache contexts somente user.permissions, embora retornem lista, título, autor, datas e revisões específicos de usuário | risco de cache entre contas com permissões iguais, se entidades/grants forem diferentes | estabelecer contextos user + permissions/roles quando relevantes, Domain/purpose e todas as dependências cacheáveis; testar duas contas e cache aquecido |
| SEC-02 | build() consulta content_moderation_state com accessCheck(FALSE), carrega estados e mapeia por content_entity_id sem distinguir revision ID | indexação de status de revisões inconsistentes; consulta ampla a registros não autorizados | criar provider Wiki especializado que selecione a revisão correta e só exponha estado/contagens de conteúdo autorizado; auditar proteção de consultas auxiliares |
| SEC-03 | buildRevisions() executa allRevisions()->accessCheck(TRUE) e exibe campos da revisão sem chamar explicitamente a operação view revision por linha | grants ao Node podem não equivaler a grants à revisão | exigir revisão carregada + node access + revision view access por usuário/Domain em cada registro |
| SEC-04 | build() usa nid query limitada a 50, sem paginação; estados coletados globalmente antes de aplicar filtro da listagem | volume, dados incompletos e custo desnecessário | filtro server-side, paginação estável e provider restrito a workflow/bundle e escopo |
| SEC-05 | cabeçalho de tabela em build() é inicializado duas vezes e render array table tem formatação irregular | duplicação/manutenção | limpar duplicação em fase posterior com testes, sem alterar comportamento de outras áreas do Studio nesta fase |
| SEC-06 | listing de Comment usa accessCheck(TRUE), depois getCommentedEntity() e cria URL do host sem teste explícito de view | possível link de host inacessível, dependendo de grants | verificação contextual de acesso a entidade host e URL antes de link; caracterização Fórum/LMS |
| SEC-07 | listing de Groups consulta group_relationship com accessCheck(FALSE), depois usa IDs do Group | associação privada/contexto LMS pode ser exposta se não revalidada | revisar autorização da relação e revalidar Group + membership no provider LMS |
| SEC-08 | MarleyController root tem cache contexts user.permissions e url.path; seção é selecionada com MarleyPresenter e os filtros de access() de WikiTab incluem create wiki_entry | seção futura de moderação pode ser visível ao criador não moderador | gating de seção e cada item por capacidade de moderação real + user/domain cache contexts, sem depender apenas do MarleyTab.access |
| SEC-09 | MarleyController usa SDC context-tabs para áreas/subtabs; JS tem AJAX com fallback e foco | base de UI consistente já existente | manter, não criar tabs/nav paralelas; testar back/forward, foco e erro |

**Classificação:** esses são **riscos e lacunas deduzidos do código**, não demonstração de vazamento no Runtime. Não afirmar violação explorável antes dos testes; bloquear uso dessa listagem genérica como fila de moderação Wiki até correção.

**Atualização Studio dev.5:** `create wiki_entry content` foi removida do gate
de entrada da WikiTab, e a seção `verbetes` exige `edit any wiki_entry content`.
SEC-08 deve ser lida como evidência histórica; o acesso específico a revisão e
propostas continua regido pelos gates próprios e pela Entity Access da Wiki.

### Direção de refatoração segura

- **D-A.2.1:** escrever testes de caracterização para MarleyListingBuilder em roles distintas e grants por entidade/revisão; não alterar código antes de entender consumidores.
- **D-A.2.2:** provider de revisão/filas Wiki em aculta_wiki, retornando itens e metadados cacheáveis autorizados, com query restrita a bundle/wiki_editorial e revisão-base.
- **D-A.2.3:** integrar provider via interface do Studio, mantendo WikiTab existente e seção de moderação separada; garantir que listagem não seja tratada como concessão de acesso.
- **D-A.2.4:** corrigir cache metadata da listagem transversal onde houver dados por usuário, após testes de regressão de Revista, Fórum e LMS.
- **Gate de publicação:** todos os POSTs e transições permanecem no aculta_wiki; Studio somente consome URLs/autorização.

## D-B — arquitetura visual da fila e da janela

**Objetivo de produto:** experiência própria do editor/moderador no ACULTA Studio Marley, distinta do WE SATIVA (autor/colaborador). Reutilizar Core SDC/UI Suite, não widgets legados. ACULTA Studio Marley mantém Institution Bar + Domain Header de conta e seu shell de navegação.

### B01 — aba Wiki > Moderação > Aguardando

**Mobile 320–390px:**
1. Cabeçalho global já existente; breadcrumbs ACULTA Studio Marley › Wiki › Moderação; título único de página.
2. Filtros compactos por links/GET: Aguardando, Todos autorizados, Histórico (somente quando provider disponível), seleção de tipo: primeiro verbete / revisão / proposta.
3. Cards em uma coluna: título autorizado, tipo, status real, instante de envio, ação contextual `Analisar`. Autor apenas se permitido.
4. Rodapé de paginação, estados vazio, falha e sem permissão; nenhum contador ou item de preenchimento falso.

**Desktop >=1024px:** painel de filtro à esquerda se houver espaço; resultados em lista densa acessível, sem obrigar tabela. Máximo de largura controlado; não fazer sidebar no mobile.

### B02 — janela de moderação

**Mobile:** página própria full-screen após cabeçalhos globais; retorno à fila, título, estado real, comparador em blocos empilhados, fontes e mídias, observações, ações autorizadas ao fim. Não abrir modal pequeno sobre teclado.

**Desktop:** comparador lado a lado em região central (versão atual e revisão proposta), painel lateral de contexto com fontes/histórico/checklist; janela modal somente se foco, rolagem, rota e fallback funcionarem, caso contrário página/painel com URL canônica.

**Primeiro verbete:** não inventar original; mostrar `Primeira contribuição — sem versão anterior`. **Revisão de verbete existente:** comparar revision IDs corretos e sinalizar conflito antes da decisão.

**Ações:** `Devolver para ajustes` (review_to_draft), `Publicar` (review_to_published) e `Voltar`, apenas com permissão/estado real; justificativa persistida apenas se o Wiki fornecer storage/contrato. Publicação não ocorre no cliente.

### B03 — feedback e estados

- Sucesso real do servidor: atualizar item da fila, confirmar ação e disparar evento idempotente para aculta_messenger quando implementado.
- Erro de permissão, sessão expirada e revisão concorrente: impedir POST e preservar contexto; não devolver dados proibidos.
- A11y: título único, aria-current nas tabs reais, foco após AJAX, teclado, Escape quando modal, estados textuais além de cor, reflow zoom 400%, mínimo 320px e light/dark com geometria idêntica.

## Composição SDC/UI Suite

| Peça | Componente/ferramenta | Regra |
| --- | --- | --- |
| Shell global + estrutura Studio | existentes; nunca copiar | manter multidomínio e navegação AJAX do Studio |
| Breadcrumb/cabeçalho | aculta420:context-header | provider fornece links autorizados e um H1 |
| Seções e filtros | aculta420:context-tabs (quando navegação por URLs) | UI apenas, aria-current e fallback GET |
| Item de fila | aculta420:card por slots | título, status, metadados, link autorizados |
| Estados vazios | SDC vazio existente somente após auditoria de sua metadata | nenhum componente fictício |
| Form de decisão | Drupal Form API encapsulada em layout SDC | CSRF e transição no Wiki |
| Media/fontes | render arrays do Wiki/Image/Media | não copiar storage nem permission |
| Mensagens | feedback Core e futuro aculta_messenger | sem central paralela |

### Stories de aceite B

| Story | Caso |
| --- | --- |
| GL-B-01 | usuário email_confirmed sem poder de publicar não vê seção nem fila |
| GL-B-02 | editor confirmado vê somente itens e revisões autorizados |
| GL-B-03 | duas contas com permissões iguais e grants diferentes não compartilham resultados em cache |
| GL-B-04 | Node legível mas revisão não autorizada não revela corpo/metadados da revisão |
| GL-B-05 | primeiro verbete não apresenta comparação inexistente |
| GL-B-06 | revisão concorrente bloqueia transição sem sobrescrever nova versão |
| GL-B-07 | listagem paginada/filtrada retorna resultados coerentes e access-checked |
| GL-B-08 | AJAX/back/focus/no-JS, modo claro/escuro, zoom 400%, 320–1440px |
| GL-B-09 | Forum/LMS/Revista não sofrem regressão após hardening transversal |
| GL-B-10 | publicar/devolver é só POST do Wiki, com confirmação real |

## Próxima execução

**D-A.2-T:** implementar testes de caracterização e endurecer cache/access no listing sem regressão. **D-B-S:** construir stories SDC reais somente quando provider/cache forem auditados. **D-C:** integrar a seção Moderação da WikiTab existente. **D-R:** testar no homelab; nenhum gate Runtime ou screenshot real realizado nesta fase.

## D-A.2-T — primeira correção de código (2026-10-22)

**Commits:** `0a004cc` (MarleyListingBuilder) e `7709f12` (Unit). A listagem de Nodes, Comments, Groups e Revisions agora acrescenta cache context `user` junto a `user.permissions`, isolando resultados por conta; isso não dispensa tags/grants adicionais. A listagem de revisões verifica `view` e `view revision` na revisão antes de montar link ou metadados. Cabeçalho duplicado foi removido.

**Testes incluídos mas NÃO executados no runtime:** `StudioListingRevisionAccessTest` caracteriza (1) Node visível e revisão negada, (2) ambas operações concedidas, (3) Node negado e short-circuit. Não alegar testes PASS nem afirmar que a operação `view revision` cobrirá todos os handlers sem Kernel. Próximo agente deve executar PHPUnit/Drupal Kernel e confirmar semântica dos handlers.

**Continua bloqueado:** a consulta de `content_moderation_state` com `accessCheck(FALSE)` e associação de estado por nid, a listagem sem paginação, grants de comentário hospedeiro, relações Group e cache por domínio. Por isso **não** reutilizar a tabela genérica como fila editorial Wiki e não ativar seção Moderação até concluir provider Wiki com revisão autorizada. Garantir testes de cache com duas contas do mesmo papel, Node publicado com draft privado e grupos LMS diferentes.
