# Papéis e participação

Status: **decisões aprovadas pelo responsável (2026-10-16)**. Dúvidas abertas na seção 6.
Escopo: visualização, participação e moderação no Fórum e na área de contas. Não substitui as políticas de cursos,
loja e wiki, que continuam em `EMAIL-CONFIRMATION-POLICY.md`.

## 1. Papéis e hierarquia

Do menor para o maior:

| Ordem | Papel (machine name) | Rótulo | Como é concedido |
|---|---|---|---|
| 1 | `authenticated` | Usuário autenticado | Toda conta com login |
| 2 | `email_confirmed` | E-mail confirmado | Automático, ao confirmar o e-mail (irreversível) |
| 3 | `associado` | Associado | **Manual**: pessoas associadas à Aculta |
| 4 | `content_editor` | Editor de conteúdo (moderador do Fórum) | Administrativa |
| 5 | `administrator` | Administrador | Administrativa |

**Usuários podem ter mais de um papel.** `associado` é um papel extra: pode acompanhar qualquer outro papel. No futuro,
outros papéis com o mesmo comportamento poderão ser integrados. Por enquanto, a regra de visibilidade considera
`associado` mais qualquer outro papel.

Cada nível enxerga o que o nível anterior enxerga. Na implementação, os realms de visibilidade são concedidos assim:

- `aculta_email_confirmado`: `email_confirmed` ou `associado`.
- `aculta_associado`: `associado`, ou `content_editor` com `email_confirmed`.

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
| Ver contêiner ou tópico de nível "e-mail confirmado" | `email_confirmed` ou `associado` | Aplicado |
| Ver contêiner ou tópico de nível "associado" | `associado`, ou `content_editor` com `email_confirmed` | Aplicado |
| Denunciar e ver a fila de denúncias | `content_editor` com `email_confirmed` | Aplicado |
| Ocultar, despublicar ou excluir tópico e comentário (exceto histórico) | `content_editor` com `email_confirmed` | Aplicado |

## 4. Visitante e bots

- Quem não tem o nível exigido recebe **404**, inclusive bots. O conteúdo restrito não aparece em listagens,
  contagens, sitemap nem busca (decisão F-D15).
- Visitante **não vê o nome** do fórum restrito: o contêiner não aparece no índice do fórum nem tem página.

## 5. Confirmação de e-mail pelo administrador: lacuna

**Hoje não existe** função no painel administrativo para o administrador confirmar o e-mail de uma conta. A confirmação
acontece só por: link enviado ao e-mail, login com provedor externo (OAuth) e troca de e-mail confirmado. O
administrador precisa, então, de uma ação própria: marcar a confirmação (flag do Portal em `user.data`) **e** conceder
o papel `email_confirmed`, registrando quem fez e quando. Essa ação não está implementada. Até lá, a confirmação manual
não é possível pela interface.

## 6. Dúvidas abertas

1. `associado` precisa sempre vir junto de `email_confirmed`? Hoje não é exigido: uma conta só com `associado` ganha o
   nível de e-mail confirmado pela regra de visibilidade. Confirma que deve ser exigido?
2. A ação de confirmação manual pelo administrador (seção 5) deve ser implementada agora, ou fica para outra fase?
