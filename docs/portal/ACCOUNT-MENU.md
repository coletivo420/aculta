# Menu da Minha conta

Data da revisão: 2026-10-10. Versões: `0.2.0-dev.26` a `0.2.0-dev.39`.

A visão geral é a raiz do Domain ACCOUNT (`/`). O caminho técnico `/minha-conta` não é público e responde 404; nenhum link o usa (`docs/integrations/AUTHENTICATION.md`).

O menu lateral da conta (`/` para a visão geral, `/dados`, `/seguranca`, `/conexoes`, `/meus-cursos`, `/meu-apoio`, `/configuracoes`) tem duas apresentações. As duas usam as mesmas rotas, a mesma ordem e o mesmo item ativo.

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
| Meus Dados | `bi-person-vcard` |
| Conexões | `bi-link-45deg` |
| Segurança | `bi-shield-lock` |
| Configurações | `bi-sliders` |
| Sair | `bi-box-arrow-right` |

## Arquivos

- `src/AccountShellBuilder.php`: monta os dois menus e a sanfona.
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
