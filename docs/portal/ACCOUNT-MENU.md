# Menu da Minha conta

## Garantia de preservação — Minha Conta legada (decisão 2026-10-24)

**Esta interface não será retirada ao final da migração.**
A rota de entrada legada é a raiz **`/` do Domain ACCOUNT**, com
`aculta_portal.dashboard` internamente. O caminho técnico direto
`/minha-conta` **continua 404**, sem modificação de subscriber.
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

A visão geral é a raiz do Domain ACCOUNT (`/`). O caminho técnico `/minha-conta` não é público e responde 404; nenhum link o usa (`docs/integrations/AUTHENTICATION.md`).

O menu lateral da conta (`/` para a visão geral, `/dados`, `/seguranca`, `/conexoes`, `/meus-cursos`, `/meu-apoio`, `/configuracoes`) tem duas apresentações. As duas usam as mesmas rotas, a mesma ordem e o mesmo item ativo.

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

Durante a transição, preservar a raiz `/` do Domain ACCOUNT; a rota
`/minha-conta` continua técnica e bloqueada, protegida pelo
`validate-aculta-portal-conta-root.php`. Não introduzir redirects de POST
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

## Por que a visão geral só existe na raiz

Uma única URL pública por destino evita conteúdo duplicado, links antigos que chegam a um caminho técnico e a dependência de o usuário lembrar um nome interno. O mesmo princípio vale para `/apoio`, que é servido somente em `/` no host SUPPORT.

O caminho `/minha-conta` continua existindo só como identificador da rota `aculta_portal.dashboard`. Ele não é público: a guarda em `DomainPurposeRequestSubscriber::onRequestBeforeRouter` responde 404 para qualquer requisição cujo caminho original não seja `/`.

## Barreira anti-regressão

- Gate: `php scripts/validate-aculta-portal-conta-root.php` (1142 verificações). Reprova:
  - um segundo uso do caminho interno no roteamento;
  - a remoção da guarda da visão geral;
  - um link literal a `/minha-conta` em código, template, JS, CSS, scripts ou configuração exportada (exceto `front` do Domain ACCOUNT, que é resolução interna);
  - o reenvio de confirmação fora de `/confirmar-email/reenviar`;
  - divergência entre `aculta_portal.info.yml` e o topo do `CHANGELOG.md`.
- Sonda privada do `aculta_deployer` (`deploy.json`, `private_probes`): a sonda de `/conta-interna` foi removida em 0.3.0-dev.13, junto com o caminho. A proteção principal é o gate acima.
- Verificação HTTP no servidor de testes, depois do deploy: `https://conta.aculta.toca.net.br/` deve servir a conta; `https://conta.aculta.toca.net.br/minha-conta` deve responder 404.
