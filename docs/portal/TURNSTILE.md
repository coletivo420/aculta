# Turnstile (anti-bot) do ACULTA Portal

Data da revisão: 2026-10-10. Versões: Portal `0.2.0-dev.23` (caixa de ativação), `0.2.0-dev.24` e `0.2.0-dev.25` (chaves por ambiente).

## Onde o CAPTCHA se aplica

O CAPTCHA é o módulo `captcha` com o desafio `turnstile/Turnstile`. Os pontos ativos são:

- `user_login_form` (login);
- `user_register_form` (cadastro);
- `user_pass` (recuperação de senha);
- formulários webform de contato e participação.

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
| `commerce_order_item_add_to_cart_form` | Adicionar ao carrinho (loja) | Carrinho não é conta; o desafio quebraria a compra. | `captcha.captcha_point.commerce_order_item_add_to_cart_form.yml` |
| `commerce_checkout_flow_default` | Checkout da loja, inclusive o cadastro feito pelo fluxo de compra para usuário não autenticado | Pagamento é feito no Mercado Pago; o desafio não protege a compra e atrapalharia o cadastro pela compra. | `captcha.captcha_point.commerce_checkout_flow_default.yml` |
| `aculta_login_popup_form` | Popup "Entrar" da barra multidomínio (visitante não autenticado) | Decisão do responsável: o modal de login não usa Turnstile. A validação do Core (`user.auth`, flood control e bloqueio de conta) continua, porque o formulário herda `UserLoginForm`. | `captcha.captcha_point.aculta_login_popup_form.yml` |
| `aculta_messenger_new_conversation` | Nova conversa pela barra de mensagens (formulário de compor) | Só contas com e-mail confirmado (`email_confirmed`) chegam ao formulário. O desafio não protege esse envio, e o widget não é montado no fragmento carregado pela gaveta. A proteção contra envios em excesso é o limite do próprio Messenger (`flood`, 10 envios por hora por conta). | `captcha.captcha_point.aculta_messenger_new_conversation.yml` |
| `private_message_add_form` | Resposta nas conversas de mensagens (formulário nativo do Private Message, também na gaveta) | Mesma regra: só contas confirmadas escrevem. O widget não é montado no fragmento da gaveta, e o limite de envio do Messenger continua valendo. | `captcha.captcha_point.private_message_add_form.yml` |

- **Cadastro pelo fluxo de compra:** o cadastro feito pelo checkout segue a mesma regra. Hoje o checkout não cria conta
  (`guest_new_account: false`, login do checkout com `allow_registration: false`). Se a criação de conta for ligada no
  checkout, ela continua sem Turnstile pelo ponto `commerce_checkout_flow_default`.
- **Página de login `/entrar` não é exceção:** `user_login_form` continua com o desafio (`status: true`). O popup é um
  formulário separado, com ID próprio, e não substitui a página.
- **Flood control:** o popup não tem desafio anti-bot; a proteção contra tentativas repetidas é o flood control do Core
  (por IP e por conta). Não remover.
- **Barreira de regressão:** gate `php scripts/validate-turnstile-exceptions.php` (invariantes INV-TS-1 a INV-TS-4 em
  `docs/portal/ANTI-REGRESSAO.md`). Uma exceção nova exige atualizar a constante, o ponto em `config/sync`, esta tabela e o gate.

## Caixa de ativar/desativar

- Caminho: **ACULTA Portal > Turnstile (anti-bot)**, em `/painel-administrativo/configuracoes/aculta/portal/turnstile`. Exige a permissão `administer site configuration`.
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
