# Guardrails do Portal

Regras que bloqueiam regressão no `aculta_portal`. São normativas: quem altera o Portal deve
cumpri-las, e o gate ou a revisão deve checá-las. Não são roadmap nem relatório.

Documentos normativos relacionados: [DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md),
[SOURCE-OF-TRUTH.md](SOURCE-OF-TRUTH.md), [ARCHITECTURE.md](ARCHITECTURE.md),
[ADMIN-DOMAIN-POLICY.md](ADMIN-DOMAIN-POLICY.md),
[CROSS-DOMAIN-REQUEST-POLICY.md](CROSS-DOMAIN-REQUEST-POLICY.md),
[PAYMENT-DOMAIN-POLICY.md](PAYMENT-DOMAIN-POLICY.md),
[DOMAIN-PRESENTATION-CONTRACT.md](DOMAIN-PRESENTATION-CONTRACT.md),
[AJAX.md](AJAX.md), [COMPONENT-DESIGN-SYSTEM.md](COMPONENT-DESIGN-SYSTEM.md),
[AGENT-TOKEN-ECONOMY.md](AGENT-TOKEN-ECONOMY.md) e
[FRIENDLY-PORTUGUESE-SLUGS.md](FRIENDLY-PORTUGUESE-SLUGS.md).

## Fronteiras gerais

- O Portal integra capacidades existentes. Não substitui storage, regra de negócio ou
  decisão de access de Core ou contrib.
- Não criar storage paralelo quando o Core ou o contrib já tiver a capacidade.
- Não usar service locator (`\Drupal::`) em código novo de `src/`.
- Não hardcodar hostname ou domínio em controllers, rotas, templates ou config de tema.
- Decisão por purpose vem de `DomainPurposeManager` e do Domain, nunca de hostname.
- O tema recebe contexto neutro de apresentação. Não recebe entidade `Domain`, storage nem
  decisão de access.
- Não criar CMS, catálogo, carrinho, pedido, pagamento ou LMS paralelo a Core ou contrib.

## Fórum (`forum`)

- Não criar entidade Topic, Reply, tabela de fórum ou tabela de comentários. Usar Forum,
  Node e Comment nativos.
- Não criar `ForumController` só para reconstruir a listagem nativa. Mapear rotas nativas antes
  de escrever subscriber.
- Acesso direto a um tópico em host incorreto é bloqueado pela política multidomínio.
- Acompanhar tópico e favoritos usam Flag, não storage custom.
- Moderação reutiliza permissões, Comment, Node, Views e ferramentas administrativas. O Portal
  Admin pode ser hub de acesso e resumo, mas não um segundo CRUD de moderação.
- Conta agrega participação sem duplicar comentários.

## Revista (`magazine`)

- O Portal não cria CMS editorial paralelo. Não recriar formulários de Node ou Taxonomy.
- Conteúdo editorial preserva revisões, workflow e propriedades do Node.
- Plugins de schema custom do Portal são dívida auditável: só ficam quando o upstream instalado
  não cobre a propriedade com a mesma semântica.
- Analytics e Search Console são integrações de plataforma, não lógica editorial. Search Console
  observa URLs canônicas e sitemap reais.

## Loja (`shop`)

- O Portal não cria catálogo, carrinho, pedido ou pagamento paralelo.
- Não transformar Donation Order Items em produtos de loja só para reaproveitar UI.
- Não transformar compra de produto em "apoio" para contornar a modelagem Commerce.
- Não hardcodar `loja.aculta.org` em controllers. Usar `DomainPurposeManager`.
- Não adicionar eventos de mensuração (`purchase`, `add_to_cart`) antes de existir o fluxo Commerce
  correspondente e um plano de mensuração revisado.
- Antes de ativar catálogo: finalidade, política comercial, Product Types, checkout, gateway,
  LGPD, testes em MariaDB, isolamento de Domain e exportação de configuração. Nenhuma dessas
  decisões é tomada sem especificação.

## Wiki (`wiki`)

- O Portal não cria entidade, tabela ou índice próprio para espelhar verbetes.
- Dashboards e listagens consultam entidades e revisões reais. Não criar contador persistido no
  Portal só para dashboard.
- Conteúdo unpublished ou restrito não vaza metadata em cards, busca ou listagem.
- A Wiki não recria CRUD editorial.

## Minha Conta

- Semântica de apresentação: entity metadata → componente → decisão de access. Nunca o caminho
  inverso, e nenhum componente decide access.
- Conteúdo não publicado (curso, verbete, pedido Commerce) não aparece por apresentação, mesmo
  quando o componente só mostra metadata.
- Extração de componentes não altera OAuth, não substitui Form API, não duplica Social Auth,
  Email Confirmer, LMS ou Commerce, e não cria dependência obrigatória de SDC ainda inexistente.
- Nenhuma regra funcional migra para o tema.
- Substituição de fluxo acontece por fluxo, nunca com remoção total antecipada.

## Foto de perfil (`aculta_user`)

- Sorteio é prévia: só "Salvar foto do perfil" grava a foto. Fechar sem salvar descarta a escolha.
- Foto personalizada tem "Remover foto" (imediato, sorteia do acervo). Avatar do sistema tem "Sortear novo avatar" e
  caixa de envio, sem "Remover".
- Recorte só do arquivo recém-enviado; aplicar o recorte troca a foto e recarrega o painel (sem reabrir o popup).
- Foto importada por OAuth é personalizada.
- Regras completas, contrato do sorteio e invariantes: `web/modules/custom/aculta_portal/modules/aculta_user/docs/FOTO-PERFIL.md`
  e `docs/portal/ANTI-REGRESSAO.md`. Gate: `php scripts/validate-aculta-user-photo.php`.

## AJAX e formulários

- O Portal não mantém um segundo framework de AJAX. Usa Drupal AJAX, Views AJAX ou Form API.
- Toda interação assíncrona tem fallback de página inteira.
- Um SDC pode apresentar a moldura visual de uma seção, mas não recria Form API nem decide
  submissão. Upload e crop continuam no widget contrib.

## Como verificar

- Gate Drupal 11+: `php scripts/validate-aculta-portal-drupal11.php`.
- Revisão de PR: checar cada regra que o diff toca. Regra violada é finding, não nota.
- Regressão: comportamentos já aceitos são invariantes em `docs/portal/ANTI-REGRESSAO.md`; diff que toca um deles precisa do caso executado.

## Links de perfil (`/u/<apelido>`)

Regra (fixada pelo responsável): valores inseridos em formulários e a apresentação preservam maiúsculas/minúsculas;
antes de gerar qualquer rota pública navegável, o parâmetro slug passa por
`Drupal\aculta_portal\Routing\PublicRouteSlug::normalize()`. A normalização comum remove espaços externos e
converte para minúsculas, sem transliterar, apagar pontuação, truncar ou persistir o resultado. Cada feature continua
responsável por validar a gramática, unicidade, palavras reservadas e acesso da própria rota. Os caminhos públicos
canônicos são minúsculos, mas o texto visível conserva a forma salva/editorial.

Para `/u/<apelido>`, `AccountNames::publicRouteSlug()` combina o normalizador do Portal com as regras de apelido e
retorna `NULL` para valores legados que não formam uma rota válida. Todo link é gerado pela rota
`aculta_user.perfil_publico` no propósito `main` (`DomainPurposeManager::routeUrl`), a partir do apelido **atual**;
nunca escrever `/u/...` à mão nem guardar a URL sem tags de cache.

- **Por quê:** caminhos duplicados por capitalização são ambíguos, prejudicam canonicalização/SEO e podem falhar na
  validação estrita do roteador Drupal. Separar o valor de URL da grafia de exibição evita alterar dados autorais;
  a função do Portal evita reimplementar lowercase de formas diferentes entre formulários.
- **Cache:** quem gera o link registra o perfil e o usuário na cache (`getCacheTags()` / `addCacheableDependency`),
  para que a troca de apelido invalide as páginas que mostram o link (caixa de autores, faixa da postagem,
  botão "Ver Perfil" da Minha conta).
- **Domínio:** o link é absoluto para o host do propósito `main` (ex.: a Revista aponta para o domínio principal),
  porque o perfil só existe nele. Relativo só funciona dentro do próprio host principal; por isso o link é
  gerado pelo serviço de domínio, sem host fixo no código.
- **Gate:** `php scripts/validate-profile-links.php` reprova caminho `/u/` literal, geração do perfil sem a regra
  de slug canônico, perda da capitalização na exibição e falta de registro na cache. Aplicar a mesma API do Portal
  em novos formulários e ações que gerem slugs públicos; valide sempre contra o contrato da rota proprietária.

## Nomes de arquivos enviados

- Uploads Drupal passam pela sanitização nativa configurada em `file.settings.yml`: transliteração para ASCII,
  espaços e caracteres não alfanuméricos convertidos em hífen, separadores repetidos removidos e nome em
  minúsculas. O Portal converte também a caixa da extensão após essa sanitização e antes da validação final de
  segurança do Core; o tipo/extensão permanece sujeito à allowlist do campo.
- A política vale para novos uploads atendidos pela API de upload do Core. Não renomear arquivos existentes nem
  alterar manualmente URI/entidades File; isso exige migração específica, auditoria de referências e plano de rollback.
- Não substituir `managed_file`, File API ou validações de extensão por renomeador próprio. Campos continuam donos
  da lista de extensões/tamanho; nome limpo não torna um arquivo seguro.
- Por quê: nomes canônicos evitam espaços, diferenças de caixa entre filesystems e caracteres problemáticos em URL;
  o Core já oferece evento e configuração pública de sanitização, então duplicar o fluxo criaria risco de extensão,
  colisão e inconsistência entre arquivo físico e entidade.
- Foram avaliados `file_rename` (renomeação editorial posterior) e `filefield_paths` (padrões de nome/destino por
  tokens durante o salvamento de entidade). Eles resolvem problemas mais amplos e diferentes; para sanitizar o nome
  original de uploads, a API de evento e as opções de `file.settings` do Core cobrem o contrato sem nova dependência.
  Referências: [FileEventSubscriber do Core 11.x](https://api.drupal.org/api/drupal/core%21modules%21file%21src%21EventSubscriber%21FileEventSubscriber.php/class/FileEventSubscriber/11.x),
  [configuração file.settings do Core](https://api.drupal.org/api/drupal/core%21modules%21file%21config%21install%21file.settings.yml/11.x),
  [File Rename](https://www.drupal.org/project/file_rename) e
  [File (Field) Paths](https://www.drupal.org/project/filefield_paths).

## Documentação e Status das capacidades do Portal

- Toda capacidade/função funcional nova do módulo principal `aculta_portal` deve ser registrada no README do módulo,
  na documentação específica do contrato e no resumo `description` do `aculta_portal.info.yml`. O Status administrativo
  apresenta esse resumo junto das extensões Portal habilitadas.
- Cada submódulo em `aculta_portal/modules/` declara `name` e `description` no próprio `.info.yml` e mantém um README
  ou ao menos um documento em `docs/`. O painel **ACULTA Portal > Status** deriva sua lista do registro de extensões
  habilitadas (`module_handler`), sem uma lista paralela manual.
- Ao criar uma capacidade, verifique que a documentação explica finalidade, fonte de verdade, fronteiras, segurança,
  cache/Domain e configuração/testes pertinentes. Atualize o changelog do Portal e a documentação específica no mesmo
  conjunto de mudanças.
- O gate `php scripts/validate-aculta-portal-drupal11.php` valida o README/descrição do Portal, a implementação do
  inventário de Status e a presença de descrição/documentação em cada submódulo.
- Cada novo serviço reutilizável do Portal deve receber a tag `aculta_portal.helper` e metadados em
  `PortalHelperRegistry`; cada serviço de integração runtime efetivamente aplicado deve receber a tag
  `aculta_portal.implementation` e metadados em `PortalImplementationRegistry`. A tela Status mantém seções próprias
  para Helpers ativos e Extras aplicados dentro de Implementações do ACULTA Portal. Novos registros exigem referência
  documental existente e atualização do gate; ver [IMPLEMENTATIONS.md](IMPLEMENTATIONS.md).
- Por quê: evita funcionalidades invisíveis para administradores, reduz divergência entre código e documentação e
  mantém o Status baseado no estado efetivo de habilitação do Drupal.

## Usuários de teste criados por agentes

Regra do responsável (2026-10-14). Vale para qualquer agente (Codex, Claude, ChatGPT ou outro).

- Quando um agente cria um **novo usuário para testes**, o usuário é **excluído ao fim dos testes**, na mesma
  sessão. Não fica no Runtime como resíduo.
- Nome obrigatório: prefixo `agente-teste-` (ex.: `agente-teste-20261014-1`), e-mail em `@testuser.invalid`.
  O prefixo identifica o que o agente criou; sem ele, o usuário não é removido automaticamente.
- O agente registra o `uid` criado e confirma a exclusão no relatório final (`drush php:eval` com contagem
  antes e depois).
- Contas que não foram criadas pelo agente nesta sessão **não são excluídas**. Em particular, `teste.comum`
  (uid 5) é conta de teste permanente do responsável e fica.
- A mesma regra vale para **elementos publicados para teste**: pedidos, pagamentos, posts (nós), mídias,
  galerias e demais conteúdos criados pelo agente para teste. Ao fim dos testes, o agente exclui o que criou.
  Conteúdo de exemplo com prefixo `[EXEMPLO]` (ver `docs/operations/TEST-DATA.md`) é removido somente após
  conferência do prefixo e do manifesto. Páginas institucionais reais, mesmo listadas num manifesto de QA,
  não são removidas sem confirmação explícita do responsável.
- Contas reais (autores, administradores, editores) nunca são excluídas por limpeza de teste.
- Limpeza: `php vendor/drush/drush/drush.php php:script scripts/cleanup-agent-test-users.php` (padrão: simulação;
  `--apply` exclui). Gate: `php scripts/validate-agent-test-users-rule.php`.

Motivo: contas de teste deixadas no Runtime aparecem em listagens de usuários, em relatórios de papéis e em
buscas de autor, e podem receber e-mails ou permissões por engano. Excluir ao fim do teste mantém o Runtime com
apenas as contas reais e as de teste permanentes.

## Papéis editoriais: função única

Decisão do responsável (2026-10-14). Vale para toda criação e moderação de conteúdo.

- **Uma só função editorial:** `content_editor` (Editor de conteúdo). Autor, editor e revisor da Wiki, e editor, autor e moderador do Fórum, são a mesma função. Não criar papéis novos para isso.
- O papel `content_editor` pode criar Media do tipo `document` para inserir PDFs editoriais pela biblioteca do CKEditor 5. Esse bundle usa `public://`: somente arquivos destinados à publicação podem ser enviados nele; documentos confidenciais exigem outro fluxo protegido.
- **Única exceção:** `wiki_contributor` (Colaborador da Wiki420). Continua sendo concedido automaticamente a toda conta com e-mail confirmado (`email_confirmed`).
- `wiki_editor` foi removido: suas permissões estão em `content_editor`.
- Perfil público obrigatório para quem edita: somente `content_editor` (`PublicProfilePolicy`).
- Gate: `php scripts/validate-aculta-user-roles.php`.

Motivo: poucos papéis na criação de conteúdo. Um papel por função evita combinações de permissões difíceis de auditar.

## Modelo visual: Notícia e Editorial são iguais

Regra do responsável (2026-10-14). Vale para toda alteração de exibição dos conteúdos publicados.

- **Notícia (`article`) e Editorial (`blog`) têm o mesmo modelo visual.** As visualizações padrão, teaser e completa têm os mesmos campos, a mesma ordem e as mesmas configurações de exibição.
- Mudança visual em um dos dois tipos vale para o outro na mesma entrega. Não criar exceção de layout por tipo.
- **Única diferença permitida de formulário:** o Editorial passa pelo fluxo de moderação (estado de moderação), que é funcional. Não há outra diferença registrada.
- Gate: `php scripts/validate-content-visual-parity.php` (deve ser PASS em toda mudança de exibição de Notícia ou Editorial).

Motivo: o leitor percebe a mesma página com dois layouts diferentes quando os tipos divergem, e a manutenção dobra.

## Papéis e hierarquia

Barreiras contra regressão (gate `php scripts/validate-role-hierarchy.php`):

- A cadeia de herança em `config/sync/role_inheritance.settings.yml` deve ser a decidida em `docs/portal/PAPEIS-PARTICIPACAO.md`. Mudança exige decisão do responsável e atualização do gate no mesmo commit.
- O mapa de herança não pode ter ciclo nem autorreferência (o formulário do painel também recusa).
- `administrator` herda de todos os papéis de negócio.
- Checagens por nome de papel em código customizado (`->hasRole(` e `in_array('papel', ...)`) não podem subir acima da linha de base registrada no gate (15 e 4). Acesso novo usa permissão.
- Papéis efetivos: para o usuário logado, o `role_inheritance` estende os papéis a cada requisição (`CurrentUserRoleShim`); para entidades de usuário, a herança também aparece em `getRoles()`. Confirme no Runtime antes de depender de um caso de borda.

Motivo: a hierarquia é decisão do responsável. Um comentário de código ou uma checagem por nome podem contradizê-la sem que nenhum teste pegue.

