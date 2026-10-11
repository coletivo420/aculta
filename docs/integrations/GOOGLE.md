# Integrações Google

Data da revisão arquitetural: 2026-10-06.

## Objetivo

O site deve entrar em produção preparado para uma integração Google mínima e
evoluir depois da aprovação/homologação institucional.

As integrações são separadas em estados:

- **PREPARED**: código/configuração suporta ativação, mas serviço não está ativo;
- **LAUNCH-MINIMAL**: integração mínima autorizada para produção;
- **POST-APPROVAL**: recursos que dependem de aprovação, OAuth ampliado ou nova
  decisão de produto.

Não ativar um recurso apenas porque sua documentação existe.

## Estado atual

O projeto já possui:

- Social Auth + Social Auth Google;
- Key;
- `GOOGLE_OAUTH_CLIENT_ID` e `GOOGLE_OAUTH_CLIENT_SECRET` como providers de
  ambiente;
- Config Override apontando Social Auth Google para essas keys;
- configuração exportada sem client ID/secret reais.

### Google Login — URIs do cliente Web

O Social Auth Google usa um fluxo OAuth do lado do servidor. O fluxo começa em
`/oauth/google` e retorna a `/oauth/google/retorno`. Cadastre os URIs de
redirecionamento exatos abaixo no cliente OAuth Web:

Após autenticação concluída, o Social Auth direciona a pessoa para a raiz do
Domain ACCOUNT. Esse Domain serve a visão geral (`aculta_portal.dashboard`) somente na raiz `/`;
o caminho técnico `/minha-conta` responde 404. A rota `user.page` permanece somente para compatibilidade com redirects
do Core e redireciona para a raiz da Conta, sem renderizar o perfil genérico;
`/identidade` também não é um destino válido. O destino preferencial é a página visitada
antes do login, transportada como `destination` e acompanhada pelo purpose do
Domain durante a autenticação. Isso vale para senha e Google OAuth. Sem um
destino anterior, o fallback é a rota do Portal “Minha conta”. O fluxo e seus
limites estão documentados em [Autenticação](AUTHENTICATION.md).

O Social API 4.0.2 emite uma depreciação PHP 8.4 ao instanciar o gerenciador
OAuth. O projeto aplica por Composer o ajuste upstream proposto em
[Drupal.org #3593752](https://www.drupal.org/project/social_api/issues/3593752)
(MR !17), sem editar o módulo contrib diretamente. Esse aviso não identifica,
por si só, uma falha de autenticação; a conclusão do callback Google ainda
precisa de validação interativa após as alterações.

### Acesso OAuth do administrador Drupal

O perfil Google atualmente vinculado ao administrador Drupal (UID 1) só pode
autenticá-lo quando `social_auth.settings:disable_admin_login` está desativado.
Essa opção do Social Auth é global: ela permite autenticação OAuth para UID 1
em qualquer provedor Social Auth habilitado, não apenas Google. No momento,
Google é o provedor Social Auth ativo. Alterações futuras que habilitem outros
provedores devem reavaliar essa exposição antes de ativá-los. O Drupal continua
sendo responsável por validar o usuário e o vínculo OAuth existente.

| Ambiente | URI de redirecionamento autorizado |
| --- | --- |
| Homelab | `https://conta.aculta.toca.net.br/oauth/google/retorno` |
| Produção | `https://conta.aculta.org/oauth/google/retorno` |

Se o cliente também usar APIs Google iniciadas diretamente por JavaScript,
cadastre estas origens autorizadas (origem é somente esquema + host, sem
caminho):

- `https://conta.aculta.toca.net.br`
- `https://conta.aculta.org`

As origens JavaScript não substituem os URIs de callback. O ambiente de
produção deve manter seu URI cadastrado antes de ativar o novo código; não
inclua localhost ou hosts de desenvolvimento adicionais no cliente de
produção sem necessidade.

Esse padrão deve ser preservado.

## Princípio de credenciais

Nunca versionar:

- OAuth client secret;
- service account;
- refresh token;
- API key privada;
- credentials JSON;
- Measurement Protocol API secret.

IDs públicos de tags/propriedades podem ser configuráveis, mas não devem ser
hardcoded no tema.

Segredos entram por ambiente/Key ou mecanismo equivalente revisado.

### Homelab e importação de configuração

O contrato portátil está definido em
[ACULTA Secrets Contract](../operations/SECRETS.md). Homelab e Hostinger usam
os mesmos Key IDs, `GOOGLE_OAUTH_CLIENT_ID`,
`GOOGLE_OAUTH_CLIENT_SECRET` e Config Overrides. Só muda o adapter operacional:
environment nativo ou arquivo seguro carregado no bootstrap. Drupal e Social
Auth não dependem do sistema operacional, do PHP-FPM ou do fornecedor.

O Configuration Sync e o storage bruto do Homelab mantêm `client_id` e
`client_secret` vazios após a R0.4. O Secure Bootstrap Adapter fornece as
variáveis ao Drupal Key para web e Drush; a configuração efetiva é preenchida
somente em memória pelos Config Overrides. Hostinger Web/Cloud ainda não foi
provisionada. Não use a leitura efetiva como prova de ausência de segredo;
essa prova deve ler `config.storage` e o sync diretamente.

Não preencher valores reais em Configuration Sync nem imprimir credenciais em
Git, documentação, logs, URLs ou histórico de shell.

### Segurança da desconexão

A conexão Google só pode ser removida pela interface da Conta quando existir uma
senha local **escolhida pela pessoa usuária**. Contas criadas via Social Auth
recebem o marcador `aculta_portal/social_auth_password_unset`; um hash de senha
gerado internamente não deve ser interpretado como senha conhecida. A área
Conexões e a área Segurança usam a mesma regra.

No callback, o identificador externo vem de
`SocialAuthUserInterface::getId()`; o e-mail do provedor é metadado de
apresentação e não substitui o ID persistido nem o Drupal User como fonte de
verdade.

## Matriz

| Integração | Lançamento | Pós-aprovação | Fonte/owner |
| --- | --- | --- | --- |
| Search Console | LAUNCH-MINIMAL | manter/expandir | Google Search Console |
| GA4 / Google Tag | LAUNCH-MINIMAL, sujeito a consentimento | eventos avançados | Google Analytics |
| Google Tag Manager | candidato preferencial de tagging | ampliar destinations/events | Google Tag Manager |
| Google Login | PREPARED; ativar somente com credenciais/homologação | ampliar se necessário | Social Auth Google |
| Google Classroom | PREPARED | integração API/add-on conforme caso | Google Classroom |
| Google Workspace | PREPARED | operacional institucional | Google Workspace |
| Google Ad Grants | não é código de lançamento | POST-APPROVAL | Google Ads/Ad Grants |
| YouTube Nonprofit | não é código de lançamento | POST-APPROVAL | YouTube |
| Google Maps Platform | somente se houver caso real | POST-APPROVAL | Maps Platform |

Google for Nonprofits atualmente lista Workspace for Nonprofits, Ad Grants,
YouTube para organizações sem fins lucrativos e Google Maps Platform entre os
produtos do programa. A aprovação no programa não deve ser confundida com
autorização automática de escopos OAuth ou Classroom.

## Search Console

### Estratégia preferida

Criar uma **Domain Property** para `aculta.org` e verificar via DNS.

Vantagem arquitetural: a propriedade de domínio cobre protocolos e subdomínios,
portanto é adequada ao desenho MAIN/ACCOUNT/SUPPORT/MAGAZINE/WIKI/SHOP/COURSES/
FORUM.

A verificação DNS pertence à infraestrutura/DNS, não ao tema.

### Fallback

Se DNS não estiver disponível, Search Console aceita outros métodos para
propriedades URL-prefix, como meta tag, arquivo HTML, Analytics ou Tag Manager.

Se for necessário suportar meta verification no Drupal:

- configurar token por ambiente/config segura;
- injetar no `<head>` por mecanismo Drupal;
- não editar Twig apenas para inserir token;
- não acoplar token ao tema.

### Sitemap

Simple XML Sitemap já existe no projeto.

No lançamento:

- confirmar sitemap público;
- confirmar canonicals;
- submeter sitemap apropriado no Search Console;
- não indexar ACCOUNT/admin/private flows.

## Google Analytics 4 e Google Tag

A documentação oficial do Google recomenda Google Tag Manager como opção
inicial de tagging.

No ecossistema Drupal, o módulo legado `drupal/google_analytics` está marcado
como obsoleto e recomenda migração para **Google Tag 2.x**.

Candidato preferencial atual:

`drupal/google_tag:^2.0`

Release pesquisada:

`2.0.9`

Compatibilidade:

Drupal ^9.5 || ^10 || ^11.

Security Advisory Policy:

coberto.

### Arquitetura proposta

Não instalar nesta documentação.

Quando a integração for executada:

1. instalar Google Tag;
2. configurar container/tag ID via configuração apropriada;
3. integrar consentimento;
4. excluir rotas administrativas e outras rotas inadequadas;
5. testar todos os purposes públicos;
6. validar Tag Assistant/DebugView;
7. documentar eventos.

Preferência inicial:

uma estratégia de mensuração coerente para a família `*.aculta.org`, com
relatórios distinguindo hostname/purpose, em vez de snippets independentes
espalhados por temas e módulos.

A decisão exata de property/stream/container deve ser registrada no momento da
implementação.

### Consentimento

Analytics não deve ser introduzido ignorando privacidade/LGPD.

Candidato atual:

`drupal/klaro:^3.1`

Release pesquisada:

`3.1.1`

Compatibilidade:

Drupal ^10.2 || ^11.

Security Advisory Policy:

coberto.

A release atual declara suporte ao Google Consent Mode v2.

Antes de adotar, revisar:

- política de privacidade;
- categorias/finalidades;
- analytics storage;
- ad storage se Ad Grants/Ads entrar;
- consent defaults;
- comportamento para usuários autenticados;
- acessibilidade do diálogo.

Não implementar banner custom se uma solução upstream adequada for adotada.

## Eventos

No lançamento mínimo, preferir coleta padrão/medição revisada.

Eventos custom só entram com especificação.

Exemplos futuros possíveis:

- cadastro concluído;
- envio de formulário institucional;
- início/conclusão de apoio;
- inscrição/início/conclusão de curso;
- publicação de tópico;
- participação Wiki;
- e-commerce da Loja quando existir.

Nunca enviar como parâmetros:

- e-mail;
- nome;
- endereço;
- telefone;
- texto de comentário;
- título privado;
- identificador sensível.

Commerce continua fonte de verdade financeira; Analytics é mensuração, não
ledger.

## Google Login

A infraestrutura já está PREPARED.

Ativar somente quando:

- OAuth app correto existir;
- redirect URIs de produção estiverem cadastradas;
- consent screen estiver coerente;
- chaves entrarem por ambiente;
- fluxo ACCOUNT for validado;
- logout/session multidomínio passar.

Não usar OAuth Google como fonte de verdade de conta.

Drupal User continua fonte.

## Google Classroom

"Google Class" neste projeto é tratado como **Google Classroom**.

Classroom não substitui Drupal LMS.

Fonte de verdade continua:

- Group para matrícula;
- Drupal LMS para curso/progresso.

Classroom será uma integração externa opcional.

### Estado inicial

PREPARED / desabilitado.

Não solicitar scopes Classroom no lançamento se não houver feature ativa.

A documentação do Google exige configurar OAuth consent e escolher scopes
Classroom conforme os dados/ações necessários.

### Casos futuros possíveis

A decisão funcional deve escolher explicitamente um caso, por exemplo:

- listar cursos Classroom relacionados;
- criar coursework que aponta para conteúdo ACULTA;
- compartilhar conteúdo ACULTA com Classroom;
- sincronizar subconjunto de cursos;
- construir Classroom add-on.

Não implementar sincronização bidirecional genérica sem especificação.

### Adapter

Se Classroom entrar:

- criar adapter/serviço dedicado, preferencialmente isolado do controller;
- credenciais/tokens fora do Git;
- scopes mínimos;
- idempotência;
- tratamento de revogação;
- logs sem dados sensíveis;
- feature flag/config para desligar integração;
- LMS continua funcional se Google estiver indisponível.

## Google Workspace for Nonprofits

Após aprovação institucional, documentar operacionalmente:

- domínio Workspace;
- contas institucionais;
- aliases/e-mail;
- políticas de acesso;
- relação com SMTP;
- contas usadas para OAuth/Analytics/Search Console.

Não acoplar autenticação administrativa do Drupal à disponibilidade do
Workspace sem decisão específica.

## Google Ad Grants

É integração POST-APPROVAL.

O código do Portal não deve conter lógica Ad Grants.

Dependências possíveis:

- Analytics/Google Tag;
- consentimento;
- landing pages públicas;
- conversões bem definidas.

Qualquer conversão precisa usar eventos sem PII.

## YouTube e Maps

Somente integrar quando houver caso funcional real.

YouTube:

- incorporar/publicar conteúdo institucional;
- consentimento para embeds de terceiros se aplicável.

Maps:

- não carregar biblioteca/API apenas para exibir endereço simples;
- usar somente quando mapas interativos/geocoding realmente forem necessários;
- chaves com restrições de origem/API.

## Fases

### G0 — Prepared

Já em andamento:

- OAuth via Key/env;
- documentação;
- SEO/canonical/sitemap;
- arquitetura desacoplada.

### G1 — Production Minimum

Antes/na entrada em produção:

- Search Console Domain Property;
- sitemap;
- Google Tag/GA4, se aprovado internamente;
- consentimento adequado;
- Tag Assistant/DebugView;
- no PII;
- Google Login somente se homologado.

### G2 — Google for Nonprofits

Depois da aprovação:

- Workspace;
- Ad Grants;
- YouTube Nonprofit;
- Maps credits quando houver caso;
- governança das contas Google.

### G3 — Learning Integration

Depois de requisitos e OAuth:

- Classroom API/add-on/share;
- adapter;
- scopes mínimos;
- testes de falha;
- LMS independente.

## Testes

Para qualquer integração Google:

- ausência de segredo no Git;
- feature desligada sem credencial;
- falha externa não quebra Drupal;
- Homelab não envia dados reais por padrão;
- produção habilita apenas o explicitamente aprovado;
- CSP/headers revisados;
- consentimento respeitado;
- nenhuma PII enviada;
- cache correto;
- multi-domain;
- logs seguros.

## Código futuro

As implementações devem preferir:

- módulos Drupal estáveis;
- Key/env;
- serviços/adapters;
- configuração exportável sem segredo;
- feature flags/config;
- DI;
- testes.

Não inserir snippets Google diretamente em Twig, `html.html.twig`,
`page.html.twig` ou JS do tema.
