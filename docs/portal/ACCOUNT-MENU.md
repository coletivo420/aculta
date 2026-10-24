# Menu da Minha conta

## S-R3-A — comparação do mesmo formulário em duas interfaces

A Minha Conta original continua em `/conta-legada` com a navegação e
JavaScript intocados. A raiz `/` do Domain ACCOUNT serve o hub do Studio. A experiência Studio independente `/studio/conta` oferece
edição de endereço, e-mail/segurança, senha e conexões em suas rotas,
chamando os **mesmos métodos e Form API** do Portal.
`PortalHooks` aplica a política única de Número/S-N e país BR tanto
em `/dados/endereco` quanto em `/studio/conta/endereco`.
O redirect pós-gravação volta à origem correspondente.

A edição de dados cadastrais continua em `/dados` até o formulário
do apelido público ser isolado. Nenhum comportamento atual do legado
foi retirado. PHPUnit/Runtime ainda pendentes.
[Contrato](../architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).

## Garantia de preservação — Minha Conta legada (decisão 2026-10-24)

**Esta interface não será retirada ao final da migração.**
A rota pública da Minha Conta original é **`/conta-legada`**
(`aculta_portal.dashboard`). A raiz `/` do Domain ACCOUNT serve o hub do
ACULTA Studio (`aculta_studio.hub`, caminho interno `/studio`). O caminho
`/minha-conta` não é mais rota e responde **404**, sem redirect.
Manter shell, menu desktop/mobile, `account-navigation.js`,
formulários, segurança, dados e integração existentes.

A nova **Minha Conta Studio** é `/studio/conta`; **Meu Perfil Studio**
é `/studio/perfil`, separado da Conta. As páginas antigas continuam
funcionais para comparação, inclusive depois de aprovar o Studio.
Regras e fases:
[Conta e Perfil separados](../architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).
Qualquer indicação antiga abaixo de remoção do shell/menu legado
foi revogada.


Data da revisão: 2026-10-14. Versões: `0.2.0-dev.26` a `0.2.0-dev.39`; extensão de seções no Portal `0.4.0-dev.15`.

A visão geral original fica em `/conta-legada`. A raiz do Domain ACCOUNT (`/`) é o hub do Studio. O caminho `/minha-conta` responde 404; nenhum link o usa (`docs/integrations/AUTHENTICATION.md`).

O menu lateral da conta (`/conta-legada` para a visão geral, `/dados`, `/seguranca`, `/conexoes`, `/meus-cursos`, `/meu-apoio`, `/configuracoes`) tem duas apresentações. As duas usam as mesmas rotas, a mesma ordem e o mesmo item ativo.

## Migração planejada para o ACULTA Studio — P0 (2026-10-24)

A direção aprovada para uma entrega futura é tornar `/studio` o hub,
oferecer `Meu Espaço` em `/studio/pessoal` e unificar a navegação global
com submenus contextuais. Meu Perfil será **editado** no Studio, mas seu
endereço público `/u/{apelido}` continuará no purpose `main`.
[Contrato e roadmap P0–P7](../architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md).

**P1-A criou apenas uma entrada independente em `/studio/pessoal` com links
para as páginas atuais da Conta, sem migrar formulários ou shell e sem
homologação Runtime.** O comportamento descrito nas seções seguintes permanece
**atual** e não deve ser retirado até haver equivalência de forms, acesso,
AJAX e navegação. O Portal continuará dono de dados, endereço
e segurança. Um formulário renderizado no Studio não pode ser envolvido
simultaneamente por `aculta_portal_shell` e shell Studio.

Durante a transição, a Minha Conta original fica em `/conta-legada`; a
raiz `/` é o hub do Studio. `/minha-conta` não é rota e responde 404,
protegido pelo `validate-aculta-portal-conta-root.php`. Não introduzir redirects de POST
nem reescrever `account-navigation.js` para operar como navegação
universal antes de caracterizar uploads, senha, OAuth, CSRF e estados AJAX.
O menu antigo somente será retirado na P7 após homologação P6.

## Desktop (a partir de 768px)

Lista vertical de links com destaque no item atual. Ícones e setas ficam ocultos. Nada mudou no desktop.

## Celular (até 767.98px)

Sanfona nativa (`details`/`summary`, sem JavaScript de abertura):

- cada seção vira um item com ícone, rótulo e seta;
- ao abrir um item, o conteúdo completo da seção é carregado por AJAX e entra no painel;
- só uma seção fica aberta por vez: abrir uma recolhe as demais;
- a seção atual começa aberta e carrega sozinha;
- "Sair" continua como linha simples, sem painel.

### Como a carga funciona

- `account-navigation.js` busca a página da seção com `requestDocument` (mesma origem, cabeçalho `X-Requested-With`) e copia o `.portal-account__body` da resposta para o painel. Assim a autorização e o cache são os da própria página.
- O painel tem `data-portal-account-accordion-panel` e `data-src` com o endereço da seção.
- No celular, a coluna de conteúdo lateral (`[data-portal-account-content]`) sai do DOM. Assim não há IDs duplicados.
- Se a carga falhar, o painel mostra um link para abrir a página.

### Ícones por seção (Bootstrap Icons)

| Seção | Ícone |
| --- | --- |
| Visão geral | `bi-grid` |
| Meu Apoio | `bi-heart` |
| Cursos | `bi-mortarboard` |
| Mensagens | `bi-chat-dots` (opcional; somente com as permissões nativas de mensagens) |
| Meus Dados | `bi-person-vcard` |
| Conexões | `bi-link-45deg` |
| Segurança | `bi-shield-lock` |
| Configurações | `bi-sliders` |
| Sair | `bi-box-arrow-right` |

## Arquivos

- `src/AccountShellBuilder.php`: monta os dois menus e a sanfona.
- O Portal chama `aculta_portal_account_sections_alter` e `aculta_portal_account_parent_sections_alter` para módulos opcionais acrescentarem seções e subrotas sem dependência funcional do Portal.
- Uma seção pode fornecer `badge_count` numérico; o Portal encaminha o valor sem markup ou classe visual, e o ACULTA420 escolhe sua apresentação.
- `templates/aculta-portal-shell.html.twig`: `menu` (desktop) e `menu_accordion` (celular).
- `css/account-portal.css`: regras de desktop e de celular.
- `js/account-navigation.js`: carga AJAX da sanfona e a regra de uma seção aberta por vez.

## Limites e pendências

- A carga autenticada (com login) não foi verificada pela automação: precisa de conferência no celular.
- Formulários dentro dos painéis dependem dos comportamentos do Drupal (`attachBehaviors`). Qualquer formulário que não funcionar deve ser reportado.

## Por que a raiz é o hub do Studio

Uma única URL pública por destino evita conteúdo duplicado e links antigos que chegam a um caminho técnico. A raiz do ACCOUNT segue o mesmo padrão de `/apoio`, servido em `/` no host SUPPORT: o caminho interno `/studio` não é público (404 direto), e o anônimo na raiz vai ao login.

A Minha Conta original tem URL própria, `/conta-legada`, porque ela não é a raiz. O caminho `/minha-conta` não é mais rota; `DomainPurposeRequestSubscriber::onRequestBeforeRouter` mantém o 404 explícito para ele.

## Barreira anti-regressão

- Gate: `php scripts/validate-aculta-portal-conta-root.php` (1142 verificações). Reprova:
  - um segundo uso do caminho interno no roteamento;
  - a remoção da guarda do hub na raiz;
  - um link literal a `/minha-conta` em código, template, JS, CSS, scripts ou configuração exportada (exceto `front` do Domain ACCOUNT, que é `/studio`, resolução interna);
  - o reenvio de confirmação fora de `/confirmar-email/reenviar`;
  - divergência entre `aculta_portal.info.yml` e o topo do `CHANGELOG.md`.
- Sonda privada do `aculta_deployer` (`deploy.json`, `private_probes`): a sonda de `/conta-interna` foi removida em 0.3.0-dev.13, junto com o caminho. A proteção principal é o gate acima.
- Verificação HTTP no servidor de testes, depois do deploy: `https://conta.aculta.toca.net.br/` anônimo redireciona ao login; logado, serve o hub do Studio; `https://conta.aculta.toca.net.br/conta-legada` anônimo redireciona ao login; logado, serve a Minha Conta original; `/minha-conta` e `/studio` respondem 404.
