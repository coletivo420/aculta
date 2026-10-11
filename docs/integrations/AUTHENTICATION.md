# Login, Turnstile e redirecionamentos

## Login e página de retorno

Os links de login da navegação da ACULTA levam à Conta e carregam o caminho
visível da página atual, além do `Domain purpose` de origem. O destino fica na
sessão durante o login tradicional ou o ciclo OAuth; após autenticação, o
Portal reconstrói a URL no Domain correto. Rotas de login, logout, recuperação
e OAuth não podem virar destinos de retorno.

O parâmetro Drupal `destination`, quando já fornecido por uma rota protegida,
tem precedência. O Portal preserva **path e query string** da página de origem:
uma busca como `/wiki/busca?q=termo` deve retornar com `q=termo` depois do
login. O menu varia por `url.path` e `url.query_args` para não reutilizar em
cache um destino de outra busca/filtro. Um acesso novo e direto a `/entrar`
sem destino válido limpa qualquer destino abandonado da sessão.

O destino salvo é decomposto em path interno + query estruturada; URLs externas,
protocol-relative, fragments e rotas de autenticação/recuperação não são
aceitas como retorno. Social Auth preserva `destination` durante o
redirecionamento ao provedor; quando o parâmetro ACULTA de purpose não é
propagado pelo módulo upstream, o Portal conserva o purpose já capturado para o
mesmo path **e query** em vez de sobrescrevê-lo com ACCOUNT. Quando não há página anterior ou destino explícito, o destino público padrão é
a raiz do Domain ACCOUNT (`/` em `conta.aculta.org` ou no alias Homelab).
A visão geral (rota `aculta_portal.dashboard`) é servida somente nessa raiz.
O caminho técnico `/minha-conta` não é público: requisição direta responde 404
(guarda em `DomainPurposeRequestSubscriber::onRequestBeforeRouter`), no mesmo padrão de `/apoio`.
Nenhum link gerado aponta para esse caminho.

A página genérica de perfil do Drupal não é publicada. A rota técnica
`user.page` continua registrada porque Core Navigation, recuperação de senha e
outros fluxos upstream ainda geram URLs para ela. Em qualquer host onde seja
alcançada por um usuário autorizado, ela redireciona para a raiz absoluta do
Domain ACCOUNT e nunca renderiza o perfil genérico. `/identidade` também não é
destino de login.

## Endpoints HTTP de autenticação

O projeto não publica `user.login.http` nem `user.pass.http`. Esses endpoints
JSON do Core autenticam sem construir Form API e, portanto, não participam da
política Turnstile aplicada aos formulários públicos. Mantê-los ao lado do login
tradicional protegido criaria um caminho alternativo sem CAPTCHA.

Se uma API de autenticação for necessária no futuro, ela deve ser desenhada e
protegida explicitamente; não reativar esses endpoints como atalho.

## Google OAuth

O início do fluxo é `/oauth/google`; o retorno é
`/oauth/google/retorno`. As URIs cadastradas para o cliente Web são:

| Ambiente | URI de callback | Origem JavaScript |
| --- | --- | --- |
| Homelab | `https://conta.aculta.toca.net.br/oauth/google/retorno` | `https://conta.aculta.toca.net.br` |
| Produção | `https://conta.aculta.org/oauth/google/retorno` | `https://conta.aculta.org` |

Client ID e segredo não são documentados nem impressos. O responsável
configurou o cliente OAuth Google; nenhuma alteração foi feita na Hostinger ou
no Drupal de produção neste trabalho.

As configurações exportadas de Social Auth Google mantêm `client_id` e
`client_secret` vazios. As Keys `google_oauth_client_id` e
`google_oauth_client_secret` usam o provider Environment com
`GOOGLE_OAUTH_CLIENT_ID` e `GOOGLE_OAUTH_CLIENT_SECRET`; os objetos Key
Configuration Override injetam esses valores no runtime quando as Keys os
resolvem. Native Environment e Secure Bootstrap Adapter são operacionalmente
equivalentes e não alteram as rotas/callbacks OAuth. Consulte o
[ACULTA Secrets Contract](../operations/SECRETS.md).

Na R0.4, o Homelab migrou para o Secure Bootstrap Adapter. O storage bruto e o
YAML mantêm ambos os campos vazios; o loader local publica as variáveis para
Drupal Key, e os Config Overrides fornecem os valores somente à configuração
efetiva em memória. Web e Drush usam o mesmo bootstrap. Hostinger ainda não foi
provisionada. OAuth e seus callbacks não dependem do adapter escolhido.

### Vínculos em Minha Conta

A página `/conexoes` lê os vínculos da entidade Social Auth do usuário atual.
Para Google, o identificador persistido é `social_auth_google`; `google` é
somente o nome curto usado na URL. A consulta da Conta deve usar o identificador
persistido, ou um vínculo salvo pode existir sem aparecer como conectado na
interface. Após um callback autenticado, o Portal registra o e-mail retornado
pelo Google em `additional_data.provider_email` na entidade Social Auth; a área
privada exibe esse e-mail e não mostra tokens. Vínculos antigos sem esse dado
continuam identificados como conectados, informam que o endereço ainda não está
disponível e oferecem “Atualizar conexão Google” para buscar novamente o perfil.

O Homelab usa Social API 4.0.2. Para PHP 8.4, o projeto aplica pelo Composer
uma correção pequena proposta no
[issue 3593752 do Social API](https://www.drupal.org/project/social_api/issues/3593752):
o parâmetro opcional `Request` recebe tipo nullable explícito. Isso elimina o
aviso de depreciação visto durante a inicialização OAuth; o aviso isolado não
prova a causa de uma falha no callback. O fluxo completo com uma conta Google
real continua pendente de validação interativa.

## Turnstile

O Turnstile é o desafio ativo para visitantes anônimos nos formulários públicos
em rotas não administrativas. O Turnstile global protege formulários de
visitantes anônimos; o papel `authenticated` usa `skip CAPTCHA` para não receber
o widget. As chaves são
mantidas na configuração de Key do runtime; seus valores não pertencem ao
código nem à documentação. Após o
responsável confirmar que o Turnstile estava funcionando, foi desativado o
fallback de CAPTCHA solicitado. A aparência é `always`, para deixar o widget
visível desde o carregamento; `interaction-only` o ocultava para a maioria das
pessoas.

CAPTCHA é uma barreira para visitantes anônimos: formulários públicos em rotas
não administrativas exigem Turnstile, salvo quando o fluxo não submete um
formulário Drupal. Isso inclui o formulário anônimo de cadastro e formulários
públicos de contato/participação. O papel `authenticated` recebe a permissão
oficial `skip CAPTCHA`, então qualquer usuário autenticado pode preencher os
formulários sem desafio. A exceção de cadastro sem CAPTCHA é o fluxo OAuth:
login e criação de conta Google ocorrem no redirecionamento/callback do
provedor, sem submeter o formulário Drupal de cadastro. Essa exceção não cria
um bypass de CAPTCHA para formulários anônimos. Formulários continuam sujeitos
às demais validações, permissões e proteções normais. O login tradicional
anônimo continua bloqueado quando não apresenta token Turnstile válido; não
existe bypass por JavaScript.

O smoke HTTP desta revisão confirmou que o formulário de login carrega o
JavaScript e o markup do widget Turnstile, sem markup de CAPTCHA de imagem ou
reCAPTCHA. A validação de token e o login por senha não foram repetidos com
interação de navegador nesta alteração.

## Estado operacional desta revisão

- O fallback do Social Auth usa a raiz do Domain ACCOUNT (`AccountRouteSubscriber`), não o caminho interno.
- a UI genérica de `/user` foi removida da experiência: `user.page` é mantida
  apenas como rota técnica de compatibilidade e redireciona usuários autenticados
  para a raiz ACCOUNT; `/identidade` não é destino de login;
- `/minha-conta` não é público: responde 404. A front page do Domain ACCOUNT resolve a visão geral na raiz.
- Os links de login renderizados em MAIN, MAGAZINE, WIKI e COURSES carregaram
  `destination` com o caminho visível e o purpose respectivo.
- O link Google gerado em `/entrar` preservou `destination`; o início OAuth
  respondeu com redirecionamento para Google e callback Homelab correto.
- A revisão de `/conexoes` identificou que a entidade Social Auth usa o plugin
  ID `social_auth_google`, enquanto o Portal consultava o nome curto `google`.
  A consulta foi corrigida e a renderização reconhece o vínculo persistido
  durante a validação Drupal. Isso valida a leitura pela Conta, não substitui
  um novo teste interativo de consentimento/callback Google.
- A depreciação PHP 8.4 deixou de aparecer no início OAuth após aplicar o
  patch Composer. O retorno com consentimento real do Google não foi executado
  nesta revisão.
- Nenhuma configuração ou deploy de produção foi alterado.


## Ownership de apresentação

A supressão do Page Title duplicado no shell privado pertence ao
`aculta_portal`, que conhece o render array `aculta_portal_shell`. O tema não
inspeciona nomes de rotas do Portal para decidir comportamento funcional. Essa
separação evita afetar Wiki, Cursos, Apoio ou outros routes `aculta_portal.*`.


### Gates anti-regressão de autenticação

Antes de alterar login/OAuth/Conta, validar pelo menos:

- `/wiki/busca?q=termo` -> login -> retorno mantém `q=termo`;
- login vindo de MAIN/MAGAZINE/WIKI/COURSES retorna ao mesmo purpose;
- acesso direto a `/entrar` não reutiliza destination antigo;
- `user.page` continua registrado para Core Navigation, mas nunca renderiza o
  perfil genérico;
- `user.login.http` e `user.pass.http` permanecem indisponíveis;
- conta criada por Social Auth sem senha escolhida não oferece
  “Desconectar Google”;
- callback Google usa `SocialAuthUserInterface::getId()` como identificador do
  provedor.

## Aceite dos termos (popup persistente, 0.3.0-dev.16)

- **Origem do aceite:** o checkbox obrigatório "Li e aceito os Termos de Uso e a Política de Privacidade." do cadastro por e-mail/senha (`user_register_form`, `FormHooks`) grava o aceite em `aculta_account_terms` (`RegistrationTerms::record`). O mesmo registro é lido pelo popup (`AccountTermsPolicy`). Quem se cadastra pelo formulário nunca vê o popup.
- **OAuth (Google) e outras vias sem o checkbox:** não gravam aceite. O popup aparece na primeira página restrita até o aceite.
- **Páginas restritas (só usuário logado):** conta (`_aculta_domain_purpose` = account), checkout (`commerce_checkout.*`) e cursos/áreas de membro (`lms.*`). Carrinho e doação são públicos e não recebem o popup; visitante anônimo nunca recebe o popup. A opção de rota `_aculta_terms_required` é definida por `DomainRouteSubscriber`.
- **Comportamento:** sem aceite, a página restrita carrega normalmente, na mesma URL, com o popup por cima (o popup é o bloqueio). Não há redirecionamento para `/aceite-termos` nem resposta 403 de página. As ações protegidas (checkout e cursos) seguem negadas pelo servidor (`ProtectedRouteAccessCheck`); a página de acesso negado também mostra o popup. O popup não tem botão de fechar, Esc é cancelado (`js/account-terms-modal.js`), e clique fora não fecha. O foco fica no modal (`showModal()`).
- **Após aceitar:** `POST /termos/aceitar` grava o aceite e volta à mesma página (destino validado como caminho local).
- **Versão dos termos:** alterar `settings.reset_date` do acordo (ou aumentar a frequência) faz todos aceitarem de novo, pelo cálculo de `Agreement::getAgreementFrequencyTimestamp()`.
- **Todos os hosts:** o aceite é por usuário no banco, então vale em principal, conta e loja.
- **Página antiga:** `/aceite-termos` responde 404 (`DomainRouteSubscriber`; a rota do módulo agreement existe só por nome).
- **Fundo:** `css/account-terms-modal.css` desfoca a página atrás do popup.
