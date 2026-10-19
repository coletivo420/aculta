# Política de confirmação de e-mail

Status: **parcialmente implementada** (ver seções 9 e 10). As decisões pendentes da seção 6 continuam abertas.

## 1. Regra

Usuário com e-mail não confirmado:

- **Pode:** acessar o site, fazer login, navegar e usar a área "Minha conta".
- **Não pode:** editar a wiki (criar ou alterar verbetes), comentar, comprar produtos não educacionais ou apoiar.
- **Cursos:** confirmação de e-mail não é requisito de matrícula nem de participação. O acesso às aulas continua
  condicionado à permissão `take` atual do Group e ao aceite dos termos vigente; o LMS/Group permanece a fonte de
  verdade para matrícula e acesso.

## 2. Aviso em "Minha conta"

Enquanto o e-mail não estiver confirmado, a área "Minha conta" exibe um aviso em destaque vermelho com:

- texto: o e-mail precisa ser confirmado, com um link **"Clique aqui para reenviar"**;
- **não pode ser descartado** (sem botão de fechar, nem lembrar-depois);
- desaparece somente quando o e-mail for confirmado.

## 3. Definição de "e-mail confirmado"

Um e-mail é confirmado quando o usuário abre o link de confirmação enviado ao endereço. O estado deve ser gravado
pelo Portal (flag por usuário, por exemplo em `user.data` do módulo `aculta_portal`), e não inferido de outro campo.

## 4. Mecanismo técnico (a decidir)

- Hoje o Core bloqueia a conta até a verificação quando `user.settings:verify_mail` está ligado. Isso impede o login
  de quem não confirmou, o que contradiz a regra 1. Para cumprir a regra, a verificação passa a ser feita pelo Portal:
  `verify_mail` desligado, conta ativa, flag de "não confirmado" e confirmação pelo `email_confirmer` (realm próprio de
  cadastro).
- O reenvio usa a rota de reenvio do `email_confirmer`, com o intervalo de `resendrequest_delay` (900 s) para evitar abuso.
- O link de confirmação é gerado sempre no host da conta (como na troca de e-mail).

## 5. Pontos de aplicação

- Cursos: matrícula e acesso às aulas são autorizados pelo Group/LMS; não exigir a marca global de e-mail confirmado.
  A política geral de termos continua aplicada às rotas de participação.
- Wiki: criação e edição de verbetes e categorias (permissões de conteúdo e `entity_access` do Portal).
- Aviso: formulário/seção de "Minha conta" do shell do Portal, apenas para o usuário dono da conta.

Esta política não restringe leitura pública da wiki nem páginas institucionais.

**Troca de e-mail não é bloqueio.** Quem não confirmou o e-mail pode solicitar a troca de endereço em "Segurança"
(e reenviar a confirmação). A regra de escrita bloqueia apenas wiki, comentários, cursos, loja e apoio.

## 6. Decisões do responsável (pendentes)

1. **Contas existentes:** consideradas confirmadas (recomendado) ou precisam confirmar agora?
2. **Confirmação de cadastro:** confirmar também via link do `email_confirmer` (recomendado), mantendo a verificação
   do Core desligada.
3. **Escopo:** além de cursos e edição da wiki, há outras ações a bloquear (por exemplo, apoio, comentários)?
4. **Expiração do link de cadastro:** usar o mesmo `hash_expiration` (24 h) da troca de e-mail, ou outro prazo?

## 6.1 Validade do link (decisão do responsável)

- O link de confirmação **não expira** (`EmailConfirmationEntity::isExpired()` devolve falso; classe trocada no Portal).
- A segurança depende do hash de 43 caracteres de uso único, do cancelamento dos pedidos anteriores depois que um
  novo envio é aceito e do reenvio por formulário POST com proteção CSRF (`aculta_portal.email_resend`). A configuração
  `hash_expiration` deixa de valer.
- A implementação de `isExpired()` é global: também afeta o realm `email_confirmer_user`, usado na troca de endereço.
  Portanto, links de troca de e-mail também não expiram; se um link ou a caixa postal for comprometido, ele poderá
  confirmar o novo endereço enquanto o token permanecer ativo. Esta fase preserva a política existente e não altera
  sua validade.
- Antes desta decisão, a premissa era de 24 horas (ver CHANGELOG do Portal 0.2.0-dev.16).

## 6.2 Decisões já tomadas

- Contas existentes precisam confirmar (sem marca `email_confirmed` = não confirmado).
- Cadastro é confirmado pelo `email_confirmer` (realm `aculta_registration`).
- Escopo: cursos, wiki, comentários, loja e apoio.
- Login por OAuth confirma o e-mail (provedor já verificou o endereço).

## 7. Segurança

- Nenhum endereço de e-mail nem valor de link é gravado em log além do necessário para diagnóstico (nome e usuário).
- O reenvio é limitado pelo intervalo de `resendrequest_delay`.
- A confirmação segue o hash de 43 caracteres de uso único e sem expiração; o login automático na confirmação está
  registrado em `docs/portal/` como risco aceito (ver CHANGELOG do Portal 0.2.0-dev.14).

## 8. Cadastro com e-mail já cadastrado (0.2.0-dev.22, DT-P06)

Decisão do responsável (2026-10-09): a resposta do cadastro não revela se o e-mail já tem conta.

- **Mensagem única** para e-mail novo e já cadastrado: "Sua solicitação foi recebida. Se este endereço tiver uma confirmação ou aviso disponível, verifique a caixa de entrada e a pasta de spam. Se nenhuma mensagem chegar, tente novamente mais tarde." Mesmo destino (página inicial).
- **Com e-mail já cadastrado:** nenhuma conta é criada, ninguém é logado, e o dono da conta recebe um e-mail (`registration_existing_mail`) com o link para entrar, o link para recuperar a senha e a orientação de que não é preciso fazer nada se não foi ele.
- **Com e-mail novo:** o cadastro segue como antes (conta bloqueada até a confirmação), só com a mensagem única.
- **Implementação:** o erro "already taken" do Core sai do formulário (`validateRegistrationMail`); o botão usa `saveRegistration` no lugar de `::save`; a busca e o aviso ficam em `RegistrationMailPolicy`; o texto do aviso está no hook `mail` do módulo. Callbacks que gravam dados ignoram o caso de e-mail existente.
- **Limites:** (1) o tempo de resposta difere quando o aviso é enviado na mesma requisição; (2) o aviso pode ser usado para incomodar um endereço alheio, por isso o CAPTCHA do cadastro continua obrigatório (ver `TURNSTILE.md`); (3) o aviso não é enfileirado; (4) o aviso depende do SMTP, ainda não validado em produção (DT-P05).

## 9. Troca de e-mail (decisão 2, 2026-10-12)

**Decisão do responsável:** a troca de e-mail dispara uma nova confirmação do novo endereço, e o papel
`email_confirmed` permanece na conta. A troca só existe no formulário de Minha Conta (`change_mail_page`); a edição
de conta do Core não oferece o campo de e-mail (`EmailChangeHooks`).

**Como funciona hoje (código e configuração verificados):**

- O `email_confirmer_user` (`user_presave`, `email_confirmer_user.module`) intercepta toda troca de e-mail de conta
  quando `email_confirmer_user.settings:user_email_change.enabled` está ligado (no Runtime: ligado).
- Para troca feita por quem **não** tem o bypass: o módulo guarda o novo endereço em `user.data`
  (`email_change_new_address`), restaura o endereço antigo, cria a confirmação do novo (realm `email_confirmer_user`)
  e envia o e-mail. O novo endereço só passa a valer depois do clique no link.
- O papel `email_confirmed` não é alterado por essa troca (o papel é irreversível, ver `EmailConfirmedRoleGuard`).
- Troca feita por administrador: o papel `administrator` tem `is_admin: true`, então passa no bypass
  `email confirmer user bypass email change`. A troca é aceita na hora, sem confirmação.

**Decisões complementares (2026-10-12):**

1. **Decidido: manter o comportamento do módulo.** O endereço antigo continua valendo até a confirmação do novo. É o
   mais protetivo para o combate a contas falsas.
2. **Decidido: aceito.** O bypass do administrador (`is_admin`) permanece: troca feita por administrador é aceita
   sem confirmação. Essa lacuna só alcança trocas feitas por código, porque o formulário de administração não tem mais
   o campo de e-mail.

## 9. Aplicação atual no site (2026-10-16)

Verificada no código e na configuração do Runtime de teste. Itens marcados como "a confirmar" não têm verificação
própria e não devem ser tratados como garantia.

| Área | Regra | Situação | Onde |
|---|---|---|---|
| Cursos (matrícula e aulas) | Sem requisito de e-mail; exige Group `take` e termos vigentes | Aplicado | `ProtectedRouteAccessCheck::course()` |
| Checkout de curso | Sem requisito de e-mail; exige propriedade/acesso do pedido e termos vigentes | Aplicado | `ProtectedRouteAccessCheck::checkout()` para pedido `curso` |
| Checkout (loja) | Exige e-mail confirmado | Aplicado | `ProtectedRouteAccessCheck` (rota `/checkout`) |
| Apoio (contribuições) | Exige e-mail confirmado | A confirmar | Sem verificação própria nesta revisão |
| Wiki: editar e excluir verbete | Exige e-mail confirmado | Aplicado | `EntityHooks::entityAccess`, `update` e `delete` |
| Wiki: criar verbete | Exige e-mail confirmado | Aplicado pelo papel | Só `email_confirmed` concede escrita na wiki (`wiki_contributor` foi mesclado em `email_confirmed`; sem hook) |
| Comentários: criar | Exige e-mail confirmado | Aplicado pelo papel | `post comments` só em `email_confirmed` (ver seção 10) |
| Comentários: editar e excluir | Exige e-mail confirmado | Aplicado | `EntityHooks::entityAccess`, `update` e `delete` |
| Leitura pública (wiki e páginas institucionais) | Livre | Aplicado | Não restringido |
| Login, navegação e "Minha conta" | Livre | Aplicado | Aviso de confirmação na conta |
| Troca de e-mail | Livre | Aplicado | Não é bloqueio (seção 5) |

Notas:

- O papel `email_confirmed` é irreversível pela administração (`EmailConfirmedRoleGuard`). A marca de confirmação
  fica em `user.data` do módulo `aculta_portal`.
- O papel `email_confirmed` também pula o CAPTCHA. Isso é independente do bloqueio de escrita.
- Contas existentes sem confirmação (decisão 6.2) não podem comentar nem participar do fórum até confirmar.
  No teste, a conta de editor `coletivo420` está nesse caso.

## 10. Aplicação no Fórum (2026-10-16)

Decisão registrada como F-D13 em `web/modules/custom/aculta_portal/modules/aculta_forum/docs/DECISOES.md`.

Na reconstrução `aculta_forum`, a política atual de confirmação de e-mail para
criação/edição/resposta/denúncia deve ser mantida nos providers correspondentes
de tópicos e Comment. As referências abaixo a `create forum content` e
`comment_forum` descrevem o código atual; não são nomes definitivos de permissão
do novo motor. Revisar a tabela de permissões antes da fase dev.4.

| Ação no Fórum | Papel mínimo | Situação |
|---|---|---|
| Ler tópicos e comentários | Público (anônimo) | Aplicado |
| Seguir e salvar tópico | Conta cadastrada (`authenticated`) | Aplicado |
| Criar tópico | `email_confirmed` | Aplicado (`create forum content`) |
| Editar e excluir o próprio tópico | `email_confirmed` | Aplicado (permissões do papel) |
| Comentar | `email_confirmed` | Aplicado (`post comments`) |
| Editar e excluir o próprio comentário | `email_confirmed` | Aplicado (hook de wiki e comentários, seção 9) |
| Denunciar tópico e comentário | `email_confirmed` | Aplicado (`flag sinalizar_*`) |
| Moderar denúncias | `content_editor` e administrador | Aplicado (`moderar denuncias do forum`) |

Motivo: o texto de decisão do responsável é "participação no fórum exige e-mail confirmado". Seguir e salvar ficam
com `authenticated` por serem leitura, sem conteúdo gravado de outra pessoa.

Lacunas conhecidas:

- O papel `authenticated` ainda concede a permissão `view commerce_shipping_method`, que não está no arquivo do
  repositório (divergência de configuração anterior à F5.1, não tratada aqui).
- Conteúdo histórico importado não passa pela confirmação. As autorias legadas não são contas que participam.
