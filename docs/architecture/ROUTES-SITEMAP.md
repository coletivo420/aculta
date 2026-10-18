# Mapa de caminhos do ACULTA

Este documento é o índice operacional dos caminhos HTTP do projeto: páginas
públicas, conta, administração, endpoints técnicos, caminhos removidos e
redirecionamentos. Ele complementa o sitemap XML de indexação; não o substitui.

## Convenções e fonte de verdade

- Identificadores de host usam a forma `*.aculta`, sem hostname de ambiente:
  `main.aculta`, `conta.aculta`, `apoio.aculta`, `loja.aculta` etc. Isso cobre
  produção (`*.aculta.org`) e Homelab (`*.aculta.toca.net.br`). Consulte
  [multidomínio](multidomain.md) para o mapeamento completo.
- Todo caminho começa com `/`. Parâmetros variáveis aparecem entre chaves, como
  `/pedidos/{pedido}`. `/{alias}` indica alias editorial dinâmico e não uma
  página fixa.
- Cada entrada classifica o caminho como **ativo**, **condicional** (depende de
  módulo, conteúdo, autenticação ou configuração), **interno** (não navegável
  diretamente), **redirecionado** ou **desabilitado/404**. Não inferir estado
  ativo apenas porque há uma definição de rota no código.
- Rotas de entidades e aliases de conteúdo são dinâmicos: documentar o padrão e
  a regra de acesso, sem copiar conteúdo de banco para este arquivo. Inventários
  de aliases concretos, quando necessários, devem ser gerados por ambiente e
  não versionados como se fossem universais.
- Para mudança de rotas do Portal, confira também
  [slugs públicos](../portal/FRIENDLY-PORTUGUESE-SLUGS.md),
  [raízes de domínio](../portal/DOMAIN-FRONT-PATHS.md),
  [redirecionamentos](../portal/REDIRECTS.md) e
  [política administrativa](../portal/ADMIN-DOMAIN-POLICY.md). Esses contratos
  detalham comportamento; este mapa dá a visão transversal.

## Limite de validação deste inventário

O checkout local consultado em 2026-10-18 não possui conexão de banco acessível
ao Drush (`route:list` informa que não pode consultar o banco). Portanto este
mapa registra caminhos e padrões identificados nas definições customizadas e na
documentação vigente, mas **a lista completa de rotas efetivamente habilitadas
no Runtime ainda precisa ser reconciliada**. Antes de declarar o inventário
completo para um ambiente, gere a lista de rotas com Drush nesse ambiente,
inspecione os caminhos derivados do conteúdo/aliases e marque as rotas
desabilitadas. Nunca use um caminho de teste como evidência de produção.

## Hosts e páginas iniciais

| Identificador | Raiz `/` | Estado e observação |
| --- | --- | --- |
| `main.aculta` | Página institucional principal | Ativo. Administração, Commerce (carrinho/checkout/pagamentos) e checkout pertencem a este purpose. |
| `conta.aculta` | Painel da conta; visitante recebe `302` para `/entrar` | Ativo e condicionado a autenticação/permissões. O caminho interno da visão geral não é público diretamente. |
| `apoio.aculta` | Formulário público de apoio | Ativo; caminho interno `/apoio` responde `404` diretamente. |
| `revista.aculta` | Início da revista | Ativo; aliases de conteúdo dependem dos nós publicados. |
| `wiki.aculta` | Início da wiki | Ativo; criação e edição dependem de autenticação/permissões. |
| `loja.aculta` | Vitrine da loja | Domain ativo; caminhos de produto dependem de conteúdo publicado. Carrinho e checkout vão para `main.aculta`. |
| `cursos.aculta` | Catálogo/início de cursos | Ativo; ações dependem de matrícula, acesso e conteúdo. |
| `forum.aculta` | Índice do fórum | Ativo; tópicos/respostas dependem de acesso e conteúdo. |
| `links.aculta` | Página pública de redes sociais | Código/configuração existente; provisionamento DNS de produção deve ser confirmado no documento de multidomínio. |

## Caminhos humanos ativos ou condicionais

Os padrões dinâmicos abaixo são ativos quando a rota, o módulo e o conteúdo
correspondentes estão habilitados no ambiente.

| Host | Caminho | Estado / finalidade |
| --- | --- | --- |
| `main.aculta` | `/` | Início institucional. |
| `main.aculta` | `/busca` e `/busca?q={termo}` | Busca; o envio do formulário redireciona para a forma com query. |
| `main.aculta` | `/carrinho` | Carrinho Commerce. |
| `main.aculta` | `/finalizar-compra/{pedido}/{etapa}` | Fluxo de checkout; protegido pelos requisitos do Commerce e do acordo. |
| `main.aculta` | `/apoiar` e `/apoiar/{valor}/agora` | Fluxo Commerce de apoio/doação, conforme configuração. |
| `main.aculta` | `/termos/aceitar` | Endpoint de aceite autenticado; `POST` processa e redireciona ao destino seguro. |
| `main.aculta` | `/painel-administrativo/**` | Administração Drupal central. Acesso continua sob permissões/access do Core e contrib. |
| `main.aculta` | `/painel-administrativo/perfil/editar` | Edição do perfil próprio no painel. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/redes-sociais` | Configuração administrativa de links sociais. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/cep` | Configuração administrativa do serviço CEP, se o submódulo estiver habilitado. |
| `main.aculta` | `/painel-administrativo/game` | Administração do jogo; exige permissão específica. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/aparencia`, `/painel-administrativo/configuracoes/aculta/apoio`, `/painel-administrativo/configuracoes/aculta/portal`, `/painel-administrativo/configuracoes/aculta/portal/turnstile`, `/painel-administrativo/configuracoes/aculta/portal/requisitos` | Configuração ACULTA do Portal, com permissões administrativas. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/segredos`, `/painel-administrativo/configuracoes/aculta/deployer` | Ferramentas de configuração/deploy; acesso restrito e credenciais continuam fora do mapa. |
| `main.aculta` | `/painel-administrativo/loja/vendas`, `/painel-administrativo/loja/vendas/pagamento/{commerce_payment}/atualizar`, `/painel-administrativo/loja/vendas/pagamento/{commerce_payment}/cancelar`, `/painel-administrativo/loja/doacoes` | Administração de vendas e doações, conforme módulos ativos e permissões. |
| `main.aculta` | `/painel-administrativo/contas/nao-confirmadas`, `/painel-administrativo/contas/{user}/confirmar-email` | Ferramentas administrativas de confirmação de conta. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/mensagens`, `/painel-administrativo/avisos`, `/painel-administrativo/avisos/temporarios`, `/painel-administrativo/avisos/persistentes`, `/painel-administrativo/avisos/persistentes/criar` | Administração de mensagens/avisos, condicional ao Messenger ativo. |
| `conta.aculta` | `/entrar` | Login. |
| `conta.aculta` | `/sair` | Logout; destino canônico retorna ao login. |
| `conta.aculta` | `/sair/confirmar` | Confirmação de logout do Core. |
| `conta.aculta` | `/recuperar-senha` | Recuperação de senha. |
| `conta.aculta` | `/criar-conta` | Cadastro, quando aberto/configurado. |
| `conta.aculta` | `/recuperar-acesso/{uid}/{timestamp}/{hash}/entrar` | Reset temporário de senha, com token válido. |
| `conta.aculta` | `/entrar/janela`, `/criar-conta/janela` | Formulários de login/cadastro em janela; fallback sem JavaScript é preservado. |
| `conta.aculta` | `/confirmar-email/reenviar` | Reenvio de confirmação de e-mail, conforme estado da conta. |
| `conta.aculta` | `/dados` | Meus dados. |
| `conta.aculta` | `/dados/endereco` | Endereço único de perfil/checkout. |
| `conta.aculta` | `/dados/outros-dados` | Condicional ao submódulo de identificação habilitado. |
| `conta.aculta` | `/foto` | Foto de perfil, conforme configuração da conta. |
| `conta.aculta` | `/seguranca` | Segurança e operações de conta. |
| `conta.aculta` | `/seguranca/senha`, `/seguranca/email`, `/seguranca/email/{user}`, `/seguranca/email/cancelar/{user}` | Senha e fluxo de troca/cancelamento de e-mail, autenticados e sujeitos à validação de token. |
| `conta.aculta` | `/conexoes` | Conexões OAuth/social, conforme provedores habilitados. |
| `conta.aculta` | `/meus-cursos` | Cursos do usuário, conforme integração/matrículas. |
| `conta.aculta` | `/meu-apoio` | Histórico de apoio do usuário. |
| `conta.aculta` | `/minha-participacao` | Área de participação, conforme módulos/permissões. |
| `conta.aculta` | `/pedidos` e `/pedidos/{pedido}` | Pedidos próprios; propriedade/access verificados pelo Commerce. |
| `conta.aculta` | `/meu-apoio/assinatura/{commerce_order}/{acao}` | Ação de assinatura de apoio, quando o fluxo estiver habilitado. |
| `conta.aculta` | `/configuracoes/mensagens` | Preferências de mensagem do Messenger; módulo opcional, requer conta autenticada. |
| `conta.aculta` | `/mensagens`, `/mensagens/notificacoes`, `/mensagens/avisos` | Caixa postal, avisos privados e comunicados; rotas do Messenger condicional ao módulo ativo e às permissões nativas. |
| `conta.aculta` | `/mensagens/resumo`, `/mensagens/conversas/painel`, `/mensagens/conversa/{private_message_thread}/responder`, `/mensagens/conversa/{private_message_thread}/opcoes`, `/mensagens/conversa/{private_message_thread}/relacao`, `/mensagens/conversa/{private_message_thread}/painel`, `/mensagens/nova`, `/mensagens/nova/buscar`, `/mensagens/nova/grupo/painel`, `/mensagens/nova/{user}/painel`, `/mensagens/perfil/{user}/relacao` | Subrotas do Messenger; condicionais ao módulo ativo, acesso à conversa e permissões nativas Private Message. |
| `conta.aculta` | `/avisos/resumo`, `/avisos/alerta/{site_alert}`, `/avisos/aviso/{node}`, `/avisos-mensagens`, `/avisos-mensagens/{aba}` | Rotas de avisos do Messenger/Site Alert; condicionais ao módulo e access. |
| `conta.aculta` | `/oauth/{provedor}` e `/oauth/{provedor}/retorno` | Início e callback técnico OAuth; provedores ativos variam por ambiente. |
| `apoio.aculta` | `/` | Formulário público servido na raiz. |
| `main.aculta` | `/{titulo}`, `/atividades/{titulo}`, `/projetos/{titulo}`, `/transparencia/documentos/{titulo}` | Aliases Pathauto do conteúdo institucional; cada endereço depende de conteúdo publicado e da política de domínio do nó. |
| `loja.aculta` | `/loja/produto/{categoria}/{titulo}` | Páginas de produto publicadas; fora do purpose loja respondem `404`. Padrão Pathauto vigente. |
| `revista.aculta` | `/{sessao}/{titulo}` | Artigos editoriais publicados, incluindo textos de blog; padrão Pathauto vigente e aliases concretos são conteúdo dinâmico. |
| `wiki.aculta` | `/verbete/{titulo}`, `/wiki/busca`, `/wiki/busca/previa` | Padrões de conteúdo/busca da Wiki; páginas e resultados dependem de conteúdo e permissões. `/wiki` é caminho interno da raiz e responde `404` direto. |
| `cursos.aculta` | `/curso/{alias}` | Detalhe conforme conteúdo/módulos ativos. `/cursos` é caminho interno da raiz e responde `404` direto. |
| `cursos.aculta` | `/resposta/{lms_answer}/baixar-arquivo` | Download protegido de anexo de resposta, com matrícula validada. |
| `forum.aculta` | `/topico/{titulo}` | Tópicos do fórum com alias Pathauto; conteúdo publicado e acesso determinam disponibilidade. `/forum` é caminho interno da raiz e responde `404` direto. |
| `links.aculta` | `/` | Página pública de links sociais; `/redes-sociais` é caminho interno e responde `404` direto. |
| `magazine.aculta` | `/u/{apelido}` | Perfil público de autor quando habilitado; apelidos não canônicos respondem `404`. |
| `magazine.aculta` | `/albuns/{alias}` | Condicional: `aculta_gallery` permanece desabilitado até validação e habilitação autorizada. |

## Rotas técnicas ativas

Estes padrões não são itens de navegação. Sua existência depende do módulo
correspondente e sua proteção deve ser mantida no dono da feature.

| Host | Caminho/padrão | Uso |
| --- | --- | --- |
| `main.aculta` | `/webhook/{...}` e callbacks Commerce/gateway | Callbacks técnicos de pagamento, conforme configuração do gateway. |
| `main.aculta` | `/admin/**` e endpoints Drupal técnicos | Administração/rotas técnicas que continuam protegidas por Core/contrib. O painel humano ACULTA usa `/painel-administrativo/**`. |
| `main.aculta` | `/aculta_portal_cep/{cep}` | Consulta AJAX de CEP; só com integração correspondente habilitada. |
| `main.aculta` | `/oauth/{provedor}/retorno` | Callback OAuth técnico, restrito aos provedores configurados. |
| `cursos.aculta` | `/resposta/{lms_answer}/baixar-arquivo` | Endpoint de arquivo protegido pela matrícula e access da resposta. |

O inventário técnico exaustivo deve ser preenchido a partir do roteador Drupal
do ambiente validado. Não copiar indiscriminadamente endpoints de todos os
módulos instalados: módulos desabilitados não expõem rotas ativas.

## Caminhos internos, desabilitados e removidos

| Caminho | Host | Estado / comportamento |
| --- | --- | --- |
| `/apoio`, `/wiki`, `/cursos`, `/loja`, `/forum`, `/redes-sociais` | respectivos purposes | Caminho interno usado para resolver a raiz; acesso direto responde `404`, sem 301. |
| `/minha-conta` | `conta.aculta` | Caminho interno da raiz de conta; direto responde `404`. A página pública é `/`. |
| `/conta-interna` | `conta.aculta` | Removido; `404` para visitante. |
| `/cart`, `/donate`, `/donate/{...}` | `main.aculta` | Removidos; `404`, sem redirecionamento de slug. |
| `/user/{uid}/orders`, `/user/{uid}/payment-methods` | `conta.aculta` | Caminhos com UID removidos; usar `/pedidos` e caminhos atuais. |
| `/user/{uid}/address-book*` | `conta.aculta` | `404`; endereço único fica em `/dados/endereco`. |
| `/pagamentos/novo`, `/pagamentos/{id}/editar`, `/pagamentos/{id}/excluir`, `/pagamentos/{id}/padrao` | `conta.aculta` | `404`; ACULTA não armazena nem exibe meios de pagamento pessoais. |
| `/aceite-termos` | todos | Rota antiga removida; `404`. O fluxo usa o popup e `POST /termos/aceitar`. |
| `/albuns/{titulo}` | `revista.aculta` | O código/configuração do submódulo prevê esse padrão. O estado efetivo do Runtime precisa ser confirmado; não inferir habilitação pelo YAML instalado. |
| Rotas `aculta_lms_h5p` | `cursos.aculta` | Desabilitadas; não registrar como ativas até habilitação e validação funcional. |

## Redirecionamentos documentados

| Origem | Destino | Código/condição |
| --- | --- | --- |
| `/` | `conta.aculta:/entrar` | `302` para visitante anônimo; usuário autenticado recebe a experiência da conta. |
| `/user` | `conta.aculta:/` | `302`, página legada do Core. |
| `/user/{uid}` | `conta.aculta:/` | `302` para o próprio perfil legado; usuário alheio não tem acesso. |
| `/user/{uid}/edit` e rotas de troca de e-mail | `conta.aculta:/seguranca` | `302` para operações próprias; acesso alheio é negado. |
| `/carrinho` em `loja.aculta` | `/carrinho` em `main.aculta` | `302`, política de pagamento/Commerce. |
| `/enderecos*` | `conta.aculta:/dados/endereco` | `302` para o formulário de endereço único nos caminhos legados listados em `REDIRECTS.md`. |
| `/pagamentos` | `/pedidos` | `301` legado registrado em `DOMAIN-FRONT-PATHS.md`; confirmar status no ambiente antes de alteração. |
| `/product/{id}` | alias canônico de produto | `301` pelo Redirect/Pathauto, se houver registro e produto publicado. |
| `/busca` (envio do formulário) | `/busca?q={termo}` | `302`, submissão do formulário de busca. |

Esta tabela é um resumo; as listas de regras e exceções completas permanecem em
[`REDIRECTS.md`](../portal/REDIRECTS.md) e
[`DOMAIN-FRONT-PATHS.md`](../portal/DOMAIN-FRONT-PATHS.md). Todo novo redirect,
remoção ou exceção precisa ser adicionado nos dois lugares pertinentes.

## Regra de manutenção para agentes

Antes de implementar ou revisar rotas, aliases, links, redirects, controles de
acesso por domínio ou páginas iniciais, o agente deve:

1. ler este mapa e os documentos normativos vinculados na seção de fontes;
2. consultar as definições de rota customizadas, configuração exportada,
   menus, aliases e código de redirect pertinentes;
3. se a tarefa depender do estado ativo do Runtime, seguir
   `docs/architecture/environments.md`, confirmar o ambiente pelo Drush e
   inspecionar rotas sem executar operação persistente;
4. usar `*.aculta` nos identificadores e caminhos relativos começando com `/`;
   nunca gravar URL absoluta específica de produção ou Homelab como caminho
   canônico do mapa;
5. atualizar este arquivo no mesmo commit de qualquer adição, mudança,
   desativação ou remoção de caminho. Registrar propósito, estado, access,
   domínio e redirect/404 quando aplicável;
6. atualizar também a política especializada correspondente, sem duplicar
   detalhes em READMEs ou índices que não sejam fonte canônica;
7. deixar explícito o que depende de conteúdo, módulo, papel ou configuração e
   anotar o ambiente e a data quando registrar evidência Runtime.

Não declarar o mapa completo enquanto houver rotas ativas do escopo alterado
sem registro, nem marcar rota desabilitada como ativa porque o YAML ainda
existe. Alterações somente documentais não avançam versões de módulo/tema.
