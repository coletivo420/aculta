# Turnstile (anti-bot) do ACULTA Portal

Data da revisão: 2026-10-27. Versões: Portal `0.2.0-dev.23` (caixa de ativação), `0.2.0-dev.24` e `0.2.0-dev.25` (chaves por ambiente).

## Onde o CAPTCHA se aplica

O CAPTCHA é o módulo `captcha` com o desafio `turnstile/Turnstile`. Os pontos ativos são:

- `user_login_form` (login);
- `user_register_form` (cadastro);
- `user_pass` (recuperação de senha);
- formulários webform de contato e participação.

Os formulários de compra Commerce são uma exceção permanente: adicionar produto,
carrinho, cálculo de frete, identificação/cadastro de visitante e demais etapas
de checkout. O cadastro silencioso do Commerce acontece ao concluir uma compra;
ele não usa `user_register_form`. A tela separada de cadastro continua protegida.

O CAPTCHA global (`captcha.settings`, `enable_globally`) fica ligado, e as rotas de administração ficam fora dele. Quem tem o papel `email_confirmed` (com a permissão `skip CAPTCHA`) não recebe o desafio.

## Chaves por ambiente

O projeto tem dois ambientes: **teste** e **produção**. Cada um lê a própria chave, sempre pelo Drupal Key com provedor `env`. Nenhuma chave aparece em configuração exportada.

| Ambiente | Variável no arquivo de credenciais | Key do Drupal |
| --- | --- | --- |
| Produção | `TURNSTILE_KEYS_JSON` | `turnstile` |
| Teste | `TURNSTILE_TEST_KEYS_JSON` | `turnstile_test` |

- O valor é o Base64 de um JSON com `site_key` e `secret_key`.
- A escolha é automática: `TurnstileKeyOverride` lê o ambiente que o `aculta-deployer` gravou (`var/deployer/environment.json`) e aponta `turnstile.settings:keys` para a Key correspondente. Sem arquivo, o padrão é produção. Nada é gravado no banco.
- **Chaves de teste** (as chaves públicas da Cloudflare, que sempre passam) existem só no ambiente de teste. Nunca vão para produção.
- A chave de produção deve ser provisionada pelo deployer no banco criptografado, conforme `SECRETS.md`.

## Exceções (sem desafio, sempre)

Estes formulários **nunca** recebem o Turnstile, qualquer que seja a decisão do responsável na caixa de ativação. A
lista de código é `Captcha\TurnstileToggle::EXCEPTION_FORM_IDS`; cada item tem ponto com `status: false` em `config/sync`.

| Formulário (`formId`) | Quando aparece | Motivo | Ponto |
| --- | --- | --- | --- |
| `commerce_order_item_add_to_cart_form` e IDs com prefixo `commerce_order_item_add_to_cart_form_` | Adicionar itens comerciais ao carrinho da Loja, LMS ou Doações | Variações do form por produto também ficam isentas; o Portal remove o elemento caso o fallback global o insira. | `captcha.captcha_point.commerce_order_item_add_to_cart_form.yml` |
| `views_form_commerce_cart_form_default` | Atualizar, remover itens e fechar o carrinho compartilhado | O captcha global pode ignorar pontos desligados sem ponto base; o Portal também remove o elemento no alter do formulário. | `captcha.captcha_point.views_form_commerce_cart_form_default.yml` |
| `commerce_checkout_flow` | Base de todos os checkouts, inclusive Loja, LMS e Doações | Pagamento é feito no Mercado Pago; o desafio não protege a compra. O Captcha usa o form id base quando o ponto específico está desligado (`captcha_form_alter`); sem este ponto, `enable_globally` aplicaria o desafio. | `captcha.captcha_point.commerce_checkout_flow.yml` |
| `commerce_checkout_flow`, `commerce_checkout_flow_default`, `commerce_checkout_flow_fluxo_doacao` e IDs com prefixo `commerce_checkout_flow_` | Todos os flows Commerce ativos, incluindo Loja, LMS, Doações, assinaturas, frete, identificação e cadastro de visitante | A barreira de código cobre também IDs específicos sem ponto próprio. O desafio não protege pagamento e interromperia o checkout. | Pontos específicos existentes ficam desligados; `ShopCaptchaHooks` remove o fallback global. |
| `aculta_support_form` | Página de apoio (`/` do subdomínio de apoio, entrada das doações), sem envio próprio: a escolha do valor segue para o checkout | Sem Turnstile na entrada da doação para visitante. O formulário não cobra nem cria pedido; o pagamento é feito no checkout do Mercado Pago. | `captcha.captcha_point.aculta_support_form.yml` |
| `commerce_checkout_flow_fluxo_doacao` | Checkout da doação (apoio), etapa `/apoiar/{id}/donation` no MAIN | Mesma regra de pagamento via Mercado Pago; o visitante doa sem desafio. Form id derivado do plugin `fluxo_doacao`. | `captcha.captcha_point.commerce_checkout_flow_fluxo_doacao.yml` |

Além do Config Sync, `ShopCaptchaHooks` remove o elemento inserido pelo fallback
global de Captcha em formulários de produto, carrinho e checkout. Os prefixos
Commerce também são reconhecidos por `TurnstileToggle::setEnabled()`, para que
uma ativação global não reabilite pontos dinâmicos de compra. A exceção do
carrinho cobre o form `views_form_commerce_cart_form_default_{order}`. O widget
e o script global do Turnstile não aparecem nas rotas compartilhadas de produto,
carrinho e checkout.
| `aculta_login_popup_form` | Popup "Entrar" da barra multidomínio (visitante não autenticado) | Decisão do responsável: o modal de login não usa Turnstile. A validação do Core (`user.auth`, flood control e bloqueio de conta) continua, porque o formulário herda `UserLoginForm`. | `captcha.captcha_point.aculta_login_popup_form.yml` |
| `aculta_register_popup_form` | Cadastro em modal, aberto pelo convite do checkout de doação (visitante) e pela rota `/criar-conta/janela` | Decisão do responsável: o cadastro em modal não usa Turnstile. A validação e a criação da conta são as do Core (`RegisterForm`); a exceção não altera `user_register_form`, que continua protegido. | `captcha.captcha_point.aculta_register_popup_form.yml` |
| `aculta_messenger_new_conversation` | Nova conversa pela barra de mensagens (formulário de compor) | Só contas com e-mail confirmado (`email_confirmed`) chegam ao formulário. O desafio não protege esse envio, e o widget não é montado no fragmento carregado pela gaveta. A proteção contra envios em excesso é o limite do próprio Messenger (`flood`, 10 envios por hora por conta). | `captcha.captcha_point.aculta_messenger_new_conversation.yml` |
| `private_message_add_form` | Resposta nas conversas de mensagens (formulário nativo do Private Message, também na gaveta) | Mesma regra: só contas confirmadas escrevem. O widget não é montado no fragmento da gaveta, e o limite de envio do Messenger continua valendo. | `captcha.captcha_point.private_message_add_form.yml` |
| `aculta_messenger_conversation_relationship` | Opções da conversa na gaveta: adicionar, aceitar, rejeitar ou cancelar amizade, bloquear e desbloquear | Mesma regra do Messenger: só contas confirmadas agem nas conversas. O desafio bloquearia uma ação de um clique; o limite de pedidos de amizade (flood) e o bloqueio do próprio Messenger continuam valendo. | `captcha.captcha_point.aculta_messenger_conversation_relationship.yml` |

- **Cadastro pelo fluxo de compra:** `default`, `shipping`, doação avulsa e recorrente permitem checkout de visitante;
  o Commerce Core cria a conta silenciosamente após concluir o pedido e envia link para definir senha. O pane de e-mail
  mostra o estado de cadastro por AJAX, sem renderizar formulário de senha ou desafio para e-mails novos. O envio final,
  criação de conta e e-mail ainda dependem de homologação no Runtime.
- **Página de login `/entrar` não é exceção:** `user_login_form` continua com o desafio (`status: true`). O popup é um
  formulário separado, com ID próprio, e não substitui a página.
- **Flood control:** o popup não tem desafio anti-bot; a proteção contra tentativas repetidas é o flood control do Core
  (por IP e por conta). Não remover.
- **Barreira de regressão:** gate `php scripts/validate-turnstile-exceptions.php` (invariantes INV-TS-1 a INV-TS-4 em
  `docs/portal/ANTI-REGRESSAO.md`). Uma exceção nova exige atualizar a constante, o ponto em `config/sync`, esta tabela e o gate.

## Caixa de ativar/desativar

- Caminho: **ACULTA Portal > Turnstile (anti-bot)**, em `/painel-administrativo/aculta/turnstile`. Exige a permissão `administer site configuration`.
- A fonte da verdade é a própria configuração do CAPTCHA (`captcha.settings` e os pontos Turnstile). Não há configuração própria do Portal.
- **Ativar** só é aceito se a chave do ambiente existir. A caixa mostra só se ela está configurada, nunca o valor.
- **Desativar** exige marcar a confirmação do risco. Sem o Turnstile, cadastro, login e recuperação de senha ficam sem proteção contra robôs.
- Cada mudança é gravada no log do canal `aculta_portal`, com a conta que alterou.
- Como a mudança é de configuração, ela deve ser exportada para `config/sync` depois de feita.

## Procedimento de deploy

1. Provisionar a chave do ambiente (produção: deployer; teste: painel de credenciais).
2. Importar a configuração (`drush config:import`). A Key do ambiente precisa existir no banco: sem o import, o Key não encontra a chave.
3. Conferir na caixa de Turnstile que a chave aparece como configurada.

## Limites e pendências

- **Verificação com navegador:** o desafio não pode ser resolvido por script. Os formulários com Turnstile ainda não foram testados ponta a ponta com navegador real (cadastro, login, recuperação, contato e participação). Esse é o próximo passo de validação.
- **Estado em 2026-10-10:** a chave de teste está provisionada no ambiente de teste. A chave de produção não está provisionada neste ambiente.
- **Deprecações:** os contribs `captcha` e `key` emitem deprecações de atributos de plugin e de requisitos. Não são do código do Portal.
- **Incidente registrado:** uma variável nova no contrato de credenciais, sem a lista correspondente no carregador, derrubou o login com erro 500 (corrigido na PR #164). A regra está em `SECRETS.md`.
