# Papéis e participação

Status: **hierarquia aprovada pelo responsável (2026-10-16)**; dúvidas abertas na última seção.
Escopo: visualização e participação no Fórum e na área de contas. Não substitui as políticas de cursos, loja e
wiki, que continuam em `EMAIL-CONFIRMATION-POLICY.md`.

## 1. Hierarquia

Do menor para o maior:

| Ordem | Papel (machine name) | Rótulo | Quem recebe |
|---|---|---|---|
| 1 | `authenticated` | Usuário autenticado | Toda conta com login |
| 2 | `email_confirmed` | E-mail confirmado | Conta com e-mail confirmado (automático, irreversível) |
| 3 | `associado` | Associado | Pessoas associadas à Aculta (**concessão manual**) |
| 4 | `content_editor` | Editor de conteúdo | Moderadores do Fórum e editores (concessão administrativa) |
| 5 | `administrator` | Administrador | Administração do site (bypass de acesso a nós) |

Regra: cada nível enxerga o que o nível anterior enxerga. Na implementação, o papel superior recebe os realms do
nível inferior (ver `RestrictedForumGrants`).

## 2. Participação no Fórum

| Ação | Papel mínimo | Situação |
|---|---|---|
| Ler tópicos públicos | Anônimo | Aplicado |
| Seguir e salvar tópico | `authenticated` | Aplicado |
| Criar tópico, editar e excluir o próprio, comentar, denunciar | `email_confirmed` | Aplicado |
| Ver contêiner ou tópico de nível "e-mail confirmado" | `email_confirmed` (ou superior) | Aplicado |
| Ver contêiner ou tópico de nível "associado" | `associado` (ou superior) | Aplicado |
| Moderar denúncias (fila em `/painel-administrativo/forum/denuncias`) | `content_editor` | Aplicado |
| Ver tópicos restritos por ser moderador | `content_editor` | Aplicado |

## 3. Visitante e bots em conteúdo restrito

Quem não tem o nível exigido recebe **404**, inclusive bots. O conteúdo restrito não aparece em listagens, contagens,
sitemap nem busca. Decisão do responsável: "bots recebem 404 nos restritos" (F-D14, item 5 e F-D15).

## 4. Dúvidas abertas (responder antes de ampliar)

1. A hierarquia é cumulativa mesmo para papéis atribuídos separadamente? Hoje, um `associado` sem `email_confirmed`
   já vê o nível de e-mail confirmado. Confirma?
2. `content_editor` precisa também ter `email_confirmed`? Hoje não.
3. Moderação além da fila: `content_editor` pode ocultar, despublicar ou excluir tópicos e comentários? Hoje só vê a
   fila e os conteúdos restritos.
4. O administrador tem bypass total de acesso a nós, inclusive restritos. Confirma?
5. Visitante vê o nome do fórum restrito em algum lugar (menu, breadcrumb, busca)? Hoje não vê.
