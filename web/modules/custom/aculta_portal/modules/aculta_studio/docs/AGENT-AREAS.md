# Studio — áreas, rotas e abas (regra de agente)

Leia só quando a tarefa tocar abas, rotas ou áreas do Studio. Regras gerais: `../AGENTS.md`.

## Disponibilidade e acesso das abas

- Aba da Wiki (`WikiTab`): verifica o módulo dono `aculta_wiki` e o bundle `wiki_entry`. Não infira disponibilidade do dono só pela configuração retida. Módulo desabilitado com bundle presente, ambos disponíveis e bundle ausente são casos de teste.
- `create wiki_entry content` sozinha não concede acesso na GanjaLab. A autoria de colaboradores fica na WE SATIVA. A GanjaLab exige `edit any wiki_entry content`, `view wiki_entry revisions` ou `moderate wiki proposals`; o inventário compartilhado exige `edit any`.
- Não adicione aba sem permissão real e sem teste de acesso.

## Áreas e rotas

- Rotas canônicas, sem aliases: WE SATIVA (`/we-sativa/**`, purpose `wiki`), GREEN STASH (`/green-stash/**`, purpose `shop`). Marley usa `/marley/{aba}[/{secao}[/{sub}]]` em `account`. Ganjalab usa `/ganjalab` em `courses`. Culturas usa `/culturas` em `eventos`.
- Conta e Meu Perfil ficam em `/conta` e `/meu-perfil`, separados. A rota pública do perfil aguarda decisão própria.
- URLs aposentadas respondem 404. Não crie redirect de compatibilidade.
- Níveis de navegação: áreas em abas superiores (uma por dono); submenu à esquerda com as seções da área; abas internas da seção, com as ações no painel. Cada área implementa `sections()` com esses níveis.
- Cada aba principal, opção de submenu e aba interna tem URL própria. AJAX melhora a troca, mas não é a única forma de acesso. Segmento fora do alcance da conta responde 404.
- WE SATIVA: `aculta_wiki.contributor_workspace` é dono de dados, grants, paginação e links Wiki. O Studio fornece o provider. Meu Trabalho separa cursores para verbetes e propostas, usa SDCs e revalida Entity Access. Criar, editar e contribuir continuam em rotas e Form API do módulo Wiki, com link de retorno cacheável quando Studio e acesso estão disponíveis.
- Início da WE SATIVA (`/we-sativa/inicio`): o controller delega ao dono da Wiki. Sem o contrato opcional de início ou com acesso negado pelo dono, mantenha o fallback.

## Marley, Ganjalab e Culturas

- Marley (`account:/marley`): Revista, moderação do Fórum e Galerias. Não exponha criação de tópicos nem navegação geral de discussões. A aba de moderação exige `moderar denuncias do forum` e delega à rota owner.
- Revista no Marley: a experiência editorial está em `/marley/revista/*`. Não encaminhe usuários editoriais para `/admin/content/revista` nem para `/painel-administrativo/revista`. `aculta_magazine` é dono do Node `revista`, dos fields, da Form API e do workflow. Revalide Entity/Revision Access e a capability ACULTA no acesso direto e no submit. Transições vêm de `StateTransitionValidation`.
- Discussão da Revista: é o Comment `aculta_discussion_post` em `field_aculta_discussion`. Use `aculta_forum.discussion_context_resolver` e `PublicationContextProvider`. Não crie ForumPost, tópico nem fila paralela.
- Ganjalab Estudante (`courses:/ganjalab`): dashboard, jornada e sala. Dados e progresso vêm do LMS.
- Ganjalab Educador (`courses:/ganjalab/educador`): autoria e gestão. Novo curso em `/ganjalab/educador/cursos/novo`. O retorno do formulário vai sempre ao dashboard do Educador.
- O dashboard do estudante só oferece o atalho ao Educador se `Url::access()` autorizar. O dashboard do Educador só exibe retorno ao estudante se a rota estudante autorizar. Não crie toggle fixo nem oculte o curso atual ao alternar.
- O Course Builder não pertence ao Marley. Não misture professor e estudante na navegação. Não conceda acesso educacional por causa de permissão editorial.
- Culturas (ACULTA Studio Culturas): a decisão de produto e domínio está em `web/modules/custom/aculta_portal/modules/aculta_events/AGENTS.md`. Aqui vale: a aba de Eventos não grava no purpose `eventos` a partir de `account`; a entrada da Conta só linka. Datas e Locais pertencem ao Evento, persistidos por `aculta_events`. O painel administrativo fica no MAIN, em `/painel-administrativo/eventos`. Não duplique esse painel na interface de organizadores.

