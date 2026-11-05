# Studio — regras locais vigentes

Caminhos das Suites: `docs/decisions/ADR-038-aculta-studio-suites-por-dominio.md` (retificado em 2026-10-30). Dashboard da Conta e Perfil: `docs/decisions/ADR-048-layouts-de-pagina-tema-v2.md` e `docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md`. Não repita essas tabelas aqui. `/studio` e `/studio/*` respondem 404, sem alias ou redirect.

Histórico desta área (contratos antigos, status de dev.3 a dev.6, rotas de migração): `docs/history/modules/aculta_studio-AGENTS-ARQUIVO-2026-11-05.md`.

## Papel do Studio

- `aculta_studio` é o framework compartilhado de registry e composição. Não tem hub público em `/studio` e não é dono de conteúdo, permissões, discussões, galerias nem tabelas próprias equivalentes.
- Cada aba é um plugin `#[StudioTab]` com três contratos: `isAvailable()` (o dono está habilitado), `access()` (permissão real do dono, nunca nome de papel) e `actions()` (ação com o acesso da rota do dono).
- Ação para outro domínio usa `DomainPurposeManager::routeUrl()` e avalia o acesso com `runInPurpose()`.
- Nenhuma rota ou ação do Studio contorna Form API, Entity Access, Content Moderation nem as permissões do dono.
- Não copie tabela ou fluxo de permissão do dono. Não amplie o acesso de uma aba para contornar o dono. Não mova nem apague dados por ação do Studio sem a operação do dono.
- Não exponha dados de terceiros.

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
- Culturas (ACULTA Studio Culturas): a decisão de produto e domínio está no AGENTS do Portal, seção "ACULTA Studio Culturas". Aqui vale: a aba de Eventos não grava no purpose `eventos` a partir de `account`; a entrada da Conta só linka. Datas e Locais pertencem ao Evento, persistidos por `aculta_events`. O painel administrativo fica no MAIN, em `/painel-administrativo/eventos`. Não duplique esse painel na interface de organizadores.

## Conta e Perfil

- Não reintroduza a experiência `pessoal`. Use somente `/conta` e `/meu-perfil`, separadas.
- O legado da Conta fica em `/conta-legada` de forma permanente. Não restabeleça o legado na raiz nem crie alias temporário. Não retire `AccountShellBuilder`. Não misture Form API cadastral com editor público nem duplique User/Profile.
- Portal é dono de cadastro, endereço, documentos e segurança. User é dono da identidade pública.
- CPF/CNPJ não é duplicado nem aparece em Meu Perfil. O link de documentos só aparece se `aculta_id_cpf.account_document` estiver disponível.
- Perfil público: o editor de Meu Perfil é privado e separado da página pública.

## Frontend e acessibilidade

- O Studio tem página, shell, JavaScript (`js/studio-navigation.js`) e hooks próprios. Não usa o shell, o menu ou o `account-navigation.js` da Minha Conta, e não aparece no menu da conta.
- Apresentação: tokens `data-portal-ui` (`studio-*`) e atributos `data-aculta-studio*` de comportamento. Classes e aparência ficam no ACULTA420.
- Troca de nível move o foco para o título, usa região `aria-live` para carregamento e erros, mantém voltar e avançar do navegador e funciona com links sem JavaScript.
- Ações de edição, aprovação, exclusão e publicação mantêm verificação de acesso, CSRF e confirmação do Drupal.

## Melhoria contínua

- Toda frente que criar função com potencial de aba ou ação investiga o Studio antes de concluir, propor ou implementar a aba dentro desse contrato. Atualize a matriz em `docs/portal/STUDIO-MAGAZINE-ROADMAP.md`, o README do `aculta_studio` e o status do componente.
- Antes de encerrar uma mudança no Studio, rode os gates do diff listados em `docs/operations/GATES.md`. A suíte visual do Studio (`tests/browser/aculta-studio/`) é gate de aceite visual.
- Não declare a migração das Suites funcional integral antes de validar grants e fluxos de cada Suite. Não há troca planejada de `wiki420` para `wiki.aculta.*`.
