# Política transversal ACULTA - operações no frontend, backend exclusivo para administradores

**Decisão vigente em 2026-11-20.** Aplica-se a todas as suítes e módulos do ACULTA, não apenas ao Fórum. Esta é uma especificação prospectiva: a Config Sync atual e as rotas existentes **ainda não cumprem** todas as regras abaixo. Proibido marcar como implementada sem teste HTTP/ACL e autorização de migração.

## Decisões vinculantes

1. **Somente contas com o papel institucional `administrator` acessam o backend**, inclusive todas as rotas `/painel-administrativo/**`, `/admin/**`, rotas administrativas fora desses prefixos, Views de administração, revisões, configuração, gestão de contas, CGU administrativo, JSON, AJAX e POST. Um usuário que acumule outro papel com `administrator` continua administrador. Somente possuir `access administration pages` ou `administer comments` **não** autoriza entrar no backend.
2. Todas as operações de usuários autorizados **não administradores** recebem interfaces **frontend específicas de sua suíte**, com popups/diálogos e páginas de trabalho onde necessário; sem redirect oculto para backend. A decisão abrange Fórum, Wiki, Revista, LMS, Loja, Eventos, Links, Galeria, Documentos, Doações, Conta e demais consumidores, respeitando o escopo de cada serviço.
3. Criar o papel `moderador` abaixo de `content_editor` em competências. Moderador exerce **moderação comunitária** pelo frontend, não possui powers editoriais de Wiki/Revista, nem administração. `content_editor` segue responsável por edição/revisão editorial, também no frontend; acumular `moderador` requer concessão explícita. **Peso de papel Drupal não estabelece herança**.
4. O `lms_teacher`/Educador exerce moderação **pedagógica própria do LMS**, por Group, matrícula, turma, curso e papel contextual; `moderador` e `content_editor` não herdam esses poderes. O backend do LMS permanece exclusivamente para administradores. O frontend operacional pedagógico pertence ao GanjaLab.
5. `aculta_cgu` mantém ownership de denúncias e decisões comunitárias; suas filas frontend respeitam purpose/ACL e nunca misturam casos pedagógicos privados do LMS. A revisão editorial de Wiki/Revista mantém decisões próprias. Serviços de motor de comentário podem ser compartilhados, autoridade não.

## Matriz de superfícies operacionais a implementar

| Contexto | Operações de não administradores | Local frontend | Autorização |
| --- | --- | --- | --- |
| Growroom420 / Fórum | Moderação de tópicos/ForumPosts/Comments atuais, fila comunitária, enquetes, ações por popup | No Fórum público, com workspace de moderação protegido | `moderador` + capacidade fina de propósito Fórum; conteúdo CURRENT |
| Wiki / WE Sativa | Criar/revisar verbetes, propostas, editar conteúdo e moderar comentários contextuais | Wiki/WE Sativa, formulários e popups da própria suite | Contrato editorial Wiki, sem elevação pela permissão global Fórum |
| Revista / Marley | Autoria, edição, revisão, publicação, galerias e moderação de comentários contextuais | Marley, popups e telas editoriais frontend | EditorialCapability da Revista; acesso ao host |
| LMS / GanjaLab | Cursos/turmas, material didático, tutoria, discussões e feedback pedagógico | GanjaLab frontend Educador; nunca fila comunitária do Fórum | Group/matrícula/capacidade do Educador, revalidada em cada POST |
| Loja / Green Stash | Catálogo operacional, itens, pedidos autorizados e gestão não financeira | Green Stash frontend específico | Capacidades Commerce e escopo de loja; pagamentos/gateways institucionais só admin |
| Eventos / Culturas | Propostas, locais, organizações, programação e revisão contextual | Culturas frontend | Provider/evento/organização/owner e CGU se aplicável |
| Links, Galerias, Documentos, Doações, Conta, outras suítes | Operações autorizadas do contexto, sem recorrer a /admin | Página da suíte + SDC/Form API | ACL de entidade e contratos do módulo; não inferir permissão global |

**Observação:** esta tabela é um inventário de destino, não declara que cada interface já existe. Antes de implementar cada suíte, inspecionar rotas/menus/controllers/permissions atuais e estado do Runtime. Não inventar novas capacidades de negócio ou dar acesso financeiro/contábil sem decisão.

## Garantia técnica admin-only

- Inventariar `user.role.*` (em especial `content_editor`, `lms_teacher`, `gerente_loja`, papéis de eventos e outros), atribuições reais, `access administration pages`, `access content overview`, `administer *`, permissões de revisão/transição, Views, VBO, JSON/HTMX e rotas contrib/Core.
- Implementar proteção **no servidor** para rotas backend e _admin_route, com verificação de `administrator`/política institucional no access manager/route subscriber; conferir bypass de usuário 1 e responsabilidades administrativas previstas pelo Drupal. Não usar apenas `path.startsWith` nem apenas hide menus. Verificar qualquer rota administrativa fora dos prefixos e exceções deliberadas do Core. Testar com role combinada e usuário sem administrator.
- Migrar fluxo editor/educador/gerente/moderador para frontend **antes** de retirar sua permissão antiga. Cada corte requer prova de acesso completo na nova superfície, rollback e garantia de que nenhuma atribuição foi promovida. `content_editor` perde privilégios de backend sem perder capacidades editoriais próprias.
- Form API/HTMX no frontend, POST+CSRF, access em GET e POST, permissão no target/host e contextos cache. Rejeitar busca direta, mutation via AJAX, JSON e URL antiga. Moderação pedagógica LMS é contrato separado do CGU comunitário.
- Ações institucionais (atribuir papéis, alterar configuração/Composer, permissão global, administração de contas, financeiro e auditoria sensível) continuam **admin-only**.
- Preferir SDC/UI Patterns, scripts de frontend e serviços nativos; backend e frontend podem invocar o mesmo serviço de negócio autorizado, mas **não compartilham automaticamente permissões**.

## Gate de aceite transversal

Para cada suíte, comprovar: usuário operacional entra na sua área frontend e conclui a tarefa sem backend; não administrador recebe 403/404 em todas as rotas backend com GET/HEAD/POST, JSON/HTMX e cache aquecido; administrador conserva acesso institucional; revogação de papel, Group ou publicação impede ação; dados de uma suíte não aparecem em outra. Cobrir `content_editor`, `moderador`, `lms_teacher`, demais papéis, agregações e combinação de papéis.

Plano técnico inicial do Fórum e enquete: [Plano de paridade frontend](../../web/modules/custom/aculta_portal/modules/aculta_forum/docs/PLANO-PARIDADE-DISCOURSE-FRONTEND-MODERADOR-ENQUETES-2026-11-20.md).
