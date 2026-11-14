# ACULTA User - coautoria nativa de Node: corte direto

Data da decisão: 2026-11-14. Origem inspecionada: pacote anexado node_co_authors 1.2.3; branch alvo: dev de coletivo420/aculta.

**Decisão:** absorver no aculta_user o comportamento necessário do contrib, remover o módulo contribuído e seus dados antigos no mesmo corte operacional. Não haverá coexistência funcional, ETL, legado preservado, alias, namespace antigo nem campo de compatibilidade. Os Nodes, o autor principal e outros campos ficam preservados.

**Estado desta entrega:** apenas o contrato de consulta, a implementação de leitura, a fábrica de definição de campo, o registro do serviço e o teste Unit foram incorporados. São componentes **passivos**: não há hook registrando campo, nova permissão, rota administrativa ou mudança no Runtime. Nenhuma execução de composer remove, desinstalação, importação de configuração ou alteração de banco foi realizada.

## 1. Inventário da fonte 1.2.3

| Elemento do pacote | Função observada | Incorporação planejada |
| --- | --- | --- |
| hook_entity_base_field_info | Base Field co_authors, referência multivalorada a User, revisionável e traduzível | Novo Base Field **aculta_coauthors**, de propriedade aculta_user, sem copiar registros |
| hook_node_access | Coautor com permissões de autor edita/exclui e pode ver conteúdo não publicado | Reescrever com proteção de Domain, Content Moderation e cacheabilidade; nunca permitir leitura de não publicado só pelo vínculo |
| hook_entity_field_access | Concessões por três permissões de editar a lista, conforme dono/coautor/todos | Permissões próprias ACULTA, sem grants automáticos; separar editar Node de administrar coautores |
| hook_form_node_form_alter | Coloca campo no grupo author | Integrar aos formulários permitidos e sem JS |
| hook_views_data e AuthorCoAuthorName | Filtro OR do autor principal ou coautor via autocomplete/UID | Novo plugin Views com namespace ACULTA, joins e distinct testados |
| hook_token_info e hook_tokens | Token co_authors_email retorna e-mails dos envolvidos | **Não expor token público de e-mail**; serviço interno autorizado somente se comprovadamente necessário |
| permissions.yml | Editar coautores do conteúdo próprio, coautorado ou de todos | Nomes novos, política explícita e testes negativos |

O arquivo enviado contém um módulo com hooks, três permissões, um plugin Views e licença GPL v2. **Não há painel administrativo próprio no arquivo fornecido**. O código original deve ser usado como referência técnica, com atribuição/licença respeitadas quando houver cópia de trechos.

### 1.1. Dependências atuais identificadas em dev

- composer.json: drupal/node_co_authors ^1.2.
- config/sync/core.extension.yml: node_co_authors habilitado.
- aculta_magazine.info.yml: dependência explícita do contrib.
- Form displays de Revista, Wiki, Páginas e Eventos apresentam co_authors; Fórum esconde o campo; view displays escondem em alguns bundles.
- Revista possui separadamente field_editorial_author (taxonomia) e field_credits (texto). **Não substituir esses campos por coautores de User.** O formulário de Revista também menciona field_coautores em hidden; verificar se é configuração residual.
- aculta_user contém AuthorIdentityResolver, AuthorCardBuilder e PublicProfilePolicy; reutilizar os contratos para exibição.
- O diretório do módulo contrib não está versionado no repositório; o pacote Composer é instalado no Runtime. Inspecionar o banco antes do corte, sem copiar seus dados.

## 2. Contrato técnico

**Campo:** aculta_coauthors como Base Field de Node, entity_reference -> user, cardinalidade ilimitada, revisionável. Permitir ajuste de form/view display por bundle e translatability compatível com a política de revisões. As referências antigas de co_authors serão descartadas. O nome antigo não poderá ser usado como fallback.

**Serviços:** aculta_user.coauthor_manager, com contrato CoAuthorManagerInterface, lê UIDs deduplicados, exclui uid proprietário e confirma pertencimento de conta autenticada. Este serviço **não concede autorização** e não acessa e-mails. A fábrica CoAuthorFieldDefinition::build fica inativa até o registro final do campo por Hook.

**Política de acesso:** coautor com edit own {bundle} content pode editar Node; com delete own {bundle} content pode excluir, se as demais políticas autorizarem. Não inferir delete de edit, nem publicar/aprovar revisão de coautoria. Autor principal não é substituído. Acesso a conteúdo não publicado deve ser regulado por Content Moderation e Domain, sem concessão genérica nova. Vínculo de coautor não equivale a membro de organização, ownership de Local, moderador, editor global ou direito sobre ingressos.

**Edição do campo:** três permissões novas de coautores para próprio/coautorado/todos, e administração restrita; validação do formulário e da API para não permitir autovinculação sem autorização, duplicação de owner, contas bloqueadas ou inexistentes e remoção arbitrária. A validação de acesso ao campo precisa independer do acesso a editar Node. Usar AccessResult com metadata de cache completa e testes negativos multidomínio.

**Presentation:** AuthorIdentityResolver e AuthorCardBuilder, incluindo privacidade, avatar, apelido e caches. UI Suite/SDC apenas para apresentação; módulos consumidores não duplicam identidade.

**Views:** portar filtro autor OU coautor sem IDs legados, com tratamento de tabela real via table mapping, revisões, distinct e paginação. O filtro não autoriza visualização de resultados. Reconsiderar a dependência do nome de login; preferir o padrão de identidade pública nas interfaces exibidas.

**Tokens:** não manter co_authors_email nem lançar token equivalente que exponha dados privados em templates acessíveis. Se consumidores internos legítimos precisarem de e-mail, usar serviço privado com autorização e auditoria.

## 3. Sessões administrativas a implementar

Todas as rotas são **somente MAIN**, sob a árvore administrativa atual, com _admin_route, filtro de purpose na camada existente e permissões restritas. Não criar Studio paralelo nem redirecionamentos legados.

| Sessão | URL | Escopo |
| --- | --- | --- |
| Pessoas > Coautorias | /painel-administrativo/pessoas/coautorias | Listagem paginada de Nodes e coautores; filtros por usuário, bundle, Node, domínio e estado; ações contextuais e estados vazios; sem e-mail privado |
| Gerenciar coautores | /painel-administrativo/pessoas/coautorias/{node}/gerenciar | Form API de inclusão/remoção, autocomplete, validação de referências, CSRF e políticas de domínio; nunca alterar uid proprietário |
| Pessoas > Auditoria de coautorias | /painel-administrativo/pessoas/coautorias/auditoria | Eventos de mudança de coautoria, ator, Node, horário e tipo, com permissão de leitura e retenção mínima documentada |
| Configurações > Pessoas > Coautoria | /painel-administrativo/configuracoes/pessoas/coautoria | Bundles habilitados; critérios de exibição e delegação, sem conceder permissão Core via configuração; Config schema e defaults seguros |

Permissões sugeridas: **administer aculta coauthors**, **view aculta coauthor audit**, **edit aculta coauthors of own content**, **edit aculta coauthors of coauthored content**, **edit aculta coauthors of all content**. Não conceder papéis automaticamente. Cada rota/POST deve validar autorização em servidor; GET é somente leitura. Mostrar menus e subabas nas seções de Pessoas/Configurações > Pessoas já existentes, sem criar item raiz redundante.

Auditoria: registrar somente metadados mínimos em entidade/tabela ACULTA própria, com retenção e permissão restrita; não armazenar e-mails, fotos, endereços nem valores privados. Testar listagem, paginação, filtros, empty state, erros, CSRF, exportações apenas se deliberadamente incluídas, e acesso 404 fora de MAIN.

## 4. Consumidores

- **Revista (Marley):** remover node_co_authors de aculta_magazine.info.yml; preservar field_editorial_author/field_credits; integrar o novo campo apenas onde a autoria por conta tem significado. Revisar formulários, painéis e view displays.
- **Wiki (WE SATIVA):** coautoria não dá direito de aprovar/moderar ou contornar revisão. Reutilizar permissões e fluxo atuais de verbetes.
- **Eventos (Culturas):** coautor de atividade não se torna titular de Organization/Location. Validar CRUD e os domínios.
- **Fórum:** distinguir aculta_forum_topic (Node) de aculta_forum_post (entidade própria); não reconfigurar permissões de discussão.
- **aculta_index/Views/Search API/Studio:** reexaminar queries, filtros, indexação e cartões; não substituir ownerUid nem introduzir contagem duplicada.
- **Outros bundles:** inventariar todos os displays de Node e configurar explicitamente por bundle com defaults seguros.

## 5. Pacotes de execução (sem fases de convivência em Runtime)

1. **P0 Inventário completo:** rg por node_co_authors, co_authors, author_co_author_name, co_authors_email, field_coautores, dependências, Composer lock e configurações. Inspecionar Runtime e contar registros sem reproduzir dados pessoais. Determinar as integrações existentes e a ordem exata do corte.
2. **P1 Núcleo:** concluir o campo e Hook OOP em aculta_user, testes Kernel/revisionabilidade, validação de coautores, service DI. A fábrica e manager passivos desta entrega são o início.
3. **P2 Acesso R2:** implementar Node access e field access, respeitar Core, moderação, domain e cache contexts; aplicar testes negativos; exigir **revisão independente antes de publicar** a mudança sensível.
4. **P3 Administração:** implementar **as quatro sessões** da seção 3, com controllers/forms/rotas/menus/permissões/schema, logs mínimos; validar MAIN e CSRF.
5. **P4 Consumidores:** atualizar todos os displays, Revista, Wiki, Eventos, Fórum, Views, UI e quaisquer consumidores efetivos. Revisar importação de configuração e testes browser/no-JS.
6. **P5 Corte único R3:** janela de manutenção com backup/restore, autorização operacional específica, revisão independente, desinstalação do contrib enquanto seu código existe, descarte do campo antigo via APIs do Drupal, instalação da nova definição, config import final, Composer remove e cache rebuild. Não migrar registros antigos e não manter dois campos funcionais em produção.
7. **P6 Pós-corte:** testar rotas e permissões, inexistência de referências executáveis ao contrib, Composer/lock/core.extension/displays, status/schema, Views, revisão/publicação, MAIN e domínios; atualizar README, CHANGELOG, TODO/status e documentação de consumidores.

### 5.1. Operação de corte

**Nunca confundir commit/push com autorização para operar o banco.** O AGENTS.md raiz exige autorização operacional específica para pm:uninstall, updatedb, import estrutural, remoção persistente e Composer no Runtime. O pedido atual autoriza a publicação do código inicial e deste plano, **não** executa automaticamente essas operações.

Pré-condições: código final e testes prontos, planos de execução e reversão, comparação de config ativo/exportado, fotografia de código e banco do mesmo momento, backup restaurável verificado, disponibilidade do código contrib durante uninstall. Colocar Runtime em manutenção, parar gravações e concluir a sequência na mesma janela, sem uso do campo novo antes da remoção do antigo.

Após desinstalar com fluxo Drupal suportado, confirmar remoção/field purge, instalar nova definição via Entity Definition Update Manager, importar configuração revisada, remover o pacote em Composer/lock quando não mais necessário, rebuild, smoke e liberação. Se houver falha crítica, recuperar **em conjunto** banco e código/config anteriores. Não executar DROP manual nem apagar código do contrib antes de pm:uninstall.

### 5.2. Gates obrigatórios

- php -l em PHP alterado, PHPCS, PHPStan focal, php scripts/phpunit-suites.php --check, testes Unit/Kernel/Functional novos e de consumidores.
- Validar APIs Drupal 11 e compatibilidade Drupal 12; validar admin MAIN com scripts/validate-admin-routes-main-domain.php e scripts/validate-aculta-portal-drupal12.php.
- Cenários de acesso: anônimo, terceiro, dono, coautor, administrador, bloqueado, permissão ausente, bundle diverso, revisão, unpublished, domínio incorreto, autoatribuição e CSRF.
- Verificar Views com JOIN, distinct, filtro composto, paginação e revisão; confirmar nenhuma fuga de token/e-mail privado.
- Antes de sincronizar Runtime, seguir skill aculta-runtime-sync e seus gates. PHPUnit em banco de teste, nunca no banco do Runtime.
- PASS só se executado; DEFERRED quando não há vendor, revisão independente, banco ou ambiente.

**Classe de revisão:** R1 para o código passivo da entrega atual (revisão do diff e lint). R2 para autorização, privacidade e domínio (revisor independente). R3 para corte de schema/dados/Composer (revisor independente, backup/rollback e autorização operacional). O corte só é concluído quando a nova API está ativa, o contrib está ausente e todas as validações críticas passam.


## 6. Estado da Implementação e Fechamento (2026-11-14)

- **Inventário (P0):** Realizado com identificação de zero registros de `co_authors` no Runtime, 11 form displays e 21 view displays.
- **Núcleo (P1) e Acesso (P2):** Concluído com `CoAuthorFieldDefinition`, `NodeCoAuthorHooks`, `CoAuthorManager`, `CoAuthorValidationConstraint` e `CoAuthorAuditService`. Coautores de conteúdo têm permissão de visualização e edição condicionada a `access content` e permissões de autoria, respeitando bundles habilitados.
- **Regra de Exclusão de Fórum:** `aculta_forum_topic` expressamente excluído da coautoria (`aculta_coauthors` não registrado nem suportado neste bundle).
- **Administração (P3):** As 4 rotas administrativas foram implementadas sob `/painel-administrativo/` no domínio MAIN:
  - `/painel-administrativo/pessoas/coautorias` (`CoAuthorAdminController`)
  - `/painel-administrativo/pessoas/coautorias/{node}/gerenciar` (`CoAuthorManageForm`)
  - `/painel-administrativo/pessoas/coautorias/auditoria` (`CoAuthorAuditController`)
  - `/painel-administrativo/configuracoes/pessoas/coautoria` (`CoAuthorSettingsForm`)
- **Consumidores (P4):** `aculta_magazine` desacoplado de `node_co_authors`. Fórum limpo de resíduos de coautoria. Displays de nós em `config/sync` atualizados para `aculta_coauthors` (e removidos de `aculta_forum_topic`).
- **Corte do Contrib e Configuração (P5/P6):**
  - `drupal/node_co_authors` removido de `composer.json`.
  - `node_co_authors: 0` removido de `config/sync/core.extension.yml`.
  - Testes Unit e Kernel executados com 100% de sucesso.
  - Procedimento operacional de corte de Runtime (pm:uninstall / update no ambiente) documentado para execução com autorização explícita conforme `AGENTS.md`.
