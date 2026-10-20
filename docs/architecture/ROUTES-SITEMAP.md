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
- Cada rota deve ter uma linha própria e um título ou explicação do objeto
  atendido. Não agrupe paths diferentes numa linha só para economizar espaço.
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

Este mapa não é inventário exaustivo de todas as rotas do Drupal. Em 2026-10-20,
o conjunto administrativo do Fórum listado abaixo foi reconciliado no Runtime
de teste por Drush e HTTP; isso não comprova as outras áreas nem produção.
Antes de declarar o mapa completo para qualquer ambiente, gere as rotas com
Drush, inspecione paths derivados de conteúdo/aliases e marque módulos
desabilitados. Nunca use um caminho de teste como evidência de produção.

## Hosts e páginas iniciais

| Identificador | Caminho | Título / objeto da página inicial | Estado |
| --- | --- | --- | --- |
| `main.aculta` | `/` | Início institucional da ACULTA | Ativo. Administração, carrinho, checkout e pagamentos pertencem a este purpose. |
| `conta.aculta` | `/` | Visão geral da conta da pessoa autenticada | Ativo para membro; visitante anônimo recebe `302` para `/entrar`. |
| `apoio.aculta` | `/` | Formulário público para escolher valor de apoio | Ativo; caminho interno `/apoio` responde `404` diretamente. |
| `revista.aculta` | `/` | Início editorial da Revista420 | Ativo; publicações dependem de nós publicados. |
| `wiki.aculta` | `/` | Início da Wiki420 | Ativo; edição/criação depende de autenticação e permissões. |
| `loja.aculta` | `/` | Vitrine da loja da associação | Domain ativo; produtos dependem de conteúdo publicado. Carrinho e checkout vão para `main.aculta`. |
| `cursos.aculta` | `/` | Catálogo inicial de cursos | Ativo; cursos e ações dependem de conteúdo, matrícula e access. |
| `forum.aculta` | `/` | Índice público do ACULTA Forum / Growroom420 | Declarado; Runtime de teste HTTP 200 e sem tópicos após limpeza dos fixtures; itens futuros dependem de Nodes publicados e access. Produção não confirmada. |
| `links.aculta` | `/` | Página pública de perfis e links sociais | Código/configuração existentes; confirmar provisionamento DNS de produção em multidomínio. |

## Caminhos humanos ativos ou condicionais

Os padrões dinâmicos abaixo são ativos quando a rota, o módulo e o conteúdo
correspondentes estão habilitados no ambiente.

| Host | Caminho | Título / objeto | Estado |
| --- | --- | --- | --- |
| `main.aculta` | `/` | Início institucional | Ativo. |
| `main.aculta` | `/busca` | Formulário de busca | Ativo; envio direciona para a busca com query. |
| `main.aculta` | `/busca?q={termo}` | Resultados da busca pelo termo | Ativo. |
| `main.aculta` | `/busca/popup` | Formulário de busca em janela | Endpoint de interface; access conforme a busca pública. |
| `main.aculta` | `/busca/previa` | Prévia de resultados da busca geral | Endpoint de interface; resultados respeitam access/cache da busca. |
| `main.aculta` | `/busca/sugestoes` | Sugestões/autocomplete da busca | Endpoint de interface; resposta controlada pela configuração da busca. |
| `main.aculta` | `/carrinho` | Carrinho de compras/apoio Commerce | Ativo, sujeito às regras Commerce. |
| `main.aculta` | `/finalizar-compra/{pedido}/{etapa}` | Etapa do checkout do pedido | Ativo; exige requisitos de acesso do Commerce e aceite quando aplicável. |
| `main.aculta` | `/apoiar` | Formulário de apoio/doação | Ativo conforme configuração do fluxo Commerce. |
| `main.aculta` | `/apoiar/{valor}/agora` | Doação rápida de valor informado | Ativo conforme configuração do fluxo Commerce. |
| `main.aculta` | `/termos/aceitar` | Ação de aceite dos termos | Endpoint autenticado; `POST` processa e redireciona ao destino seguro. |
| `main.aculta` | `/painel-administrativo/**` | Árvore do painel administrativo ACULTA | Rotas administrativas; access do Core/contrib decide cada operação. |
| `main.aculta` | `/painel-administrativo/forum` | Aba Início / Painel Growroom420 | Exige `access content overview`; mostra o resumo público. Ferramentas ficam nas abas administrativas e no menu lateral; limites de contexto estão na documentação, não na página. Runtime do servidor de testes confirmado; produção não confirmada. |
| `main.aculta` | `/painel-administrativo/forum/conteudo` | Display da View de tópicos públicos do Growroom420 | Aba administrativa Drupal; Page display de `aculta_forum_admin_topics`; permissão `access content overview`, bundle fixo e filtro de Domain ativo. Runtime do servidor de testes confirmado; produção não confirmada. |
| `main.aculta` | `/painel-administrativo/forum/comentarios` | Consulta administrativa dos comentários públicos do Fórum | Aba administrativa Drupal; Page display `page_forum_comments` da View `aculta_forum_admin_comments`; exige `administer comments`, Comment bundle/field e tipo de Node fixos, Domain ativo herdado pelo host. Runtime do servidor de testes confirmado. |
| `main.aculta` | `/painel-administrativo/forum/comentarios/publicados` | Fila de comentários publicados do Growroom420 | Segunda aba local Drupal sob Comentários; Page display `page_published`; filtro fixo de estado, acesso `administer comments` e escopo da View de comentários do Fórum. Runtime do servidor de testes confirmado. |
| `main.aculta` | `/painel-administrativo/forum/comentarios/rejeitados` | Fila de comentários rejeitados do Growroom420 | Segunda aba local Drupal sob Comentários; Page display `page_rejected`; estado e marcador de rejeição fixos, acesso `administer comments`. Runtime do servidor de testes confirmado. |
| `main.aculta` | `/painel-administrativo/forum/comentarios/aguardando-moderacao` | Fila de comentários pendentes do Growroom420 | Segunda aba local Drupal sob Comentários; Page display `page_pending`; estado e marcador de rejeição fixos, acesso `administer comments`. Runtime do servidor de testes confirmado. |
| `main.aculta` | `/painel-administrativo/forum/configuracoes` | Configurações específicas do Growroom420 | Aba administrativa Drupal; `ForumSettingsForm`; exige `administer aculta forum settings`. Runtime do servidor de testes confirmado; produção não confirmada. |
| `main.aculta` | `/painel-administrativo/forum/{node}/mover-categoria` | Ação direta de mover tópico público | Redireciona localmente para `/painel-administrativo/forum`; mantém `move aculta forum topics` e conversão de Node; não move conteúdo. Runtime RouteProvider resolve; HTTP positivo não testado porque o Fórum não tem fixtures. |
| `main.aculta` | `/painel-administrativo/forum/comentarios/{comment}/mover` | Formulário para mover comentário e respostas descendentes entre tópicos públicos | `_form` `ForumCommentMoveForm`, conversão de Comment e permissão `move aculta forum comments`; config desabilitada por padrão. Fluxo HTTP/Form API e viewports 390/768 confirmados no Runtime de teste com fixtures temporárias removidas; produção não confirmada. |
| `main.aculta` | `/painel-administrativo/forum/comentario/{comment}/mover` | Caminho singular anterior para mover comentário | Redirect local declarado para `/painel-administrativo/forum`; GET/HEAD/POST; não move conteúdo. |
| `main.aculta` | `/painel-administrativo/forum/denuncias` | Fila de denúncias do Growroom420 | Rota declarada por `aculta_user`, atualmente redireciona para a entrada central; exige `moderar denuncias do forum`. A raiz não apresenta este caminho como link até a fila ter uma tela ativa. |
| `main.aculta` | `/painel-administrativo/conteudo/forum` | Antigo acesso ao conteúdo do Fórum sob Conteúdo geral | Redirect local declarado (302) para `/painel-administrativo/forum`; `access content overview`; GET/HEAD/POST. Runtime RouteProvider resolve; sessão autenticada anteriormente retornou 302. |
| `main.aculta` | `/painel-administrativo/conteudo/forum/{node}/mover-categoria` | Antigo caminho da ação de mover tópico sob Conteúdo | Redirect local declarado (302) para `/painel-administrativo/forum`; GET/HEAD/POST; não move conteúdo. |
| `main.aculta` | `/painel-administrativo/conteudo/forum/comentario/{comment}/mover` | Antigo caminho da ação de mover comentário sob Conteúdo | Redirect local declarado (302) para `/painel-administrativo/forum`; GET/HEAD/POST; não move conteúdo. |
| `main.aculta` | `/painel-administrativo/conteudo/forum/comentarios` | Antigo caminho agregado dos comentários do Fórum sob Conteúdo | Redirect local declarado (302) para `/painel-administrativo/forum`; GET/HEAD/POST. |
| `main.aculta` | `/painel-administrativo/conteudo/forum/comentarios/publicados` | Antiga fila de comentários publicados sob Conteúdo | Redirect local declarado (302) para `/painel-administrativo/forum`; GET/HEAD/POST. |
| `main.aculta` | `/painel-administrativo/conteudo/forum/comentarios/rejeitados` | Antiga fila de comentários rejeitados sob Conteúdo | Redirect local declarado (302) para `/painel-administrativo/forum`; GET/HEAD/POST. |
| `main.aculta` | `/painel-administrativo/conteudo/forum/comentarios/aguardando-moderacao` | Antiga fila de moderação sob Conteúdo | Redirect local declarado (302) para `/painel-administrativo/forum`; GET/HEAD/POST. |
| `main.aculta` | `/painel-administrativo/estrutura/taxonomy/manage/{taxonomy_vocabulary}/overview` | Administração genérica de termos Taxonomy | Rota Core fora da seção do Fórum; os links específicos do Fórum foram removidos. Runtime gera URL para UID 1; anônimo 403. |
| `main.aculta` | `/painel-administrativo/estrutura/types/manage/{node_type}` | Administração genérica de tipos de conteúdo | Rota Core fora da seção do Fórum; os links específicos do Fórum foram removidos. Runtime gera URL para UID 1; anônimo 403. |
| `main.aculta` | `/painel-administrativo/lms/forum` | Índice de cursos e turmas cuja moderação de discussões pertence ao Educador atual | Ativa no Runtime de teste após cache rebuild; acesso anônimo negado (403). Exige Group contextual com `grade students`. |
| `main.aculta` | `/painel-administrativo/lms/forum/{group}/comentarios/publicados` | Comentários publicados da discussão do curso ou turma indicada pelo Group | Ativa no Runtime de teste; Group inexistente retorna 404. Custom access revalida Educador no curso/turma. Embed da View fixa Group ID, bundle e campo Comment; sem rota global. |
| `main.aculta` | `/painel-administrativo/lms/forum/{group}/comentarios/rejeitados` | Comentários rejeitados da discussão do Group LMS indicado | Ativa no Runtime de teste; Group inexistente retorna 404. Mesma validação contextual, status não publicado e marcador de rejeição fixos na View. |
| `main.aculta` | `/painel-administrativo/lms/forum/{group}/comentarios/aguardando-moderacao` | Comentários pendentes da discussão do Group LMS indicado | Ativa no Runtime de teste; Group inexistente retorna 404. Mesma validação contextual, status não publicado e marcador de rejeição desligado fixos na View. |
| `main.aculta` | `/painel-administrativo/lms/forum/{group}/comentario/{comment}/moderacao/{decision}` | Confirmação para aprovar ou rejeitar um comentário do LMS no Group indicado | Rota ativa após cache rebuild no Runtime de teste; Form API e custom access revalidam comentário, tópico, Group e Educador no submit. View atualizada por importação parcial; ação aparece apenas em Comments elegíveis. Smoke autenticado pendente. |
| `main.aculta` | `/painel-administrativo/conteudo/revista` | Área administrativa unificada de publicações e comentários editoriais | Condicional às Views `aculta_magazine_admin_content` e `aculta_magazine_admin_comments`, permissão `access content overview` e ao bloco editorial condicionado ao purpose main. Homologação Runtime pendente. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/participacao` | Configuração compartilhada das regras de participação | Rota `aculta_user` fora da seção do Fórum; afeta Fórum, Revista, Wiki e outros contextos, exceto LMS. Exige `administer aculta participacao`; Runtime access manager permite UID 1 e anônimo 403. |
| `main.aculta` | `/painel-administrativo/perfil/editar` | Edição do perfil do usuário autenticado | Requer access administrativo próprio. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/redes-sociais` | Configuração de perfis e links sociais | Rota administrativa protegida. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/cep` | Configuração da integração de CEP | Condicional ao submódulo CEP; rota administrativa. |
| `main.aculta` | `/painel-administrativo/game` | Visão geral administrativa do jogo | Condicional ao módulo; exige permissão específica. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/aparencia` | Configuração da aparência institucional | Rota administrativa protegida. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/apoio` | Configuração dos meios/valores de apoio | Rota administrativa protegida. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email` | Painel de envios de e-mail transacional (contagens por período, estado, tipo e transporte) | Rota administrativa protegida por `administer aculta_mailer`; condicional ao submódulo `aculta_mailer` habilitado (Runtime de teste: habilitado). Sem destinatário nem conteúdo. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/falhas` | Relatório de falhas de envio com o texto completo do erro (30 dias) | Protegida por `administer aculta_mailer`; contém o texto do servidor, que pode citar o destinatário. Não é visível ao usuário final. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/registro/{log_id}/estado` | Estado de um envio: registro (tipo, estado, transporte, tentativas, código, ID no provedor) e, para envios pelo Brevo, os eventos informados pelo provedor (30 dias) | Protegida por `administer aculta_mailer`. Sem destinatário nem conteúdo. Consulta à API do Brevo a cada abertura. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/envios` | Lista de todos os envios do registro, com filtros por tipo, estado, transporte e período (1 a 90 dias), paginada em 50 | Protegida por `administer aculta_mailer`. Sem destinatário nem conteúdo; cada linha leva à página de estado. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/diagnostico` | Diagnóstico de envio: padrão e contingência, transportes sem credenciais, último envio e falha, SPF e DMARC do domínio remetente, fila e cron | Protegida por `administer aculta_mailer`. Mostra só se usuário e senha estão preenchidos. SPF e DMARC vêm de consulta DNS ao vivo. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/monitoramento` | Monitoramento IMAP de envios: servidor, porta, e-mail, pasta, intervalos, senha (gravada no arquivo de segredos) e teste de conexão | Protegida por `administer aculta_mailer`; senha exige `administer aculta secrets`. Monitoramento desligado por padrão. Ver `aculta_mailer/docs/MONITORING.md`. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/entrega` | Entrega: endereço de retorno dos transportes SMTP, alertas de falha e resumo semanal | Protegida por `administer aculta_mailer`. Destinatários recebem o texto completo do erro: enviar só a administradores. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/registro` | Registro de e-mail: ligar o registro e prazo de retenção (30 dias a 1 ano) | Protegida por `administer aculta_mailer`. Conteúdo e anexos não se aplicam por política. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/registro/exportar` | Exportação CSV do registro de envios (1, 7 ou 30 dias, parâmetro `dias`) | Protegida por `administer aculta_mailer`. Sem destinatário nem texto de erro; resposta com `noindex`. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/configuracoes` | Configurações de envio: estado da credencial do Brevo (sem valor) e link para alterá-la | Protegida por `administer aculta_mailer`. Alteração no painel de credenciais do aculta_portal, com `administer aculta secrets`. |
| `main.aculta` | `/aculta-mailer/webhook/{provider}` | Endpoint técnico de eventos de entrega de provedores de e-mail (POST) | Pública, sem sessão. Brevo (`/brevo`): token de portador em `BREVO_WEBHOOK_TOKEN` (cadastrado no webhook do Brevo, ambiente de teste); sem token ou com token errado responde 403. Sem provedor registrado responde 404. Não é navegação. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/modelos` | Lista de modelos de e-mail (Config Entity `aculta_mailer_template`) | Protegida por `administer aculta_mailer`; editor e prévia em `.../modelos/{aculta_mailer_template}`. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/marcas` | Lista de marcas de e-mail (`aculta_mailer_brand`) | Protegida por `administer aculta_mailer`; editor em `.../marcas/{aculta_mailer_brand}`. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/layouts` | Lista de layouts de e-mail (`aculta_mailer_layout`) | Protegida por `administer aculta_mailer`; editor em `.../layouts/{aculta_mailer_layout}`. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/envios-de-email/modelos/{aculta_mailer_template}/teste` | Envio de teste de um modelo para um endereço informado | Protegida por `administer aculta_mailer`; usa valores de exemplo e o caminho real de envio. Resultado genérico na tela; motivo completo no relatório de falhas. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal` | Configurações gerais do Portal | Rota administrativa protegida. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/turnstile` | Configuração do CAPTCHA Turnstile | Rota administrativa protegida; credenciais não são documentadas aqui. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/requisitos` | Relatório de requisitos do Portal | Rota administrativa protegida. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/segredos` | Lista de integrações/segredos configurados | Rota administrativa restrita; nenhum valor secreto é registrado neste mapa. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/segredos/apagar/{name}` | Remoção de configuração de credencial | Rota administrativa restrita; nunca documentar valores de segredo. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/segredos/editar/{name}` | Edição de metadados de credencial | Rota administrativa restrita; segredo não é exposto no mapa. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/segredos/mostrar/{name}` | Visualização controlada de estado da credencial | Rota administrativa restrita; não copiar segredo para documentação. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/deployer` | Configuração/operações do deployer | Rota administrativa restrita. |
| `main.aculta` | `/painel-administrativo/loja/vendas` | Lista administrativa de vendas | Condicional aos módulos Commerce e permissões. |
| `main.aculta` | `/painel-administrativo/loja/vendas/pagamento/{commerce_payment}/atualizar` | Atualização administrativa de pagamento | Condicional ao Commerce; operação protegida. |
| `main.aculta` | `/painel-administrativo/loja/vendas/pagamento/{commerce_payment}/cancelar` | Cancelamento administrativo de pagamento | Condicional ao Commerce; operação protegida. |
| `main.aculta` | `/painel-administrativo/loja/doacoes` | Lista administrativa de doações | Condicional ao fluxo de doações e permissões. |
| `main.aculta` | `/carrinho/finalizar` | Continuação do carrinho para o checkout | Ativo conforme Commerce; checkout permanece no domínio principal. |
| `main.aculta` | `/painel-administrativo/contas/nao-confirmadas` | Lista de contas sem confirmação | Rota administrativa protegida. |
| `main.aculta` | `/painel-administrativo/contas/{user}/confirmar-email` | Confirmação administrativa do e-mail de uma conta | Rota administrativa protegida; `{user}` identifica a conta-alvo. |
| `main.aculta` | `/painel-administrativo/configuracoes/aculta/portal/mensagens` | Configuração do Messenger | Condicional ao módulo Messenger; rota administrativa. |
| `main.aculta` | `/painel-administrativo/avisos` | Lista administrativa de avisos | Condicional ao Messenger/Site Alert; access administrativo. |
| `main.aculta` | `/painel-administrativo/avisos/temporarios` | Lista de avisos temporários | Condicional ao Messenger/Site Alert; access administrativo. |
| `main.aculta` | `/painel-administrativo/avisos/persistentes` | Lista de comunicados persistentes | Condicional ao Messenger; access editorial/administrativo. |
| `main.aculta` | `/painel-administrativo/avisos/persistentes/criar` | Criação de comunicado persistente | Condicional ao Messenger; access editorial/administrativo. |
| `conta.aculta` | `/entrar` | Formulário de login | Ativo; CAPTCHA e autenticação conforme configuração. |
| `conta.aculta` | `/sair` | Ação de logout | Ativo; ao sair, destino canônico é login. |
| `conta.aculta` | `/sair/confirmar` | Confirmação de logout do Core | Ativo, sujeito à sessão. |
| `conta.aculta` | `/recuperar-senha` | Solicitação de recuperação de senha | Ativo. |
| `conta.aculta` | `/criar-conta` | Formulário de cadastro | Condicional à abertura/configuração de cadastro. |
| `conta.aculta` | `/recuperar-acesso/{uid}/{timestamp}/{hash}/entrar` | Redefinição temporária de senha | Ativo somente com token válido e não expirado. |
| `conta.aculta` | `/entrar/janela` | Formulário de login para janela/modal | Ativo; fallback de página sem JavaScript. |
| `conta.aculta` | `/criar-conta/janela` | Formulário de cadastro para janela/modal | Condicional ao cadastro; fallback sem JavaScript. |
| `conta.aculta` | `/confirmar-email/reenviar` | Reenvio de confirmação de e-mail | Ativo conforme estado da conta e access. |
| `conta.aculta` | `/dados` | Formulário de dados pessoais | Conta autenticada; próprio usuário. |
| `conta.aculta` | `/atividade/{aba}` | Atividade da própria conta por área (Wiki, Fórum, Revista, comentários e conquistas) | Condicional ao `aculta_user`; exige acesso autenticado e lista somente conteúdo próprio/publicado conforme cada área. |
| `conta.aculta` | `/dados/endereco` | Cadastro/edição do endereço único | Conta autenticada; próprio usuário. |
| `conta.aculta` | `/dados/outros-dados` | Dados adicionais de identificação | Condicional ao submódulo de identificação; próprio usuário. |
| `conta.aculta` | `/foto` | Foto de perfil | Condicional à configuração de perfil/imagens; próprio usuário. |
| `conta.aculta` | `/seguranca` | Painel de segurança e e-mail da conta | Conta autenticada; próprio usuário. |
| `conta.aculta` | `/seguranca/senha` | Alteração de senha | Conta autenticada; validação de senha vigente. |
| `conta.aculta` | `/seguranca/email` | Início da alteração de e-mail | Conta autenticada e validações de confirmação. |
| `conta.aculta` | `/seguranca/email/{user}` | Formulário/token de alteração de e-mail | Condicional a token válido; `{user}` é validado no fluxo. |
| `conta.aculta` | `/seguranca/email/cancelar/{user}` | Cancelamento de alteração de e-mail | Condicional a token válido; `{user}` é validado no fluxo. |
| `conta.aculta` | `/identidade/editar` | Edição técnica/legada do perfil próprio | Navegação redirecionada para `/seguranca`; acesso alheio é negado. |
| `conta.aculta` | `/identidade/{user}` | Perfil técnico/legado de usuário | Para o próprio usuário redireciona à raiz da Conta; perfil alheio não é exibido. |
| `conta.aculta` | `/confirmar-email/{email_confirmer_confirmation}/{hash}` | Confirmação de endereço de e-mail | Token temporário; usuário e hash precisam ser válidos. |
| `conta.aculta` | `/confirmar-email/reenviar/{confirmation}` | Reenvio de confirmação de uma solicitação | Registro/token de confirmação validado pelo módulo. |
| `conta.aculta` | `/conexoes` | Conexões de autenticação social | Conta autenticada; provedores habilitados variam por ambiente. |
| `conta.aculta` | `/meus-cursos` | Lista de cursos da pessoa | Conta autenticada; depende de matrícula/conteúdo. |
| `conta.aculta` | `/meu-apoio` | Histórico de apoio/doações da pessoa | Conta autenticada; dados próprios. |
| `conta.aculta` | `/minha-participacao` | Área de participação da pessoa | Condicional a módulos e permissões ativos. |
| `conta.aculta` | `/pedidos` | Lista de pedidos da pessoa | Conta autenticada; Commerce verifica propriedade. |
| `conta.aculta` | `/pedidos/{pedido}` | Detalhe de um pedido | Conta autenticada; Commerce verifica propriedade do pedido. |
| `conta.aculta` | `/meu-apoio/assinatura/{commerce_order}/{acao}` | Ação da assinatura de apoio | Condicional ao fluxo de recorrência; operação validada no pedido próprio. |
| `conta.aculta` | `/configuracoes/mensagens` | Preferências de notificações de mensagem (caminho antigo) | Removido: sem rota registrada no código nem no Runtime de teste (consulta de 2026-10-19). A preferência de e-mail está em `/configuracoes/email`. Sem redirecionamento. |
| `conta.aculta` | `/mensagens` | Caixa de entrada de conversas privadas (caminho antigo) | Não registrado: sem rota no código nem no Runtime de teste (consulta de 2026-10-19). Conversas são abertas pela gaveta da barra e por `/mensagens/conversas/painel`. Sem redirecionamento. |
| `conta.aculta` | `/mensagens/notificacoes` | Lista de avisos e pedidos privados (caminho antigo) | Removido: sem rota registrada no código nem no Runtime de teste (consulta de 2026-10-19). Os pedidos de amizade recebidos estão na aba Mensagens de `/avisos-mensagens/mensagens`. Sem redirecionamento. |
| `conta.aculta` | `/mensagens/avisos` | Comunicados institucionais (caminho antigo) | Removido: substituído por `/avisos-mensagens/avisos`. Sem redirecionamento. |
| `conta.aculta` | `/mensagens/resumo` | Resumo de conversas não lidas | Condicional ao Messenger; dados da própria conta. |
| `conta.aculta` | `/mensagens/conversas/painel` | Painel/fragmento da lista de conversas | Condicional ao Messenger; acesso da própria conta. |
| `conta.aculta` | `/mensagens/conversa/{private_message_thread}/responder` | Formulário de resposta à conversa | Condicional ao Messenger; valida acesso à thread. |
| `conta.aculta` | `/mensagens/conversa/{private_message_thread}/opcoes` | Opções disponíveis para a conversa | Condicional ao Messenger; valida acesso à thread. |
| `conta.aculta` | `/mensagens/conversa/{private_message_thread}/relacao` | Relação de participantes da conversa | Condicional ao Messenger; valida acesso à thread. |
| `conta.aculta` | `/mensagens/conversa/{private_message_thread}/painel` | Painel/fragmento de uma conversa | Condicional ao Messenger; valida acesso à thread. |
| `conta.aculta` | `/mensagens/nova` | Início de nova conversa | Condicional ao Messenger; permissões e bloqueios nativos. |
| `conta.aculta` | `/mensagens/nova/buscar` | Busca de destinatário para conversa | Condicional ao Messenger; resultado sujeito às regras de perfil. |
| `conta.aculta` | `/mensagens/nova/grupo/painel` | Painel de criação de conversa em grupo | Condicional ao Messenger e permissões. |
| `conta.aculta` | `/mensagens/nova/{user}/painel` | Painel para iniciar conversa com uma pessoa | Condicional ao Messenger; aplica bloqueios e privacidade. |
| `conta.aculta` | `/mensagens/perfil/{user}/relacao` | Ação/estado de relação com outra pessoa | Condicional ao Messenger; valida usuários e permissões. |
| `conta.aculta` | `/avisos/resumo` | Resumo de avisos visíveis | Condicional ao Messenger/Site Alert; sujeito a access/cache. |
| `conta.aculta` | `/avisos/alerta/{site_alert}` | Ação/visualização de alerta temporário | Condicional ao Site Alert; valida entidade e usuário. |
| `conta.aculta` | `/avisos/aviso/{node}` | Ação/visualização de comunicado persistente | Condicional ao Messenger; valida publicação, segmentação e usuário. |
| `conta.aculta` | `/avisos-mensagens` | Página de avisos e mensagens | Condicional ao Messenger; conteúdo filtrado por access. |
| `conta.aculta` | `/avisos-mensagens/{aba}` | Aba específica de avisos ou mensagens | Condicional ao Messenger; `{aba}` limitado às opções válidas. |
| `conta.aculta` | `/oauth/{provedor}` | Início de autenticação com provedor externo | Endpoint técnico; depende do provedor habilitado. |
| `conta.aculta` | `/oauth/{provedor}/retorno` | Retorno/callback de autenticação externa | Endpoint técnico; URI cadastrada e validada por ambiente. |
| `apoio.aculta` | `/` | Página de apoio com formulário e valores sugeridos | Ativo; fluxo de pagamento continua em `main.aculta`. |
| `main.aculta` | `/{titulo}` | Página institucional por alias editorial | Dinâmico; depende de nó publicado e domínio associado. |
| `main.aculta` | `/atividades/{titulo}` | Página de uma atividade cultural | Dinâmico; depende de atividade publicada e domínio. |
| `main.aculta` | `/projetos/{titulo}` | Página de um projeto cultural/social | Dinâmico; depende de projeto publicado e domínio. |
| `main.aculta` | `/transparencia/documentos/{titulo}` | Documento institucional público | Dinâmico; depende de documento publicado e access. |
| `loja.aculta` | `/loja/produto/{categoria}/{titulo}` | Página de produto da loja | Dinâmico; produto publicado; fora do purpose loja responde `404`. |
| `revista.aculta` | `/{sessao}/{titulo}` | Artigo editorial ou publicação de blog | Dinâmico; padrão Pathauto, depende de conteúdo publicado. |
| `main.aculta` | `/avisos/{titulo}` | Comunicado institucional persistente | Dinâmico; depende de publicação, segmentação de público e access. |
| `wiki.aculta` | `/verbete/{titulo}` | Verbete da Wiki | Dinâmico; depende de conteúdo e permissão de acesso. |
| `wiki.aculta` | `/wiki/busca` | Busca de verbetes | Ativo conforme View/feature; a raiz interna `/wiki` responde `404` diretamente. |
| `wiki.aculta` | `/wiki/busca/previa` | Prévia de resultados da busca da Wiki | Endpoint de interface; access/cache conforme busca. |
| `cursos.aculta` | `/curso/{alias}` | Página de um curso | Dinâmico; depende de curso publicado e acesso. O caminho interno `/cursos` responde `404` diretamente. |
| `cursos.aculta` | `/resposta/{lms_answer}/baixar-arquivo` | Download de arquivo anexado a uma resposta LMS | Condicional; valida resposta e matrícula vigente. |
| `forum.aculta` | `/topico/{titulo}` | Tópico do fórum | Dinâmico; depende de tópico publicado e access. Caminhos internos `/discussoes` e legado `/forum` respondem `404` diretamente. |
| `forum.aculta` | `/categorias` | Índice público das categorias e subcategorias do Growroom420 | View `aculta_forum_categories`; Runtime de teste HTTP 200, apresenta os 12 termos iniciais com relações pai/filho e nenhum tópico de teste. |
| `forum.aculta` | `/pesquisar` | Pesquisa pública de tópicos do Growroom420 | View `aculta_forum_search`; índice limitado a `aculta_forum_topic`, com access checks e Domain canônico. Runtime de teste: índice/View importados, rota HTTP 200 e consulta vazia HTTP 200; nenhum tópico de teste. Produção não confirmada. |
| `forum.aculta` | `/discussoes/nao-lidas` | Lista personalizada de tópicos novos ou atualizados para a conta autenticada | View criada no Runtime de teste; exige papel `authenticated`, tópico público publicado, Domain ativo e acesso atual. UID 5 autorizado no plugin; anônimo HTTP 403; sessão autenticada/browser pendentes. |
| `forum.aculta` | `/discussoes/marcar-tudo-como-lido` | Confirmação para marcar tópicos públicos acessíveis como lidos pela conta atual | Declarada em `aculta_forum 0.1.0-dev.29`; somente autenticado no purpose `forum`; Core Batch atualiza History por lotes após confirmação; Runtime não submeteu a ação para não gravar histórico de conta real. |
| `forum.aculta` | `/discussoes/novas` | Tópicos públicos mais novos, ordenados por fixação e criação | Runtime de teste HTTP 200 e lista vazia após limpeza dos 8 tópicos `[TESTE]`; inspeção visual vazia pendente. |
| `forum.aculta` | `/discussoes/sem-resposta` | Tópicos públicos sem respostas publicadas | Runtime de teste HTTP 200 e lista vazia após limpeza; filtros fixos de bundle, publicação, Domain, categoria e node access permanecem configurados. |
| `forum.aculta` | `/discussoes/mais-comentadas` | Tópicos públicos ordenados pelo agregado de respostas publicadas | Runtime de teste HTTP 200 e lista vazia após limpeza; ordenação positiva com respostas publicadas aguarda homologação. |
| `forum.aculta` | `/criar-topico` | Formulário Core para novo tópico público | Declarado; Runtime de teste resolve no purpose forum; requer Entity Create Access para `aculta_forum_topic`. Anônimo 403; submissão autorizada ainda não homologada. |
| `links.aculta` | `/` | Página pública de perfis e links sociais | Condicional ao provisionamento do host e conteúdo de configuração. `/redes-sociais` é interno. |
| `revista.aculta` | `/u/{apelido}` | Perfil público de autor | Condicional a perfil público habilitado; apelido não canônico responde `404`. |
| `revista.aculta` | `/albuns/{titulo}` | Álbum de galeria | Condicional ao módulo e conteúdo; confirmar estado efetivo do Runtime. |

## Rotas técnicas ativas

Estes padrões não são itens de navegação. Sua existência depende do módulo
correspondente e sua proteção deve ser mantida no dono da feature.

| Host | Caminho/padrão | Título / objeto | Estado |
| --- | --- | --- | --- |
| `main.aculta` | `/webhook/{endpoint}` | Recepção de evento de integração/gateway | Padrão genérico; caminhos concretos definidos pelo módulo ativo e configuração. Nunca presumir acesso público irrestrito. |
| `main.aculta` | `/admin/**` | Administração técnica Drupal Core/contrib | Rotas individuais variam por módulo e continuam sob access upstream; navegação humana ACULTA usa `/painel-administrativo/**`. |
| `main.aculta` | `/views-bulk-operations/execute/{view_id}/{display_id}` | Inicialização de operações em lote de uma View | Rota técnica condicional ao VBO; a própria View exige access e a Action revalida seleção, permissão e cada tópico. |
| `main.aculta` | `/views-bulk-operations/ajax/{view_id}/{display_id}` | Atualização da seleção entre páginas de uma View | Rota técnica condicional ao VBO; acesso passa pelo display da View e usa seleção temporária privada do usuário. |
| `main.aculta` | `/views-bulk-operations/configure/{view_id}/{display_id}` | Configuração de uma Action VBO | Rota técnica condicional ao VBO; access do display da View. |
| `main.aculta` | `/views-bulk-operations/confirm/{view_id}/{display_id}` | Confirmação de execução de uma Action VBO | Rota técnica condicional ao VBO; access do display da View. |
| `main.aculta` | `/aculta_portal_cep/{cep}` | Consulta AJAX de CEP | Condicional ao submódulo CEP; endpoint técnico com resposta controlada. |
| `main.aculta` | `/integracoes/pagamentos/{commerce_payment_gateway}/notificacao` | Notificação enviada pelo gateway de pagamento | Callback técnico, sem navegação humana; endereço declarado por ambiente e validado pelo gateway. |
| `conta.aculta` | `/oauth/{provedor}/retorno` | Callback de autenticação OAuth | Endpoint técnico, restrito a provedores e URIs configurados. |
| `cursos.aculta` | `/resposta/{lms_answer}/baixar-arquivo` | Download de anexo de resposta | Condicional; verifica access à resposta e matrícula. |

O inventário técnico exaustivo deve ser preenchido a partir do roteador Drupal
do ambiente validado. Não copiar indiscriminadamente endpoints de todos os
módulos instalados: módulos desabilitados não expõem rotas ativas.

## Caminhos internos, desabilitados e removidos

| Caminho | Host | Título / objeto | Estado / comportamento |
| --- | --- | --- | --- |
| `/apoio` | `apoio.aculta` | Caminho interno da página inicial de apoio | A raiz `/` é pública; acesso direto responde `404`, sem 301. |
| `/wiki` | `wiki.aculta` | Caminho interno da página inicial da Wiki | A raiz `/` é pública; acesso direto responde `404`, sem 301. |
| `/cursos` | `cursos.aculta` | Caminho interno do catálogo de cursos | A raiz `/` é pública; acesso direto responde `404`, sem 301. |
| `/loja` | `loja.aculta` | Caminho interno da vitrine | A raiz `/` é pública; acesso direto responde `404`, sem 301. |
| `/discussoes` | `forum.aculta` | Rota de Page display da View que serve a raiz do Growroom420 | Declarada e presente no Runtime de teste; caminho interno, acesso direto responde `404`, sem 301. |
| `/forum` | `forum.aculta` | Antigo índice do Drupal Forum contribuído | Pode continuar declarado enquanto contrib instalado; caminho bloqueado, HTTP 404 validado no Runtime de teste. |
| `/redes-sociais` | `links.aculta` | Caminho interno da página de links | A raiz `/` é pública; acesso direto responde `404`, sem 301. |
| `/minha-conta` | `conta.aculta` | Rota interna de visão geral da conta | A página pública é `/`; acesso direto responde `404`. |
| `/conta-interna` | `conta.aculta` | Antigo caminho interno do painel da conta | Removido; visitante recebe `404`; comportamento para usuário autenticado segue política documentada. |
| `/cart` | `main.aculta` | Antigo alias inglês do carrinho | Removido; `404`, sem redirect de slug. |
| `/donate` | `main.aculta` | Antigo caminho inglês do formulário de doação | Removido; `404`, sem redirect de slug. |
| `/donate/{valor}/now` | `main.aculta` | Antigo caminho inglês de doação rápida | Removido; `404`, sem redirect de slug. |
| `/donate/{pedido}/{etapa}` | `main.aculta` | Antigo caminho inglês das etapas de doação | Removido; `404`, sem redirect de slug. |
| `/user/{uid}/orders` | `conta.aculta` | Antiga lista de pedidos com UID explícito | Removido; usar `/pedidos`, que verifica propriedade pela sessão. |
| `/user/{uid}/payment-methods` | `conta.aculta` | Antiga lista de meios de pagamento com UID | Removido; meios de pagamento pessoais não são armazenados/exibidos. |
| `/user/{uid}/address-book*` | `conta.aculta` | Antigo catálogo de endereços por UID | Removido; endereço único fica em `/dados/endereco`. |
| `/pagamentos/novo` | `conta.aculta` | Cadastro de meio de pagamento pessoal | `404`; ACULTA não armazena nem exibe meios de pagamento pessoais. |
| `/pagamentos/{id}/editar` | `conta.aculta` | Edição de meio de pagamento pessoal | `404`; ACULTA não armazena nem exibe meios de pagamento pessoais. |
| `/pagamentos/{id}/excluir` | `conta.aculta` | Exclusão de meio de pagamento pessoal | `404`; ACULTA não armazena nem exibe meios de pagamento pessoais. |
| `/pagamentos/{id}/padrao` | `conta.aculta` | Seleção de meio de pagamento padrão pessoal | `404`; ACULTA não armazena nem exibe meios de pagamento pessoais. |
| `/aceite-termos` | Todos os purposes | Antiga página dedicada ao aceite dos termos | Removida; `404`. O fluxo atual usa popup e `POST /termos/aceitar`. |
| `/albuns/{titulo}` | `revista.aculta` | Álbum da galeria | Rota condicional do submódulo; confirmar estado efetivo do Runtime, não inferir pelo YAML. |
| Rotas `aculta_lms_h5p` | `cursos.aculta` | Editor, player e endpoints de atividades H5P | Módulo desabilitado; endpoints não devem ser considerados ativos até habilitação e validação funcional. |

## Redirecionamentos documentados

| Origem | Destino | Título / objeto | Código/condição |
| --- | --- | --- | --- |
| `/` | `conta.aculta:/entrar` | Entrada da área de conta para visitante anônimo | `302`; usuário autenticado recebe a visão geral da conta. |
| `/user` | `conta.aculta:/` | Página legada de usuário do Core | `302` para a raiz da Conta. |
| `/user/{uid}` | `conta.aculta:/` | Perfil legado do próprio usuário | `302` para o dono; acesso ao perfil de outra pessoa é negado. |
| `/identidade/editar` | `conta.aculta:/seguranca` | Edição legada do próprio perfil | `302` para o painel de segurança; usuário alheio recebe negação. |
| `/identidade/{user}` | `conta.aculta:/` | Perfil legado do próprio usuário | `302` para a raiz da conta; usuário alheio recebe negação. |
| `/seguranca/email` | `conta.aculta:/seguranca` | Tela legada de troca de e-mail | `302` para segurança, após validar titularidade. |
| `/seguranca/email/{user}` | `conta.aculta:/seguranca` | Formulário legado de troca de e-mail | `302` para segurança, após validar titularidade/token. |
| `/carrinho` em `loja.aculta` | `/carrinho` em `main.aculta` | Continuação da compra no domínio responsável pelo checkout | `302` cross-purpose confiável, via política de domínio. |
| `/enderecos` | `conta.aculta:/dados/endereco` | Lista legada de endereços | `302` para endereço único. |
| `/enderecos/novo/{tipo}` | `conta.aculta:/dados/endereco` | Criação legada de endereço | `302` para endereço único. |
| `/enderecos/{perfil}/editar` | `conta.aculta:/dados/endereco` | Edição legada de endereço | `302` para endereço único. |
| `/enderecos/{perfil}/excluir` | `conta.aculta:/dados/endereco` | Exclusão legada de endereço | `302` para endereço único. |
| `/enderecos/{perfil}/padrao` | `conta.aculta:/dados/endereco` | Seleção legada de endereço padrão | `302` para endereço único. |
| `/pagamentos` | `/pedidos` | Página antiga de meios de pagamento pessoais | `301` legado registrado; confirmar comportamento no ambiente antes de alterar. |
| `/product/{id}` | Alias canônico `/loja/produto/{categoria}/{titulo}` | URL técnica antiga de produto Commerce | `301` quando houver redirect/alias e produto publicado. |
| Submissão de `/busca` | `/busca?q={termo}` | Envio do formulário de busca | `302`; termo vai na query string. |

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
