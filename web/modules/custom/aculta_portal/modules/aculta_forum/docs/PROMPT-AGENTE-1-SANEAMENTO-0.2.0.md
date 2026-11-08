# Agente 1 - saneamento da base de comentários antes do novo compositor

Você é o agente executor responsável por corrigir a base atual do sistema de
comentários compartilhados ACULTA e preparar uma entrega verificável para o agente 2.

Repositório: https://github.com/coletivo420/aculta
Branch obrigatória: dev. Não criar branch, PR ou merge em main.
Referência documental inicial: 1dc800a8f9d01eca89e6b6a8c09478fdcfcdb191.
Esse hash é referência; trabalhar sobre origin/dev atualizado, preservando commits paralelos.

Plano normativo:
web/modules/custom/aculta_portal/modules/aculta_forum/docs/COMENTARIOS-0.2.0-PLANO.md
https://github.com/coletivo420/aculta/blob/dev/web/modules/custom/aculta_portal/modules/aculta_forum/docs/COMENTARIOS-0.2.0-PLANO.md

## Missão e limite entre agentes

Execute C02-01 e C02-02 e corrija todos os defeitos reproduzidos da base atual
que possam ser resolvidos sem criar o novo compositor inline.
Inclua contrato neutro de ações, segurança da citação, cache e seletores atuais.
O agente 2 executará C02-03 a C02-08 e fechará os achados estruturais restantes.

COM-01, COM-05 e a eliminação completa de redirects do novo fluxo em COM-12
dependem da implementação do compositor. Reproduza-os, documente-os e atribua
explicitamente ao agente 2; não os declare corrigidos por trocar um href.
Os outros achados devem ser examinados, corrigidos e testados no escopo atual.
Não marque o ciclo 0.2 concluído ao entregar este saneamento.

Você está autorizado a editar código e documentação do escopo, escrever e executar
testes pertinentes, corrigir novos bugs diretamente relacionados e fazer commit/push.
Não pare depois do diagnóstico, nem entregue apenas outro plano.

## Leitura e preparação

1. Leia AGENTS.md da raiz, do Portal, Fórum, Wiki e tema V2; TODO da raiz e dos
   componentes tocados; até três entradas de docs/operations/ESTADO-DA-SESSAO.md.
2. Leia o plano completo, a política FRIENDLY-PORTUGUESE-SLUGS, os gates do escopo,
   ambientes e instruções de teste de navegador.
3. Confira git status, worktrees e origin/dev. Identifique o checkout de trabalho
   e o servido antes de editar. Preserve alterações de terceiros; não use reset,
   stash global, git add indiscriminado nem force push.
4. Confirme versões no composer.lock e APIs efetivamente instaladas. O plano
   inspecionou Core 11.5.0-beta1; não presumir que continua igual.
5. Consulte código Core/contrib apenas nos pontos necessários. Não os edite.
6. Identifique testes existentes de contexto, Wiki, links, citação, permalinks,
   cache, Comment e ForumPost antes de adicionar novos.
7. Confirme a configuração ativa por leitura. Config Sync não prova estado Runtime.

## A. Registro central dos providers - C02-01

- O aculta_forum é o proprietário do registro dos providers de Revista, Wiki,
  LMS e Growroom420.
- Mova WikiDiscussionContextProvider de aculta_wiki para
  aculta_forum/src/Context/Provider e registre em aculta_forum.services.yml.
- Remova o registro antigo em aculta_wiki.services.yml e atualize referências
  reais, testes, imports e documentação. Evite classe órfã e provider duplicado.
- O provider reconhece node:wiki_entry e field_aculta_discussion pelas APIs
  genéricas; não importar classes da Wiki nem criar dependência Fórum -> Wiki.
- A Wiki continua proprietária de verbetes, campos, workflow e Entity Access.
- Preserve o contrato DiscussionContextProviderInterface e o resolver existentes.
- Garanta um único provider por hospedeiro; contexto não suportado falha fechado.
- Prove em fixtures isoladas container com e sem Wiki e descoberta dos quatro
  contextos. Não desabilite módulos no Runtime para realizar esse teste.
- Examine fechamento do campo, permissões, Domain e Group. Não reutilize
  autorização de criação de ForumPost para liberar Comment indiscriminadamente.

## B. Atributos, identidade e permalinks - C02-02

- Corrija os templates comment--field-aculta-discussion e comment--comments,
  o forum-post-card e o adapter PHP estritamente necessários.
- Preserve attributes do Drupal: ID, data-comment-user-id, classes, estados
  publicado/pendente/prévia e atributos de acessibilidade.
- Reencaminhe atributos de modo explícito compatível com o schema SDC.
  Não converta arbitrariamente objetos e render arrays em props sem schema.
- Preserve contexto do pai e autoria resolvida pelo aculta_user, inclusive
  autores históricos. Não leia User diretamente no Twig para contornar o builder.
- Permalinks humanos devem apontar ao hospedeiro, idioma, pager e âncora corretos.
- D-65 permanece: /comment/{cid} standalone retorna 404. Não reabilite a rota.
- Confira Comment pager 0 e ForumPost pager 1. Não substitua toda a query de
  paginação por um inteiro nem perca filtros necessários ao hospedeiro.
- Audite consumidores em pesquisa, Messenger, History e Index antes de mudar
  fragmentos existentes. Não quebre links publicados para traduzir um ID do DOM.
- Corrija README/story/consumer declarados de discussion-comment conforme
  os templates realmente usados. Não mantenha documentação de consumer inexistente.

## C. Saneamento dos achados COM-02/03/07/08/09/10/11

Reproduza cada caso antes de atribuir causa definitiva e registre resultados.

1. forum-quote.js:
   - O handler atual copia texto no formulário geral e cancela navegação sem
     estabelecer pid. A citação não pode produzir uma resposta com pai incorreto.
   - Elimine essa interceptação insegura. No fluxo existente, só intercepte se
     o servidor já tiver construído um formulário validado para o pai/contexto.
     Caso contrário preserve o fluxo servidor já existente, sem inventar novo
     fallback ou declarar que a resposta inline final foi entregue.
   - Não atribua pid confiando apenas no DOM ou em hidden editável.
   - Remova seleção global de form#comment-form. Use escopo por discussão,
     associação explícita e IDs únicos; suporte dois contextos na mesma página.
   - Preserve texto já digitado, seleção e editor correto. Não duplique listeners.
2. Citação:
   - Use ForumQuoteBuilder como política única, inclusive limite de 1200
     caracteres, filtragem do conteúdo e atribuição de autor autorizado.
   - Não copie innerText do cartão como fonte alternativa sem a mesma política.
   - Não injete HTML bruto de fonte não filtrada.
   - Verifique acesso ao pai, publicação, host, campo, idioma e contexto.
3. Ações e desacoplamento:
   - Retire a instanciação direta de aculta420_v2:button do módulo.
   - Gere ações Drupal neutras, com metadados; o tema aplica o SDC.
   - Reproduza render de Citar dentro da estrutura #links do Core.
     Não presumir que um componente arbitrário é coletado por preRenderLinks.
   - Garanta ação visível quando permitida, ausente quando negada e render
     neutro funcional em fixture sem depender de classes do tema.
4. Autorização e cache:
   - Consulte leitura E escrita do contexto, estado aberto do campo e criação
     efetiva para o tipo de resposta. Revalide no servidor.
   - Teste discussão fechada, acesso revogado e fonte inacessível.
   - Propague metadados também para resultados negados. Examine vazamento por
     cache entre duas contas, domínio, Group e usuários autenticados/anônimos.
5. Seletores:
   - Substitua detecção de ação por literais /comment/reply ou ?quote= por
     atributos semânticos de ação/alvo, sem transferir autorização ao navegador.
   - Preserve rótulos acessíveis, foco e área de toque dos botões.
   - Retire CSS obsoleto apenas depois de identificar os consumidores.

## D. Pendências anteriores e preparação estrutural

- Revalide HTTP de D-65 após rebuild de rotas no ambiente permitido.
- Audite por leitura as pendências 0.1 e F-M3/F-M6/F-M7 relevantes ao novo fluxo.
  Registre dependências reais; não execute migração Comment -> ForumPost.
- Reproduza action do formulário principal, prévia, erro e destination manipulado.
- Registre como COM-01/05/12 serão fechados por C02-03/04/06/07.
- Não implemente tabela própria de idempotência nesta preparação. Levante se
  o fluxo atual aceita requisições duplicadas e entregue o resultado ao agente 2.
- Examine forms serializáveis e dependências em rebuild de anexo/galeria;
  corrija apenas defeitos relacionados comprovados.
- Não transforme ausência de teste em prova de ausência do bug.

## E. Validação, documentação e Git

- Escreva e execute testes significativos: container/providers, host/campo/pai,
  atributos/âncoras, render real de ações, autoria, acesso negado/cache e citação.
- Faça Kernel/Functional no banco isolado previsto pelo projeto; fixture de
  teste pode criar dados próprios e limpar somente o que criou.
- Use apenas tema ACULTA ativo em validação visual; não habilite Olivero.
- Faça navegador autenticado com contas distintas, não apenas UID 1.
- Valide Revista, Wiki, LMS e Fórum; Comment e ForumPost onde tocados.
- Revalide lint, DI/YAML, PHPCS e gates pertinentes ao diff. Execute o gerador
  de status com --write e confira o diff; não edite o índice raiz às cegas.
- Atualize plano com tabela COM-01 a COM-12: evidência, correção/commit, teste,
  estado e fase responsável. Distinguir corrigido, não reproduzido e pendente.
- Atualize TODO, status YAML, README/roadmap/changelog dos componentes tocados.
  Tema também exige components/architecture/gates quando aplicáveis.
- Incremente 0.2.0-dev.N segundo o estado real de origin/dev e política dos
  componentes. Não atribua versão pronta a código sem validação exigida.
- Commits atômicos em português, trailer Claude-Session com ID real da sessão,
  sem Co-Authored-By; push para origin/dev após cada entrega.
- Se origin/dev avançar, integre sem reescrever trabalho alheio e revalide
  o escopo afetado. Sem force push.
- Após push, sincronize código do Runtime de desenvolvimento e reconstrua
  cache conforme AGENTS e infraestrutura disponíveis. Não execute updatedb,
  config:import, uninstall, migrações ou exclusões de dados sem autorização.
  Registre bloqueio operacional sem parar correções independentes.
- Não trate testes isolados como homologação de escrita no Runtime.

## Entrega obrigatória ao agente 2

Registre no GitHub um handoff no diretório docs do Fórum contendo:
- HEAD publicado, arquivos, mudanças e comandos/resultados;
- estados de C02-01/C02-02 e COM-01 a COM-12 com evidências;
- contratos efetivos, IDs de serviços, metadados e extensões usadas;
- versões Core/HTMX/editor e problema de assets reproduzido ou não;
- resultado de testes de concorrência que foram possíveis;
- pendências estruturais atribuídas a C02-03 a C02-08;
- status Runtime e DEFERRED detalhados.

Atualize ESTADO-DA-SESSAO mantendo no máximo três entradas e preservando
as antigas no histórico. Entregue link do handoff e hashes confirmados em dev.
Não encerre com correções pendentes de sua alçada apenas por falta de tempo.
