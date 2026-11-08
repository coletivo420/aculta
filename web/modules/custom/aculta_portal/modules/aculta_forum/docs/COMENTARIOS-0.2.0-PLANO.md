# Comentários compartilhados - ciclo 0.2.0-dev

Data: 2026-11-08.
Estado: planejamento aprovado para início; implementação e homologação pendentes.
Entrega documental: 0.2.0-dev.1.
Base inspecionada: ab78bb2057f9fa3a293e9b976f4c2f8717434db7, branch dev.
Core no composer.lock da base: drupal/core 11.5.0-beta1.
Pesquisa upstream: APIs Drupal 11.x; conferir a API exata instalada antes de implementar.

## 1. Objetivo e decisões do responsável

Responder e citar devem abrir, validar, apresentar prévia e enviar na página atual.
Revista, Wiki, LMS e Growroom420 consomem o mesmo sistema de discussões do
aculta_forum. Outros módulos poderão aderir pelo contrato de contexto.
O aculta_forum centraliza registro, resolução, autorização e composição das
discussões; o tema apresenta o contrato por SDC.

A Wiki terá o provider registrado pelo aculta_forum. Mover
WikiDiscussionContextProvider para Context/Provider no Fórum e registrar em
aculta_forum.services.yml; remover o registro em aculta_wiki.services.yml.
A classe usará Entity API, bundle wiki_entry e o campo compartilhado, sem
importar classes da Wiki nem exigir aculta_wiki para compilar o container.
A Wiki mantém ownership de verbetes, workflow e Entity Access.

Comment e ForumPost continuam com seus storages, revisões e workflows.
Esta fase não autoriza migração nem exclusão de conteúdo. D-65 permanece:
a rota standalone de Comment responde 404; navegação aponta ao hospedeiro.

Novos caminhos de ações humanas e endpoints próprios usam português,
minúsculas, hífens e sem acentos, inclusive caminhos internos criados nesta fase.
Preservar route IDs, serviços, campos e bundles para estabilidade das APIs.
Sem aliases temporários ou redirects legados. Não modificar Core/contrib.

## 2. Inventário dos pontos de entrada

| Consumidor | Hospedeiro | Provider atual | Registro atual |
| --- | --- | --- | --- |
| Growroom420 | node:aculta_forum_topic | PublicForumContextProvider | aculta_forum |
| Revista | node:revista | PublicationContextProvider | aculta_forum |
| LMS | node:aculta_lms_topic com Group | LmsDiscussionContextProvider | aculta_forum |
| Wiki | node:wiki_entry | WikiDiscussionContextProvider | aculta_wiki; mover para aculta_forum |

Todos usam field_aculta_discussion e Comment aculta_discussion_post.
Config Sync de Revista, Wiki e LMS usa comment_default, pager 0,
form_location: true e 50 comentários por página. O Fórum tem defaults próprios.
Essas configurações versionadas não comprovam a configuração ativa no Runtime.

Entradas de código, relativas à raiz:

- aculta_forum/src/Context/DiscussionContextResolver.php (dentro do submódulo):
  resolve providers registrados; contextos ambíguos falham explicitamente.
- aculta_forum/src/Hook/ForumQuoteHooks.php: altera links e formulário de citação.
- aculta_forum/src/Service/ForumQuoteBuilder.php: citação filtrada, até 1200 caracteres.
- aculta_forum/src/Service/ForumPostThreadBuilder.php: links de resposta geral e
  a publicação em aculta_forum.post_create; pager ForumPost 1 e Comment 0.
- aculta_forum/src/Controller/ForumPostSubmissionController.php: resolve tópico/pai.
- aculta_forum/src/Form/ForumPostSubmissionForm.php: formulário e retorno ao tópico.
- aculta_forum/src/Hook/ForumPostThreadHooks.php: insere posts revisionáveis no tópico.
- aculta_forum/src/Hook/DiscussionProgressHooks.php: leitura das discussões.
- aculta_forum/src/Hook/DiscussionModerationHooks.php: autorização de moderação.
- aculta_forum/src/Hook/ForumDiscussionWriteHooks.php: coordenação de locks de escrita.
- aculta_wiki/src/Context/WikiDiscussionContextProvider.php e services.yml:
  implementação e registro atuais do contexto Wiki.
- web/themes/custom/aculta420_v2/templates/comment--field-aculta-discussion.html.twig
  e comment--comments.html.twig: apresentam content.links e permalink.
- web/themes/custom/aculta420_v2/components/forum-post-card/: cartão e ações.
- web/themes/custom/aculta420_v2/js/forum-quote.js: interceptação atual da citação.
- web/themes/custom/aculta420_v2/components/discussion-comment/: SDC experimental;
  o README cita um consumer que não corresponde aos templates atuais.

Nos itens com prefixo aculta_forum/ ou aculta_wiki/, o caminho completo começa
em web/modules/custom/aculta_portal/modules/.

Core a estudar na versão instalada: CommentLazyBuilders::renderForm/renderLinks,
CommentDefaultFormatter::viewElements, CommentForm::form/preview/save,
CommentThemeHooks::preprocessComment, Entity/Comment::permalink e
Render/Element/Link::preRenderLinks.

## 3. Achados e grau de evidência

Inspeção estática não equivale a reprodução HTTP ou homologação autenticada.

| ID | Evidência | Efeito e correção planejada |
| --- | --- | --- |
| COM-01 | Core gera comment.reply; ForumPostThreadBuilder usa /publicacao/nova | Resposta abre página separada. Substituir entrada por compositor contextual. |
| COM-02 | forum-quote.js cancela clique e acrescenta texto sem definir pid | Citação pode ser salva no nível principal. Construir entidade com pai validado no servidor. |
| COM-03 | JS usa document.querySelector('form#comment-form') | Falha ou seleciona outro contexto com múltiplos formulários. Usar identificadores por discussão. |
| COM-04 | Templates não repassam attributes ao cartão | Perdem ID comment-{id} e data-comment-user-id do Core. Preservar atributos autorizados e estados. |
| COM-05 | Core CommentForm define action comment.reply para novo comentário sem pai | Envio/prévia/erros podem sair da página mesmo com caixa inline. Controlar action e resposta. |
| COM-06 | Template imprime permalink Core e D-65 bloqueia canonical standalone | Link gerado aponta a rota bloqueada. Resolver URL do hospedeiro, pager e âncora no backend. |
| COM-07 | PHP usa ForumQuoteBuilder; JS copia innerText sem limite equivalente | Citações inconsistentes. Uma única política no servidor; preservar texto já digitado. |
| COM-08 | ForumQuoteHooks instancia aculta420_v2:button no módulo | Dependência indevida do tema. Entregar ação neutra e aplicar SDC no adapter visual. |
| COM-09 | Citação consulta getReadAccess, não getWriteAccess | Lacuna entre CTA e escrita contextual; reproduzir em discussão fechada e acesso revogado. |
| COM-10 | CSS/JS detectam ação pelo literal da URL | Fragilidade com slugs e ordem de query. Usar atributos de ação, alvo e contexto explícitos. |
| COM-11 | Citar é adicionado como componente fora do grupo #links do Core | Contrato de renderização a conferir: Core coleta grupos #links. Provar render final e corrigir a estrutura. |
| COM-12 | Formulário salvo retorna por redirect e pode respeitar destination | Controlar o retorno; ignorar destination no fluxo contextual. Testar parâmetros manipulados. |

COM-09 e o efeito final de COM-11 exigem reprodução antes de declarar exploração,
falha visual universal ou perda de acesso. Preservar cacheabilidade também em
resultados negados; não registrar apenas os metadados do caminho permitido.

## 4. Arquitetura proposta

### Contexto e backend neutro

Reutilizar DiscussionContextProviderInterface e DiscussionContextResolver.
Não criar resolvedor específico de página no tema. A seleção do provider não
pode ser controlada livremente pelo cliente.

Planejar serviços (nomes sujeitos à revisão de código, ainda não implementados):

- DiscussionReplyAccess: host, campo, pai, estado aberto e acesso contextual.
- DiscussionReplyFormBuilder: composição com Form API e form modes existentes.
- DiscussionReplyUrlBuilder: URLs do hospedeiro e fallback de mesma página.
- DiscussionCommentPermalinkBuilder: pager/idioma/fragmento do comentário.
- Adapters de escrita Comment e ForumPost, escolhidos pelo contexto no servidor.

Parent, tipo de entidade, campo, idioma e hospedeiro devem permanecer associados
no FormState. Revalidar no GET e no POST; nunca confiar apenas em campos hidden,
UUID, atributos HTML, existência do formulário ou cabeçalho HX-Request.

CommentForm é marcado @internal no upstream consultado. Preferir extensão por
hooks e composição da Entity Form API; não substituir globalmente o handler nem
copiar CommentForm inteiro. Um experimento focal deve confirmar processamento
POST dentro do lazy builder, prévia e rebuild antes de fechar o adapter.

### Formulário e transporte

Uma caixa ativa por discussão; duas discussões na mesma página não compartilham
formulário, editor, token ou alvo. Não renderizar 50 formulários/editores por página.
Reutilizar o lazy builder/placeholder para preservar cache da página hospedeira.

HTMX nativo do Core é a direção do tema. Usar as APIs e bibliotecas da versão
instalada, render arrays e processamento de attachments do Drupal. Não importar
outra versão de HTMX nem substituir por fetch + innerHTML sem ciclo Drupal.

Abrir e enviar atualiza apenas o alvo do compositor. Não emitir HX-Redirect,
HX-Location nem redirect HTTP que tire o usuário da página. Evitar push de rota
de formulário para o histórico. A resposta de validação deve preservar dados,
erros, token e ação acionada (prévia/enviar); conferir o tratamento de HTTP 422
na versão instalada em vez de presumir que todo status seja trocado no DOM.

O formulário deve usar setIgnoreDestination e controlar disableRedirect/
setResponse conforme a integração suportada. A ação do formulário e seus rebuilds
precisam preservar o endpoint/contexto correto. Não basta alterar o href.

Fallback sem JavaScript: GET na própria URL hospedeira com parâmetros em
português seleciona pai e modo; POST apresenta erros/prévia na mesma página.
No sucesso, PRG apenas para a mesma página, com paginação atual preservada.
Nunca usar comment.reply ou /publicacao/nova como fallback de navegação da ação.

Se a nova resposta estiver fora da página atual, mostrar confirmação local e
link explícito para vê-la; não forçar salto a outro pager. Resposta pendente de
moderação não aparece como publicada nem altera contagens públicas.

### SDC e experiência

Criar discussion-reply-composer no aculta420_v2 com schema estrito, story,
tokens semânticos e slots: contexto, mensagens e formulário Drupal.
Definir props mínimas de identificação/rótulo/estado; não passar entidades como props.
O módulo fornece um theme hook neutro com fallback funcional; o tema encaixa SDC.
Documentar em components.md, architecture.md, roadmap e changelog do tema.

Responder mostra autor/contexto autorizado e permite cancelar. Citar usa o
mesmo compositor e ForumQuoteBuilder. Abrir outro pai preserva o texto digitado;
se houver conflito, pedir decisão local na interface. Sem autosave remoto nesta fase.
Rascunho em armazenamento persistente do navegador é backlog: conteúdo LMS
privado não deve ir automaticamente para localStorage em dispositivo compartilhado.

Estados: fechado, carregando, editando, prévia, inválido, enviando, enviado,
aguardando moderação, acesso revogado, sessão expirada e falha de rede.
Erro não apaga o texto. Resposta tardia não substitui um contexto mais recente.
Cancelar antes do envio aborta a carga; após envio, não prometer desfazer gravação.
Foco vai ao editor ou resumo de erro; fechamento devolve foco ao gatilho.
Mensagens usam região de status acessível; não aplicar aria-live ao thread inteiro.
Respeitar prefers-reduced-motion para rolagem, sem introduzir tema de cor por media query.

Preservar attributes do comentário, estados, contexto do pai e autoria entregue
por aculta_user. Não resolver identidade/autoria histórica lendo User no Twig.
Botões de envio preservam name/value e handlers Form API; não trocar submit por
link SDC. Corrigir a documentação do discussion-comment para seus consumers reais.

## 5. Segurança, concorrência e integrações

- GET abre formulário e nunca salva conteúdo.
- Manter CSRF, CAPTCHA/Turnstile aplicável, filtros, acesso ao formato e validação
  de upload dos owners; testar rebuild e sessão expirada.
- Host e pai devem coincidir em tipo, ID, campo e contexto; pai não publicado,
  removido ou inacessível não recebe resposta por ID manipulado.
- LMS revalida Group, matrícula/curso/turma e Entity Access em cada operação.
- Preservar Domain/purpose, idioma e prefixos de idioma; nenhum POST cross-purpose.
- Negar escrita após fechamento da discussão ou perda de acesso entre GET e POST.
- Reutilizar locks existentes. Não manter transação ou lock durante edição humana.
- Provar envio duplicado por requisições concorrentes e retry após timeout.
  Desabilitar botão sozinho não é idempotência. Se o Core/fluxo atual não cobrir,
  desenhar chave por intenção/conta/contexto com unicidade persistente, transação
  e recuperação do resultado; não usar apenas chave em memória ou conteúdo igual.
  A mesma chave com payload divergente deve ser rejeitada. Documentar retenção.
- Não implementar tabela própria de idempotência antes desse diagnóstico.
- Publicação/moderação conserva eventos existentes para Messenger, Index,
  estatísticas e CGU; um retry não duplica avisos nem métricas.
- Reutilizar aculta_file_uploader, aculta_image, Gallery e Document Viewer.
- Formulário/token/rascunho/resposta privada não entram em cache público.
  Bolhar metadados de grants, domínio, idioma e estados negados.
- Não zerar cache da página inteira como solução permanente. Medir HTML,
  número de editores, queries e tempo na mesma fixture antes/depois.

## 6. URLs e compatibilidade

Inventário inclui Core /comment/reply, /comment/{id}/edit e /delete, parâmetro
quote, query post e fragmentos forum-post-{id}. As rotas próprias existentes
/publicacao/nova e /publicacao/editar já usam português.
O segmento forum é a forma ASCII de fórum; não constitui inglês por si só.

Ações de resposta passam a usar URL hospedeira/parâmetros em português;
endpoints novos próprios também serão em português. Preservar route IDs.
Não renomear fragmentos históricos sem mapear seus consumidores em pesquisa,
Messenger, History, Index e links já publicados. Traduzir caminhos internos
próprios; rotas Core/contrib só mudam por extensão suportada e acesso preservado.
A existência de URL legada não autoriza redirect legado. Nenhuma nova navegação
de resposta aponta para a rota standalone bloqueada por D-65.

## 7. Microfases e evidências

| Fase | Entrega | Critério de saída | Estado |
| --- | --- | --- | --- |
| C02-00 | Inventário, decisões, pesquisa e plano | Fontes, bugs e dependências documentados | Documentada nesta entrega |
| C02-01 | Registro central dos providers, incluindo Wiki | Container com/sem Wiki; um provider por host; revisão dos contratos | Pendente |
| C02-02 | Atributos, autoria e permalinks | Âncoras reais, pager correto, sem link humano standalone | Pendente |
| C02-03 | Experimento Form API + HTMX | POST, prévia, erros, attachments e fallback na mesma página | Pendente |
| C02-04 | Backend compartilhado Comment | Revista, Wiki, LMS e Comment do Fórum com pai correto e acesso revalidado | Pendente |
| C02-05 | SDC e citação unificada | Formulário acessível, sem dependência do tema no módulo e sem JS global antigo | Pendente |
| C02-06 | Adapter ForumPost | Resposta geral e por pai inline, revisões/moderação e dois pagers preservados | Pendente |
| C02-07 | URLs, concorrência e integrações | Slugs, destination, retry/duplo envio e eventos cobertos | Pendente |
| C02-08 | Homologação integrada | Matriz autenticada, visual e regressão focal aprovada | Pendente |

C02-03 é prova técnica para fechar o mecanismo, não autorização para manter
dois caminhos concorrentes. Resolver falhas encontradas antes das fases consumidoras.
Cada microfase atualiza TODO, status, changelog e evidências sem declarar PASS presumido.
Pendências 0.1 (D-65 HTTP, migração, F-M3/F-M6/F-M7) continuam registradas e
devem ser conciliadas no aceite; não iniciar migração de dados por causa deste plano.

## 8. Matriz de aceite obrigatória

- Revista, Wiki, LMS e Fórum; Comment e ForumPost onde suportados.
- Visitante, autor, outra conta, moderador e acesso revogado; UID 1 não basta.
- Resposta principal, resposta a comentário, citação, prévia, inválido, pendente,
  discussão fechada, pai removido e formato não autorizado.
- Sem JavaScript, HTMX, teclado e leitor de tela; foco, zoom 200%, claro/escuro
  em 320, 390 e 1440 CSS px com tema ACULTA ativo.
- Duas discussões por página, múltiplos editores, editor em carregamento,
  rede lenta/offline, sessão expirada, retry e envio concorrente.
- CKEditor e textarea; anexo/galeria, CAPTCHA e seus rebuilds.
- Pager Comment 0 e ForumPost 1; locale, URL com filtros e destination malicioso.
- Provar ausência de navegação principal nos cliques e no envio: observar URL,
  requisições e resposta do servidor, além da aparência da caixa.
- Verificar storage do pai, host e campo após salvar; uma caixa inline com
  associação incorreta falha no aceite.
- Testes Unit/Kernel de contexto, acesso, cache e idempotência; testes funcionais
  dos forms e navegador para DOM/HTMX/editor. Somente gates pertinentes.
- Capturas seguem docs/operations/NAVEGADOR-TESTES.md; manifest no Git,
  PNGs fora do Git. Não alternar para Olivero.
- Código/runtime funcional nesta entrega documental: DEFERRED.

## 9. Pesquisa adicional e fontes primárias

Consulta: 2026-11-08. Fontes upstream não comprovam o estado do Runtime ACULTA.

1. CommentLazyBuilders (Drupal 11.x):
   https://github.com/drupal/drupal/blob/11.x/core/modules/comment/src/CommentLazyBuilders.php
   Form inicial usa pid NULL; renderLinks organiza grupos #links e comment-reply.
2. CommentForm (Drupal 11.x):
   https://github.com/drupal/drupal/blob/11.x/core/modules/comment/src/CommentForm.php
   Controla action, preview, save e redirects; classe interna do Core.
3. FormStateInterface:
   https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Form%21FormStateInterface.php/interface/FormStateInterface/11.x
   APIs disableRedirect e setResponse precisam integrar-se ao fluxo de formulário.
4. Ignorar destination:
   https://www.drupal.org/node/3375113
   Usar setIgnoreDestination no fluxo contextual; testar o comportamento.
5. Cache em POST:
   https://www.drupal.org/node/3420901
   Não presumir POST globalmente sem render cache; lazy builders isolam formulários.
6. Auto-placeholdering:
   https://www.drupal.org/docs/drupal-apis/render-api/auto-placeholdering
   Manter cache da entidade hospedeira e isolar o fragmento pessoal.
7. Cacheabilidade:
   https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays
   Metadados incluem dependências e resultados de acesso, inclusive negações.
8. Formulários podem optar por render cache:
   https://www.drupal.org/node/3442559
   Nesta fase não optar por cache de formulário/token sem análise específica.
9. HTMX no Core:
   https://www.drupal.org/docs/develop/drupal-apis/htmx
   Preferir integração nativa e processamento de libraries/attachments.
10. Teste upstream de formulário dinâmico:
    https://github.com/drupal/drupal/blob/11.x/core/tests/Drupal/FunctionalJavascriptTests/Core/Htmx/HtmxDynamicFormTest.php
    Referência de comportamento; adaptar à suite ACULTA sem importar tema de teste upstream.
11. Template de comentário:
    https://api.drupal.org/api/drupal/core%21modules%21comment%21templates%21comment.html.twig/11.x
    Preservar atributos, estados e contexto de pai.
12. Link::preRenderLinks:
    https://github.com/drupal/drupal/blob/11.x/core/lib/Drupal/Core/Render/Element/Link.php
    Conferir o contrato #links antes de inserir componente de ação.
13. PublicRouteSlug/política do projeto:
    docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md (a partir da raiz).
    Esta fase acrescenta a decisão explícita de português para endpoints próprios internos.

Questão adicional a reproduzir na versão instalada: ordem de carregamento de
assets HTMX e attach de Drupal behaviors, sobretudo CKEditor e uploads.
Há relatos na fila upstream; a presença de relato não prova bug no ACULTA.
Registrar versão exata, reprodução e workaround limitado antes de aplicar patch.

## 10. Fora da primeira entrega funcional

Autosave remoto, armazenamento persistente de rascunhos, menções novas,
reações novas, chat, alteração de editor e migração Comment -> ForumPost
não são pré-requisitos para a resposta inline. Avaliar como backlog após
estabilidade e privacidade. Acessibilidade, moderação e acesso são requisitos
da primeira entrega, não melhorias adiadas.

## 11. Execução pelos agentes

1. [Agente 1 - saneamento da base](PROMPT-AGENTE-1-SANEAMENTO-0.2.0.md):
   C02-01/C02-02 e correções independentes do novo compositor, com handoff.
2. [Agente 2 - implementação e homologação](PROMPT-AGENTE-2-IMPLEMENTACAO-0.2.0.md):
   depois da entrega do agente 1, concluir C02-03 a C02-08 e pendências
   predecessoras efetivamente abertas.

Os agentes executam em sequência no escopo compartilhado. COM-01/COM-05 e
o controle completo de retorno COM-12 são parte da implementação e não podem
ser declarados resolvidos apenas no saneamento prévio. A divisão não altera
o critério final: respostas e citações permanecem na página hospedeira.
