# Agente 2 - executar todas as fases restantes dos comentários compartilhados

Você é o agente executor responsável por concluir o ciclo funcional 0.2.0-dev
do sistema de comentários ACULTA, da prova técnica até a homologação integrada.

Repositório: https://github.com/coletivo420/aculta
Branch: dev. Sem nova branch, PR, merge em main ou force push.
Plano obrigatório:
https://github.com/coletivo420/aculta/blob/dev/web/modules/custom/aculta_portal/modules/aculta_forum/docs/COMENTARIOS-0.2.0-PLANO.md
Caminho: web/modules/custom/aculta_portal/modules/aculta_forum/docs/COMENTARIOS-0.2.0-PLANO.md

## Condição de início e autorização

Este prompt deve ser executado APÓS a entrega do agente 1, pois os mesmos
arquivos/contratos são tocados. Não trabalhar em paralelo no escopo compartilhado.
Leia o handoff publicado pelo agente 1 e consulte origin/dev atualizado.
Não presumir estado a partir dos hashes da conversa ou dos nomes das fases.

Você está autorizado a implementar, corrigir bugs relacionados, criar e executar
testes necessários, atualizar documentação, fazer commits e push em dev.
Execute C02-03 a C02-08 integralmente. Se C02-01/C02-02 tiverem algum item
incompleto ou regressão relevante, conclua-o antes da fase dependente.
Não pare após o experimento nem entregue só código de demonstração.

Não executar migração Comment -> ForumPost, apagar conteúdo existente,
importar configuração, rodar updatedb ou desinstalar módulos sem autorização
aplicável. Testes usam banco isolado e fixtures próprias conforme o projeto.
Uma necessidade de schema ou configuração deve ter código, documentação,
testes isolados e procedimento operacional preparados antes de pedir a decisão
que realmente faltar. Continue frentes independentes.

## Regras e decisões invariáveis

Leia AGENTS da raiz/Portal/Fórum/Wiki/tema, TODOs, handoff, plano completo,
arquitetura do tema, ambientes e gates pertinentes. Preserve trabalho paralelo.
Respeite arquitetura ativa: aculta420_v2 com SDC, tokens e HTMX nativo do Core.
Não adicionar Bootstrap, jQuery, outra versão de HTMX ou novo #ajax/use-ajax no V2.

O aculta_forum registra e resolve os providers de Revista, Wiki, LMS e Growroom420.
A Wiki mantém entidades, workflow e Entity Access; seu provider é central no Fórum.
Não criar resolvedores no tema nem registrar o provider Wiki de volta na Wiki.
Outros módulos aderem pelo contrato central, com regras explícitas e teste.
Um host não pode corresponder a dois providers.

Responder e citar permanecem na página atual durante abertura, prévia, validação,
erros, envio e confirmação. O novo fluxo não usa comment.reply nem
/publicacao/nova como fallback de navegação. Sem redirects para outra página.
D-65 continua bloqueando canonical standalone de Comment.
Storages e workflows Comment/ForumPost são preservados.

## C02-03 - prova técnica real de Form API + HTMX

1. Verifique APIs na versão instalada. Leia os pontos Core indicados no plano,
   sobretudo CommentLazyBuilders, CommentDefaultFormatter, CommentForm,
   FormStateInterface e exemplos/testes HTMX do Core.
2. Prove o ciclo completo: GET, construção do formulário, POST válido,
   POST inválido, prévia, rebuild, token, editor, anexo/galeria e sessão expirada.
3. Reutilize Entity Form API e hooks. CommentForm é @internal upstream:
   não copie a classe inteira, não substitua globalmente seu handler nem
   trate subclassing como contrato público estável.
4. Verifique processamento POST em lazy builder. Isole estado do formulário
   sem tornar toda a página sem cache. Callbacks lazy aceitam argumentos
   escalares/NULL e precisam cumprir o contrato de confiança do Core.
5. Configure action correto em todos os rebuilds; setIgnoreDestination e
   disableRedirect/setResponse conforme API e fluxo suportados.
6. Use a resposta HTMX e o processamento de attachments nativos. Verifique
   inicialização, detach/attach e ordenação de assets CKEditor.
7. Teste status de erro na versão instalada; não presumir que HTTP 422
   substitui o alvo automaticamente. Não trocar erro por falso sucesso.
8. Não emitir HX-Redirect/HX-Location/Location que tire o usuário da página.
   Não colocar a rota do formulário no histórico principal.
9. Converta a prova aprovada em código definitivo e testes; retire fixtures
   executáveis de demonstração e rotas sem consumidor real.
10. Atualize a decisão técnica no plano, com limites e evidências.

## C02-04 - backend compartilhado Comment

Implemente composição reutilizável, usando os contratos existentes e serviços
do plano quando fizer sentido; os nomes propostos não obrigam criar abstrações
sem consumidor. Nenhum serviço funcional depende de componente do tema.

- Valide contexto, host, campo, idioma e comentário-pai em GET e POST.
- O servidor escolhe provider e adapter; cliente não escolhe arbitrariamente
  o storage ou a política de acesso.
- Grave parent, host e campo corretos; não confiar em hidden/UUID como autorização.
- Revalide leitura/escrita, pai publicado, campo aberto, Entity Access, Domain,
  Group e matrícula/curso/turma LMS.
- Não construa um composer diferente por módulo. Conecte Revista, Wiki,
  LMS e Comment do Fórum ao mesmo mecanismo.
- Formulário geral e resposta ao pai devem preservar token e form state próprios.
- Uma caixa ativa por discussão, carregada sob demanda. Duas discussões na
  mesma página operam independentemente.
- Sem 50 formulários/editores renderizados antecipadamente.
- Falhas de leitura/escrita preservam metadados de acesso, não conteúdo privado.
- Fallback sem JS usa a própria página hospedeira com parâmetros em português;
  prévia e erros também ficam nela. Sucesso pode usar PRG para a mesma página.
- Preserve posição/pager atual. Se a nova resposta estiver em outro pager,
  ofereça confirmação e link explícito; não force navegação.
- Pendente de moderação não aparece como conteúdo publicado.

## C02-05 - SDC, citação e experiência completa

Crie discussion-reply-composer no tema V2 com schema estrito e stories reais.
O módulo entrega theme hook/template neutro funcional; o tema aplica o SDC.
Props pequenas para identificação/rótulo/estado; formulário/conteúdo por slots.
Não passar entidades, autorização ou conteúdo inteiro como props.

- Use cores, superfície e linguagem visual dos cartões atuais do Fórum,
  tokens semânticos, modo de cor existente e componentes de botão/campo.
- Preserve atributos do Drupal, IDs, autoria aculta_user e contexto do pai.
- Submits mantêm name/value e handlers Form API; não converter submit em link.
- Responder abre junto ao comentário selecionado e informa a quem se responde.
- Citar passa pelo mesmo fluxo e ForumQuoteBuilder, com texto filtrado,
  atribuição e limite de 1200 caracteres. Não copiar innerText como política alternativa.
- Remova o handler global antigo de forum-quote.js após migrar os consumers.
- Ações são identificadas por atributos explícitos; nada de heurística de href.
- Abrir outro pai preserva rascunho ou solicita decisão local ao usuário.
- Sem autosave remoto ou localStorage automático de conteúdo LMS privado.
- Trate fechado/carregando/editando/prévia/inválido/enviando/enviado/moderação/
  acesso revogado/sessão expirada/falha de rede.
- Erro não apaga texto. Resposta tardia não substitui outro contexto ativo.
- Cancelamento de carga aborta a requisição; não prometer cancelar gravação
  que já pode ter sido efetivada pelo servidor.
- Foco no editor ou resumo de erro; retorno ao gatilho ao fechar.
  Status acessível sem aria-live no thread inteiro; respeitar movimento reduzido.
- Suporte CKEditor e textarea, múltiplos editores e retorno após rebuild.
- Documente stories, props, slots, JS, biblioteca e consumer real no mesmo commit.
- Acessibilidade e fallback são parte da entrega funcional.

## C02-06 - adapter ForumPost

- Integre respostas gerais e por publicação ao mesmo compositor visual,
  preservando storage, revisões, moderação, ownership e Entity Access ForumPost.
- Substitua entradas de ForumPostThreadBuilder que navegam para /publicacao/nova.
- Reuse o formulário e form mode existentes por composição suportada.
- Pai precisa pertencer ao tópico, ser acessível e cumprir regras de publicação.
- Trate envio pendente e resposta ao pai fora da página atual.
- Preserve Comment pager 0 e ForumPost pager 1, ordem de thread e query completa.
- Não converta comentários contextuais de Revista/Wiki/LMS em tópicos públicos.
- Não migre Comments para ForumPost nem unifique os storages nesta fase.
- Audite edição e retornos relacionados para evitar regressão, sem redesign alheio.

## C02-07 - URLs, concorrência e integrações

URLs:
- Caminhos humanos e endpoints próprios internos em português, ASCII,
  minúsculas e hífens. Audite reply/edit/delete, quote, post e fragments.
- Preserve route IDs, classes, serviços, campos e bundles estáveis.
- Não alterar fragmentos históricos sem mapear pesquisa/Messenger/History/Index.
- Sem aliases temporários e sem redirects legados. Não editar Core/contrib.
- Não gerar novos links humanos para /comment/{cid} standalone.
- Ignore destination manipulado; preserve idioma e filtros do hospedeiro.
- Nenhum POST entre purposes, proxy cross-domain ou host fixo no código.

Concorrência:
- Teste duplo clique, duas requisições concorrentes e retry após timeout
  quando o servidor já gravou e a resposta ao cliente se perdeu.
- Desabilitar botão não basta. Reutilize capacidade existente ou implemente
  idempotência por intenção/conta/contexto, com unicidade e transação quando
  comprovadamente necessárias, conforme o plano e APIs instaladas.
- A mesma intenção com payload diferente é rejeitada; reenvio idêntico recupera
  resultado autorizado sem criar comentário/avisos/métricas duplicados.
- Não deduplicar por igualdade de texto: comentários iguais podem ser legítimos.
- Reutilize locks; não segurar lock/transação enquanto o usuário escreve.
- Se for indispensável schema novo, prepare update idempotente, rollback seguro,
  testes isolados e procedimento; aplicação no Runtime exige autorização aplicável.

Integrações:
- Preservar eventos de publicação/moderação para Messenger, Index, estatísticas e CGU.
- Provar que pendentes não vazam em busca, contadores ou notificações públicas.
- Reutilizar upload/imagem/galeria/documentos dos owners, sem fluxo paralelo.
- Testar CSRF, formato não autorizado, MIME/tamanho e CAPTCHA/rebuild.
- Cache público não contém token, rascunho ou resposta privada. Metadados
  incluem negações e dependências de usuário/domínio/Group.
- Medir HTML, queries, editores e tempo com fixture comparável; não zerar
  cache de todo o hospedeiro como solução.

## C02-08 - homologação e fechamento de todos os achados

Monte e execute a matriz da seção 8 do plano e feche COM-01 a COM-12.
Cubra no mínimo:
- Revista, Wiki, LMS e Growroom420; Comment e ForumPost nos contextos suportados;
- visitante, autor, outra conta, moderador e acesso revogado; UID 1 não basta;
- pai correto no storage, campo/host, resposta geral e aninhada, citação,
  prévia, erro, moderação, discussão fechada, pai removido/inacessível;
- JS/HTMX e sem JS, teclado, leitor de tela, foco e resumo de erro;
- 320/390/1440 CSS px, claro/escuro, zoom 200%, tema ACULTA ativo;
- duas discussões na página, múltiplos editores e assets carregados sob demanda;
- rede lenta, resposta fora de ordem, offline, sessão expirada e retry;
- anexo, galeria, CAPTCHA, paginação dupla, locale, query e destination;
- concorrência real com processos/requisições independentes quando alegar cobertura.

Comprove ausência de navegação observando URL e rede nas ações de abrir,
pré-visualizar, enviar inválido e enviar válido. Inspecione também o registro salvo.
Uma caixa inline que salva pid errado ou perde uma resposta falha no aceite.

Use Unit/Kernel/Functional existentes e adicione cobertura significativa onde faltar.
Execute os gates pertinentes: DI/YAML/PHP, PHPCS, slugs, fronteira módulo-tema,
SDC schema/render, JS/CSS, status index e regressões dos consumidores afetados.
Não execute gates globais irrelevantes; documente falhas preexistentes com evidência.
Não alterne para Olivero. Capture no tema ativo e registre manifest no Git,
com imagens fora do Git conforme procedimento do projeto.

## Entrega, documentação e Git

- Revalide origin/dev e trabalho paralelo a cada entrega.
- Faça commits atômicos em português com trailer Claude-Session real, sem
  Co-Authored-By; push confirmado após cada entrega, sem force push.
- Atualize plano, tabela COM, roadmap, TODO, changelog, manifesto e status
  dos componentes afetados. Incremente 0.2.0-dev.N conforme estado remoto.
- Atualize docs do tema: components, stories, architecture, gates e roadmap.
- Gere índice raiz pelo script oficial e revise o diff.
- Marque fase concluída apenas com critérios/evidências; não confunda
  código commitado, teste isolado e homologação Runtime.
- Sincronize código e reconstrua cache no desenvolvimento conforme regras.
  Não aplicar DB/config/update/migração fora da autorização existente.
- Se um bloqueio operacional real impedir um cenário, entregue reprodução,
  comando pendente, impacto e critério de conclusão; prossiga em trabalho independente.
  Nunca transforme DEFERRED em PASS nem promova RC com aceite obrigatório aberto.
- Ao fechar, atualize ESTADO-DA-SESSAO com até três entradas e preserve histórico.

Relatório final: hashes publicados, fases concluídas, matriz COM-01 a COM-12,
testes/resultados, evidências de navegador, estado Runtime, pendências reais
com motivo e próximos comandos estritamente necessários.
Não encerre após uma única microfase; conclua todas as fases restantes possíveis
e a homologação, sem esconder bloqueios ou inventar aprovação.
