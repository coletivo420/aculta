# Papéis e participação

Status: **decisões aprovadas pelo responsável (2026-10-16)**. Dúvidas abertas na seção 6.
Escopo: visualização, participação e moderação no Fórum e na área de contas. Não substitui as políticas de cursos,
loja e wiki, que continuam em `EMAIL-CONFIRMATION-POLICY.md`.

## 1. Papéis e hierarquia

Do menor para o maior:

| Ordem | Papel (machine name) | Rótulo | Como é concedido |
|---|---|---|---|
| 1 | `authenticated` | Usuário autenticado | Toda conta com login |
| 2 | `email_confirmed` | E-mail confirmado | Automático, ao confirmar o e-mail (irreversível), ou pela confirmação manual do administrador |
| 3 | `content_editor` | Editor de conteúdo (moderador do Fórum) | Administrativa, com e-mail confirmado |
| 4 | `administrator` | Administrador | Administrativa |

**Associado não é papel.** É um marcador puro (F-D19): fica em tabela própria, separada da identidade User e dos papéis.
Não concede acesso a nenhum conteúdo nem função do site. Serve a relatórios e páginas da associação. O administrador
marca e desmarca em `/painel-administrativo/associados/{uid}/marcar`, e o relatório de associados lista as marcas em
`/admin/aculta/associados`.

Usuários podem ter mais de um papel. A regra de visibilidade considera só `email_confirmed` e os papéis acima.

Na implementação, o realm de visibilidade é concedido assim: `aculta_email_confirmado` para `email_confirmed`, e para
`content_editor` somente com `email_confirmed`.

## 2. Regras do content_editor

- O `content_editor` **precisa ter e-mail confirmado** (`email_confirmed`). Sem ele, não recebe acesso aos níveis
  restritos nem poderes de moderação. Decisão de 2026-10-16.
- Como moderador, pode **ocultar, despublicar ou excluir** tópicos e comentários do Fórum.
- **Exceção**: conteúdo de autoria histórica (conta com o papel `usuarios_legados_forum`, importada do Growroom)
  não pode ser moderado pela fila atual. A regra é aplicada no ponto único de acesso (`ForumModerationHooks`).
- O administrador tem **bypass total**: pode tudo, inclusive moderar conteúdo histórico e ver restritos.

## 3. Participação no Fórum

| Ação | Papel mínimo | Situação |
|---|---|---|
| Ler tópicos públicos | Anônimo | Aplicado |
| Seguir e salvar tópico | `authenticated` | Aplicado |
| Criar tópico, editar e excluir o próprio, comentar, denunciar | `email_confirmed` | Aplicado |
| Ver contêiner ou tópico de nível "e-mail confirmado" | `email_confirmed` (ou `content_editor` com `email_confirmed`) | Aplicado |
| Denunciar e ver a fila de denúncias | `content_editor` com `email_confirmed` | Aplicado |
| Ocultar, despublicar ou excluir tópico e comentário (exceto histórico) | `content_editor` com `email_confirmed` | Aplicado |

## 4. Visitante e bots

- Quem não tem o nível exigido recebe **404**, inclusive bots. O conteúdo restrito não aparece em listagens,
  contagens, sitemap nem busca (decisão F-D15).
- Visitante **não vê o nome** do fórum restrito: o contêiner não aparece no índice do fórum nem tem página.

## 5. Confirmação manual de e-mail pelo administrador

Implementada em 2026-10-16. Quem tem a permissão **confirmar email de conta manualmente** (administrador) acessa
`/painel-administrativo/contas/nao-confirmadas`, lista as contas ativas sem e-mail confirmado e confirma cada uma
em `/painel-administrativo/contas/{uid}/confirmar-email`, com confirmação explícita (formulário com token).

A ação usa a mesma política do fluxo normal: grava a confirmação do Portal e concede o papel `email_confirmed`.
Não envia nenhum e-mail. Repetir a ação numa conta já confirmada não muda nada. Cada confirmação é registrada no
log do canal `aculta_portal` com o administrador e a conta, **sem o endereço de e-mail**.

## 6. Autores históricos do Fórum (acervo do Growroom)

Decisão de 2026-10-16: autores legados **não são usuários do Drupal**. Não têm conta, login, perfil nem aparecem na
lista de usuários do administrador. A autoria fica na entidade própria `aculta_legado_autor`, com tabela própria,
acessível só pela permissão **administer aculta legado**. Tópicos e comentários históricos têm uid 0 e apontam para
a autoria pelo campo `field_autor_legado`. Conteúdo com esse campo preenchido não é moderado pela fila atual.

## 7. Dúvidas abertas

Nenhuma no momento.
