# Criar a primeira senha em conta criada com acesso externo (OAuth)

Data: 2026-10-12. Módulo: `aculta_portal`. Tela: Minha conta > Segurança > Senha (`/seguranca/senha`).

## Problema

Quem cria a conta pelo Google (Social Auth) não escolhe senha. O Social Auth grava uma senha aleatória de 32 caracteres
(`social_auth/src/User/UserManager.php`, `userPassword(32)`). A aba Senha antes mostrava só um aviso ("Sua conta utiliza
acesso externo") e nada além disso. A pessoa não tinha como entrar com e-mail e senha, e não havia como desconectar o
Google sem antes ter uma senha (o próprio link de desconectar exige isso).

## Detecção

A conta é tratada como "sem senha escolhida" quando:

1. A marca `social_auth_password_unset` está gravada em `user.data` (módulo `aculta_portal`), **ou**
2. A conta não tem senha (campo vazio).

A marca é gravada por `SocialAuthUserCreatedSubscriber` no evento `social_auth.user.created`, que o Social Auth dispara ao
criar a conta. A função de decisão é `PortalController::hasUserChosenPassword()`:

```text
hasUserChosenPassword = senha não vazia AND marca social_auth_password_unset ausente
```

## Fluxo

- **Conta com senha escolhida** (cadastro comum, ou conta OAuth que já criou senha): a aba Senha mostra o formulário de
  troca (`buildPortalAccountForm`), que pede a senha atual.
- **Conta sem senha escolhida** (criada com Google): a aba Senha mostra o formulário **Criar senha**
  (`AccountPasswordCreateForm`). Ele pede só a nova senha, com confirmação, e **não pede a senha atual**.
- Ao gravar a senha nova, o formulário remove a marca `social_auth_password_unset`. Na próxima vez, a aba Senha mostra o
  formulário de troca. O botão "Desconectar Google" também fica disponível, porque a conta agora tem senha escolhida.

## Decisões

- **Sem senha atual:** a pessoa nunca escolheu uma senha, então não há o que confirmar. Pedir uma senha que ela não
  conhece levaria ao "Esqueci minha senha".
- **Mínimo de 8 caracteres**, o mesmo do cadastro (`FormHooks`, `user_register_form`).
- **Só a conta da sessão:** o formulário não recebe o usuário como parâmetro. Grava sempre na conta da sessão, carregada
  de novo pelo `entity_type.manager`.
- **Envio comum, sem AJAX** (`FormHooks::accountAjaxSubmit` exclui o formulário). Depois de criar a senha, a aba é
  recarregada (`aculta_portal.security_password`) para mostrar o estado novo. Um AJAX aqui devolveria o próprio
  formulário de criação, porque a aba só muda no recarregamento.
- **Mensagem em português** e sem log da senha.

## Código

| Arquivo | Papel |
| --- | --- |
| `src/Form/AccountPasswordCreateForm.php` | Formulário "Criar senha" (validação, gravação, remoção da marca, redirect). |
| `src/Controller/PortalController.php` | `securitySectionContent()`: conta sem senha escolhida recebe `create_form`. |
| `src/Hook/FormHooks.php` | Exclui o formulário do AJAX da conta. |
| `src/EventSubscriber/SocialAuthUserCreatedSubscriber.php` | Grava a marca na criação pelo Social Auth (já existia). |

## Limitação conhecida

Contas criadas pelo Social Auth **antes** desta mudança não têm a marca, porque o subscriber só grava na criação. Para elas,
o sistema acha que já existe uma senha escolhida e mostra o formulário de troca, que pede a senha atual. A pessoa não a
conhece, e o caminho é "Esqueci minha senha" (`user.pass`). Não há como distinguir essas contas sem uma verificação
extra. Pendência: identificar contas com registro em `social_auth` e sem marca, e decidir se a marca é gravada para elas
(por exemplo, uma atualização de dados com regra explícita). Isso depende de consulta no runtime e de decisão do
responsável.

## Verificação feita (Homelab, runtime de teste)

- Lint dos arquivos alterados: sem erros.
- Teste direto da lógica com uma conta temporária marcada como criada por OAuth:
  - senha curta (`curta`) recusada com 1 erro de validação;
  - senha `senhaboa123` gravada, com hash diferente do anterior e conferida com o serviço `password`;
  - marca `social_auth_password_unset` removida;
  - redirect para `aculta_portal.security_password` definido;
  - conta temporária removida ao final (conferido: nenhuma `oauthteste` restante).
- Gates: `validate-aculta-portal-drupal11` PASS. `validate-module-theme-boundary` com 1 falha, que é de outra linha de
  trabalho (`SocialLinksPagePresenter.php`, classes de design no módulo), não desta mudança.

## Não verificado

- Navegador: aba Senha numa conta criada com Google (desktop e mobile), e o recarregamento depois de criar a senha.
- Fluxo de login com e-mail e senha depois de criar a senha, numa conta OAuth real.

## Pendências

- CHANGELOG do `aculta_portal`: entrada para esta mudança (adiada porque o arquivo tem entradas de outra linha de trabalho
  sem commit).
- Contas OAuth anteriores à marca (ver limitação).

## Relacionado

- Regra de fronteira de autenticação: `AGENTS.md`, seção "Fronteira de autenticação e anti-bot".
