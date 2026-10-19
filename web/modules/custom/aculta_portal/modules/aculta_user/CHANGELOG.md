# Changelog — aculta_user

## 0.4.0-dev.26 — identidade compartilhada de autoria — 2026-10-19

- `AuthorIdentityResolver` (`aculta_user.author_identity_resolver`) compõe a conta Drupal, o perfil principal, a política de link público e a metadata de cache já mantida por `PublicProfileLink`.
- `AuthorIdentity` exporta somente variante, apelido e URL canônica opcional; não contém entidade, e-mail ou campos de perfil privados. Variantes identificam consumidores, mas continuam compartilhando esse payload mínimo.
- `author_legacy` não é aceito pelo resolver de `User`; autoria histórica permanece entidade independente.
- Testes unitários direcionados: 3 testes, 12 assertions. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.25 — links de perfil com o apelido atual, em todo o site

- Nome de usuário e campos de referência a usuário (autor, remetente e participantes de conversa) apontavam para `/identidade/<uid>`, a página da própria conta, no lugar do perfil público. Agora apontam para `/u/<apelido>` no purpose `main`.
- `PublicProfileLink` (`aculta_user.public_profile_link`) é a única regra de URL do perfil: o apelido do perfil publicado no momento do render, a visibilidade do perfil e as tags de cache do perfil e da conta. Quando o apelido muda, o link muda junto, sem republicar nada.
- `UsernameLinkHooks`: `preprocess_username` (tema `username` do Core) e `preprocess_field` (campos de referência a usuário). Sem perfil público, o nome sai sem link.
- Verificado no Runtime de teste (2026-10-19): troca de apelido refletida no link e restaurada em seguida; perfil privado sai como texto.
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild).

## Não lançado — manter e-mail não confirmado na matrícula LMS

- A herança automática de `email_confirmed` agora depende do marcador real de `EmailConfirmationPolicy`, inclusive no carregamento da conta, no usuário da sessão e ao remover papéis herdados antes de salvar.
- Conceder `estudante` pela matrícula de turma não confirma o endereço nem concede permissões que dependem de confirmação.
- RUNTIME STATUS: DEFERRED; precisa de smoke test com conta não confirmada após F7.

## 0.4.0-dev.24 — sem papéis laterais

- `HerancaDePapeis` deriva o mapa só da cadeia; a limpeza ao excluir papel mexe só na cadeia.
- Testes: `HerancaDePapeisTest` (4) e `RoleInheritanceHooksTest` (2) atualizados.

## 0.4.0-dev.23 — limpeza ao excluir papel; documentação e testes da hierarquia — 2026-10-17

- Limpeza ao excluir papel (equivale ao hook do contrib): `RoleInheritanceHooks::userRoleDelete` → `HerancaDePapeis::removerPapel`, que recalcula a configuração com `RoleHierarchy::completar`. Laterais cuja categoria foi excluída voltam ao autenticado.
- Regra de papel novo sem categoria em `completar` (antes duplicada no formulário): mesma função nas duas pontas.
- Testes unitários: `HerancaDePapeisTest` (4), `RoleInheritanceHooksTest` (2).
- Documentação: `docs/HIERARQUIA-DE-FUNCOES.md` (comparação função a função com o contrib e cobertura de testes) e `docs/CLASSES-E-HOOKS.md`.
- RUNTIME STATUS: APLICADO. Testes de exclusão feitos com papéis temporários (lateral e categoria), com a configuração restaurada e conferida; papéis efetivos das contas de teste e páginas administrativas verificados.

## 0.4.0-dev.22 — herança de papéis própria (substitui role_inheritance) — 2026-10-17

- Herança de papéis portada para o aculta_user: `HerancaDePapeis` (mapa derivado de `aculta_portal.role_hierarchy`), `RoleInheritanceHooks` (user_load, user_presave e formulário de usuário) e `RoleInheritanceCurrentUserSubscriber` (usuário logado, prioridade 299).
- Papéis herdados são somados à conta em memória e retirados ao salvar; nunca são gravados como papel direto.
- Módulo contrib `role_inheritance` desinstalado no Runtime e removido do `composer.json` e do `composer.lock`.
- RUNTIME STATUS: APLICADO e verificado: papéis efetivos de 4 contas de teste idênticos ao baseline antes da desinstalação; páginas administrativas e de loja respondem conforme o papel.

## Não lançado — visibilidade dos fóruns por permissão (Role Inheritance / Role Hierarchy)

Classificação MINOR. Decisões do responsável (2026-10-17): dependências `drupal/role_inheritance` e `drupal/role_hierarchy`
adicionadas ao `composer.json`; visibilidade dos fóruns por permissão explícita; ações administrativas só para o administrador.

- Permissões `view aculta restricted forum email confirmed` e `view aculta restricted forum associado` (`aculta_user.permissions.yml`).
- `RestrictedForumGrants::grantsFor()` deriva os realms das permissões. Marcador de associado continua sendo vínculo da conta (`AssociadoMarker`), não papel.
- Regra composta mantida no código: a permissão de moderação do `content_editor` só vale com `email_confirmed`.
- Config: `email_confirmed` recebe a permissão de e-mail confirmado; `content_editor` recebe as duas. Com Role Inheritance, a herança de permissões do `content_editor` a partir de `email_confirmed` fica para a Fase 2.
- Nível `estudante`: permissão `view aculta restricted forum estudante`, realm `aculta_estudante`, valor `estudante` em `field_visibilidade` e papel `estudante` (`user.role.estudante.yml`).
- Moderador (`content_editor`) sem `email_confirmed` não recebe nenhum nível por permissão; o marcador de associado não depende dessa regra.
- Testes unitários de `RestrictedForumGrants::grantsFor()` (`tests/src/Unit/RestrictedForumGrantsTest.php`): 8 casos, OK no ambiente local (`aculta_user` inteiro: 26 testes, OK). O marcador de associado é a classe real `AssociadoMarker` sobre storage simulado, porque a classe é `final`.
- Dependências: `drupal/role_hierarchy` passa de `^2.0` para `^4.0`, porque a linha 2.x declara `drupal/core ^8 || ^9` e não instala no core 11. `drupal/role_inheritance` `^2.1` resolve para 2.1.1. `composer.lock` atualizado com apenas esses dois pacotes (2 instalações, sem atualizações nem remoções).
- Concessão automática de `estudante` ao se inscrever em qualquer curso (`EnrollmentRoleHooks`, `group_relationship_insert`, decisão de 2026-10-17). O papel não é removido ao sair do curso. Só alunos recebem: a matrícula com papel de professor, criador ou administração do LMS (`group_roles`) não concede (decisão de 2026-10-17).
- Papel `gerente_loja` (Gerente de Loja, decisão de 2026-10-17): produtos `produto_loja`, variações, atributos e categorias da loja, e relatórios do site. Financeiro só com administrador (pagamentos, gateways, envios, administração de pedidos e loja). Criado no Runtime de testes e exportado em `config/sync/user.role.gerente_loja.yml`. Vê pedidos (`view commerce_order`, decisão de 2026-10-17); relatório próprio de vendas previsto. Smoke: produto, relatório e pedidos liberados; pagamentos, gateways, envios e administração de pedidos e loja negados.
- `associado` continua atribuído manualmente pelo administrador (decisão de 2026-10-17).
- Runtime de testes (`aculta_runtime`, `aculta.toca.net.br`, 2026-10-17): criados o papel `estudante` e o valor `estudante` em `field_visibilidade`; permissões de visibilidade concedidas a `email_confirmed` e `content_editor`. Aplicação por API, sem importação completa de configuração, porque o Runtime diverge de `config/sync/` em muitos itens não relacionados. Smoke: `estudante` recebe `aculta_estudante`; `email_confirmed`, `aculta_email_confirmado`; `content_editor` sem e-mail, nenhum nível; matrícula de teste concede `estudante` (usuário e matrícula de teste removidos).
- Papel `associado` (`config/sync/user.role.associado.yml`, decisão de 2026-10-17): `RestrictedForumGrants` concede o realm de associados também por papel. O marcador de conta continua como fallback (no Runtime não havia marcas). Teste unitário do caso papel sem e-mail: OK.
- `drupal/role_hierarchy` e `drupal/role_inheritance` habilitados no Runtime e em `core.extension` (sem configuração). Verificado: a ativação só define as permissões `bypass role hierarchy` e `edit user roles`, concedidas apenas ao administrador (já com acesso total). Nenhum papel não-administrador mudou de permissões efetivas.
- Fase 2 (herança de papéis) aplicada no Runtime e exportada em `config/sync/role_inheritance.settings.yml`: `estudante` ← `email_confirmed`; `associado` ← `estudante`; `lms_teacher` ← `associado`; `content_editor` ← `email_confirmed`. Permissões efetivas medidas pelo mapa (`extendRoles`). Pendente de decisão: `gerente_loja` herdar ou não de `lms_teacher`, e o elo de `content_editor` (ver PAPEIS-PARTICIPACAO.md, seção 9).

## 0.4.0-dev.21 — contagem de respostas e chamada para visitante no tópico do Fórum (F4) — 2026-10-17

Classificação: MINOR (novos dados de apresentação do tópico).

- `ForumTopicPresentationHooks` (`node_view`, tópicos do Fórum, vista completa): conta respostas publicadas do campo `comment_forum` (consulta direta; a estatística do core não devolveu a linha do nó) e, para visitante, a chamada "Entrar para seguir, salvar ou denunciar" com destino de volta ao tópico.
- Metadados do render array somados, não substituídos (contextos do nó e do tema preservados).

## 0.4.0-dev.19 — fóruns para marcadores de associado (F-D20) e caminhos em /painel-administrativo — 2026-10-16

Classificação MINOR. Nível de visibilidade "Marcadores de associado" para fóruns. Moderadores também veem. Rotas e
caminhos internos padronizados em `/painel-administrativo/` (relatório em `/painel-administrativo/associados/relatorio`).

## 0.4.0-dev.18 — associado como marcador puro (F-D19) — 2026-10-16

Classificação MINOR. Associado deixa de ser papel: tabela própria `aculta_associado` (marcador, sem acesso a nada).
Administrador marca e desmarca em `/painel-administrativo/associados/{uid}/marcar`; relatório lê a tabela. Papel
`associado` removido (config e runtime). Nível "associado" removido da visibilidade dos fóruns.

## 0.4.0-dev.17 — autores históricos fora dos usuários do Drupal (F2) — 2026-10-16

Classificação MINOR. Autores legados viram entidade própria `aculta_legado_autor` (sem conta). Removidos o papel
`usuarios_legados_forum`, os campos de origem no perfil e o acesso por conta bloqueada. Tópicos e comentários
históricos apontam para a autoria pelo campo `field_autor_legado` e têm uid 0. Correção: o presave concede
`wiki_contributor` (antes, um save dentro de hook_user_update quebrava o módulo group).

## 0.4.0-dev.16 — F9: moderação pelo content_editor e papéis múltiplos — 2026-10-16

Classificação MINOR. `content_editor` exige e-mail confirmado para visualizar restritos e moderar. Moderação
(ocultar, despublicar, excluir) de tópicos e comentários do Fórum, exceto conteúdo histórico (`ForumModerationHooks`).
Permissões de moderação do `content_editor` conferidas no arquivo e no Runtime. Conferido por papel no teste.

## 0.4.0-dev.15 — F9: hierarquia de papéis, moderação do Fórum e 404 para restritos — 2026-10-16

Classificação MINOR. Hierarquia authenticated < email_confirmed < associado < content_editor < administrator
(documentada em `docs/portal/PAPEIS-PARTICIPACAO.md`). `content_editor` vê os níveis restritos. Visitante e bots
recebem 404 em contêiner e tópico restritos (subscriber `RestrictedForumNotFoundSubscriber`). Conferido no teste por
papel: anônimo e e-mail confirmado sem acesso; associado e editor com acesso; anônimo e bot recebem 404.

## 0.4.0-dev.14 — F9: sitemap, busca e comentários de fórum restrito — 2026-10-16

Classificação MINOR. Exclusão de nó e contêiner restritos do `simple_sitemap` (hook de links). Conferido no teste:
restrito fora do sitemap; comentário de tópico restrito invisível para anônimo e visível para administrador; forum
não está no índice de busca (tipos indexados: activity, article, document, page, project) e no sitemap (sem
configuração de tipo `forum`).

## 0.4.0-dev.13 — F9 parcial: fóruns restritos por nível (associado e e-mail confirmado) — 2026-10-16

Classificação MINOR. Papel `associado` versionado em `config/sync` (concessão manual, sem permissões). Campo
`field_visibilidade` no contêiner de fórum (público, e-mail confirmado, associado). Grants de nó por realm
(`aculta_email_confirmado`, `aculta_associado`), negação de acesso direto a contêiner e tópico, e filtro das
consultas com a tag `node_access`. Testado no Runtime de teste por papel: anônimo e e-mail confirmado não veem
contêiner, tópico nem listagem; associado vê. Contêineres públicos inalterados. Pendências da F9 no roadmap.

## 0.4.0-dev.12 — denúncias do Fórum e participação com e-mail confirmado (F5.1) — 2026-10-16

Classificação MINOR. Denúncia de tópico e comentário pelo Flag, deduplicada por conta; fila de moderação em
`/painel-administrativo/forum/denuncias` (permissão `moderar denuncias do forum`); participação no Fórum (criar,
editar o próprio conteúdo, comentar e denunciar) exige o papel `email_confirmed`. Seguir e salvar continuam em
`authenticated`. Testado no Runtime de teste: denúncia registrada, repetição recusada pelo Flag, fila renderiza
com link pelo alias, acesso anônimo negado (403).

## 0.4.0-dev.11 — estrutura de identidade histórica do Fórum (F2) — 2026-10-16

Classificação MINOR. Estrutura pronta para a importação; nenhuma conta histórica foi criada.

- Campos `field_nome_historico` e `field_forum_origem_id` no perfil participante.
- `LegacyAccountManager` (`aculta_user.legacy_accounts`): cria ou atualiza por origem, idempotente; conta bloqueada,
  papel `usuarios_legados_forum`, sem e-mail, sem apelido, perfil privado, sem conquista.
- `LegacyAccessHooks` (`entity_access`): nega página pública individual de conta legada (F-D08).
- Memorial `/u/lendashistoricas`: busca, paginação de 50 e estado vazio.
- Avatar especial: definido antes do primeiro save quando `assets/avatars/legado/lendas-historicas.png` existir;
  sem o arquivo, a conta fica sem foto. O sorteio do acervo nunca atribui avatar a conta legada.
- Testes unitários: `LegacyAccountManagerTest`, `LegacyMemorialReservedTest`.
- Pendências: lista de autores comprovados (importação), arte do avatar, texto aprovado do memorial, ligação
  do link de autoria nos comentários e tópicos do Fórum (depende da importação).

## 0.4.0-dev.10 — F2 estrutural do Fórum: memorial e papel legado — 2026-10-15

Classificação MINOR (rota e papel novos). Entrega estrutural do ACULTA Fórum F2 em
branch própria; pendências registradas em `aculta_forum/CHANGELOG.md`.

- Papel `usuarios_legados_forum` ("Usuários legados do fórum") em `config/sync`, sem permissões.
- Apelido `lendashistoricas` reservado em `AccountNames::RESERVED`: conta comum não pode escolhê-lo.
- Rota `/u/lendashistoricas` (`aculta_user.memorial_legados`), somente no purpose MAIN, com
  placeholder de pendência. A rota estática vence `/u/{apelido}` no mesmo host.
- Teste unitário `LegacyMemorialReservedTest`.
- Pendências: lista de autores comprovados, contas legadas bloqueadas, texto aprovado e avatar especial.

## 0.4.0-dev.9 — avatar ilustrativo em perfis sem foto — 2026-10-15

- `/u/<apelido>` escolhe uma imagem estável por conta do acervo atual de avatares
  quando não há foto pública. A imagem é apenas apresentação: não grava nem troca
  a foto da conta.
- Mantém a inicial como fallback de segurança caso o acervo esteja indisponível.

## 0.4.0-dev.8 — arquivo de imagem de conquista — 2026-10-15

- Definição referencia o arquivo enviado por UUID e registra uso pela API File Usage.
- Remoção confirmada desassocia a imagem e exclui o arquivo gerenciado sem outros usos; arquivos compartilhados são preservados.

## 0.4.0-dev.7 — catálogo configurável e auditoria de concessões — 2026-10-15

- `aculta_user.achievement_definitions` e schema preservam definições/critério, inclusive quando Game é removido.
- API única concede por gatilho predefinido ou administrador; configuração não executa expressões/código.
- Origem de cada concessão governa revogação: manuais revogáveis, automáticas históricas preservadas.
- Update 10003 adiciona motivos internos de concessão/revogação, sem alterar campos anteriores.
- Ator/data/motivo permanecem na entidade; nomes/descrições públicos não incluem dados de origem.

## 0.4.0-dev.6 — identidade dos troféus importados — 2026-10-15

- Catálogo inclui Fundadore manual e nomes originais Apoiadore e Associado/a Antiproibicionista.
- IDs e concessões antigas preservados. Imagens/metadados de apresentação pertencem ao Game.

## 0.4.0-dev.5 — pontos de integração do Game no perfil e na atividade — 2026-10-15

- Alters neutros `aculta_user_public_profile` e `aculta_user_activity_content`
  permitem Game opcional; User não depende dos serviços ou classes do Game.
- Module Handler injetado nos controllers; nenhuma lógica de conquista no tema.
- Evidências de G3 em `../aculta_game/docs/EXECUCAO.md`.

## 0.4.0-dev.4 — integridade da API de conquistas — 2026-10-15

- Novas concessões apenas a contas ativas e sem papel legado.
- Lock persistente por pessoa/conquista coordena concessão e revogação pela API;
  quando há transação externa, liberação ocorre depois de commit/rollback.
- Relógio injetado; identidade e histórico da entidade preservados.
- Validação e limites na fase G2 de `../aculta_game/docs/EXECUCAO.md`.

## 0.4.0-dev.3 — gatilhos de conquistas no Game — 2026-10-15

- `BadgeEvaluationHooks` passa ao submódulo opcional `aculta_game`.
- Catálogo, serviço, provider e dados de `aculta_badge_award` preservados no User.
- Ativar Game acompanha esta mudança para continuar avaliações automáticas.
- Evidência: `../aculta_game/docs/EXECUCAO.md`, fase G1.

## 0.4.0-dev.2 — ação de amizade no cabeçalho do perfil público — 2026-10-14

- Coloca a ação de amizade dentro do cabeçalho do cartão `/u/{apelido}` para membros autenticados, ao lado da identidade.
- O botão de solicitação usa o mesmo contrato visual de “Ver Perfil” da Minha Conta; a operação continua sendo Form API e `FriendshipManager`.
- A marcação inclui cache tag da lista de amizades para atualizar o estado depois de solicitar, cancelar ou remover.
- `RUNTIME STATUS: DEFERRED` até validar em navegador local autenticado.

## 0.4.0-dev.1 — relacionamentos privados entre membros — 2026-10-14

- Cria `AcultaFriendship` (`aculta_friendship`) como entidade privada por par, com chave string canônica única e estados explícitos.
- `FriendshipManager` fornece solicitações/transições sob lock e consultas por participante; acesso é controlado pelo handler da entidade.
- Perfil público autenticado mostra ações de solicitar/cancelar/remover; pedidos recebidos são tratados no centro de notificações do Messenger.
- `aculta_user_update_10002()` instala a entidade em sites existentes; não remove dados no uninstall.
- Verificados PHP lint, Drupal 11+ gate (696), YAML de metadados/serviços/permissões e `git diff --check`; runtime/Homelab DEFERRED.

## 0.3.0-dev.13 — contagem de comentários sem plural de pt-br em zero — 2026-10-14

Classificação: PATCH (correção de texto; sem mudança de contrato).

- "Ver discussão completa" mostrava "(1 comentário)" quando não havia comentário: em pt-br a regra plural trata 0
  como singular. Agora a forma é escolhida pelo número (1 → singular; 0 e demais → plural com o número).
- Verificado no runtime: publicação nova sem comentários passa a mostrar "(0 comentários)".
- RUNTIME STATUS: verificado no Homelab (HTTP). Inspeção visual humana pendente.

## 0.3.0-dev.12 — bloco de discussão em caixa própria, abaixo da autoria — 2026-10-13

- Bloco de discussão (`DiscussionHooks`) com tokens `discussion*` em `data-portal-ui` (sem classes de design) e peso 110,
  depois da caixa de autoria. Cor e borda ficam no tema (`css/components/discussion.css`).
- Testado com o tema padrão `aculta420` e com o Olivero (tema padrão trocado temporariamente, depois restaurado).
- RUNTIME STATUS: verificado no Homelab (HTTP, navegador e Olivero). Inspeção visual humana pendente.

## 0.3.0-dev.11 — discussão vinculada: plural do link e tradução correta — 2026-10-13

- `DiscussionHooks`: o link "Ver discussão completa" usa plural correto (1 comentário / N comentários) e
  tradução pelo `StringTranslationTrait`.
- Integração com o Fórum da Revista: a publicação cria o tópico automaticamente (`aculta_magazine`); o bloco
  de discussão desta página mostra o trecho e o link para o tópico no propósito FORUM.
- RUNTIME STATUS: verificado no Homelab (HTTP e banco). Inspeção visual humana pendente.

## 0.3.0-dev.10 — abas de Configurações (Modo de cor e Privacidade) — 2026-10-13

- Minha conta > Configurações com abas no padrão de Meus Dados: Modo de cor e Privacidade.
- Privacidade (`/configuracoes/privacidade`) só com as escolhas de visibilidade (perfil público, foto, bio,
  redes, e-mail). Apelido segue em Meus dados; bio e redes na visão geral. `/configuracoes/perfil` sai (404).
- Aba acrescentada pelo alter `aculta_portal_settings_tabs` (Portal: `aculta_portal.settings_tabs`).
- Privacidade: a opção "Mostrar foto" sai; a foto sempre aparece no perfil público. Bio, redes e e-mail continuam separados.
  Teste unitário da política atualizado (perfil privado: foto e apelido).
- Sorteio de avatar: token CSRF verificado (200 com o link do modal, 403 sem token).
- RUNTIME STATUS: verificado no Homelab (navegador). Inspeção visual humana pendente.

## 0.3.0-dev.9 — perfil público, cartão de autor e painel da Minha conta — 2026-10-13

- Perfil público `/u/<apelido>`: foto, apelido, bio e redes em botões com ícone e rótulo; e-mail como botão `mailto:`.
  Perfil privado mostra só foto e apelido. Rota no propósito `main`.
- Visão geral: botão "Ver Perfil" (após o título), caixa Bio bloqueada com modal de edição e caixa Redes sociais
  (Instagram, Facebook, Reddit) com lápis; gravação por JSON com token CSRF.
- Cartão de autor nas publicações da Revista (`ArticleAuthorsHooks`): autor e coautores (Node Co-Authors), foto,
  nome com link para o perfil público, bio e ícones das redes preenchidas.
- Privacidade: padrão público; autores e editores (`content_editor`, `wiki_editor`) sempre públicos.
- Dependência: `drupal/node_co_authors` ^1.2 (contrib, Drupal 11).
- Documentação: `docs/modules/PERFIL-PUBLICO-E-CARTAO-DE-AUTOR.md`.
- RUNTIME STATUS: verificado no Homelab (navegador e HTTP). Inspeção visual humana pendente.

## 0.3.0-dev.8 — Atividade em abas no padrão de Meus Dados e Segurança — 2026-10-13

- Minha Conta > Atividade: Wiki, Fórum, Revista, Comentários e Conquistas viram abas com o mesmo marcador e estrutura de Meus Dados e Segurança (`account-data-nav`, links com URL própria, aba atual marcada, título e área de conteúdo).
- `aculta_user.info.yml` 0.3.0-dev.8. RUNTIME STATUS: DEFERRED (inspeção visual humana pendente; captura conferida no Chromium).

## 0.3.0-dev.7 — sorteio só na prévia; OAuth como foto personalizada — 2026-10-13

- "Sortear novo avatar" troca só a prévia do modal (escolha pendente). A foto muda ao clicar em "Salvar foto do perfil". Fechar sem salvar descarta a escolha (INV-FOTO-8). Substitui o comportamento de 0.3.0-dev.5 (que gravava e recarregava ao fechar).
- Foto importada por OAuth (Social Auth) fica permanente e é personalizada (INV-FOTO-9). Configuração do Google pendente: ver `docs/integrations/GOOGLE.md`.
- `aculta_user.info.yml` 0.3.0-dev.7. RUNTIME STATUS: DEFERRED (inspeção visual pendente).

## 0.3.0-dev.6 — botão "Sortear novo avatar" na linha de ações — 2026-10-13

- O sorteio fica como botão na linha de ações, ao lado de "Salvar foto do perfil", no lugar de "Remover foto" (personalizada). Regra de exibição inalterada (INV-FOTO-2).
- `aculta_user.info.yml` 0.3.0-dev.6. RUNTIME STATUS: DEFERRED (inspeção visual pendente; captura conferida no Chromium).

## 0.3.0-dev.5 — sortear atualiza o painel por AJAX; fechar o modal recarrega — 2026-10-13

- "Sortear outro avatar" troca por AJAX o avatar da visão geral e da prévia, sem recarregar.
- Ao fechar o modal depois de sortear, a página recarrega uma vez (INV-FOTO-8). Fechar sem sortear não recarrega.
- `aculta_user.info.yml` 0.3.0-dev.5. RUNTIME STATUS: DEFERRED (navegador verificado com Chromium; inspeção visual pendente).

## 0.3.0-dev.4 — foto de perfil: popup, estados, recorte e remoção (consolidado) — 2026-10-13

- Popup da foto é modal Drupal centralizado (`/avatar/foto`), com prévia do avatar atual e a orientação do popup anterior. Lápis de edição sobre o gatilho.
- Regras por estado (`docs/FOTO-PERFIL.md`): avatar do sistema sem "Remover", com "Sortear outro avatar" e campo de envio; foto personalizada com "Remover foto" (botão próprio, sorteia do acervo e recarrega o painel), sem sortear.
- Campo de envio sempre vazio: sem nome nem link da foto atual no popup.
- Recorte só logo após o envio, e só do arquivo recém-enviado. "Aplicar recorte" torna o arquivo permanente, troca a foto da conta e recarrega o painel (sem reabrir o popup: era o loop).
- Recorte sem abas ("Crop image"/tipo) e sem duplicação do campo (pre_render limpo); modal de recorte com 560 px e 75% da altura, com rolagem.
- Formulários de foto sem `readonly` nas dependências (rebuild AJAX do cache).
- Gate: `scripts/validate-aculta-user-photo.php` (INV-FOTO-1 a INV-FOTO-7). Roteiro: TESTES-NAVEGADOR seção P.
- `aculta_user.info.yml` 0.3.0-dev.4. RUNTIME STATUS: DEFERRED (teste de navegador do fluxo de foto pendente).

## 0.3.0-dev.3 — regras de estado da foto de perfil — 2026-10-13

- Avatar do sistema: sem "Remover"; campo de envio com "Escolha uma imagem"; "Sortear outro avatar" visível.
- Avatar personalizado: "Remover" visível; sem "Sortear outro avatar".
- "Recortar foto" removido do popup: o recorte abre só logo após o envio, e só para o arquivo recém-enviado.
- Regras em `docs/FOTO-PERFIL.md`; gate `scripts/validate-aculta-user-photo.php`; roteiro de teste em TESTES-NAVEGADOR seção P.
- `aculta_user.info.yml` 0.3.0-dev.3. RUNTIME STATUS: DEFERRED (navegador pendente; ver seção P).

## 0.3.0-dev.2 — sortear avatar no popup de foto (AJAX) — 2026-10-12

- Botão "Sortear outro avatar" no popup de foto do painel da conta. Por AJAX troca só o bloco da foto
  (`#aculta-user-avatar`); sem JavaScript, o envio comum volta ao painel. Nunca sorteia o mesmo avatar quando há outro.
- A cópia anterior do acervo é apagada quando nenhuma outra conta a usa.

## 0.3.0-dev.1 — cadastro, avatar, associado e relatório (fase 5, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Cadastro: o nome de usuário vira o apelido inicial do perfil (sem colidir; reservadas e formato validados). Conta
  OAuth recebe como nome de usuário a parte local do e-mail recebido, sem o domínio (`SocialAuthFieldsSubscriber`,
  evento `social_auth.user.fields`).
- Avatar do acervo: quem não tem foto recebe um sorteado (`AvatarPool`), copiado para `user_picture` como arquivo
  gerenciado; a escolha fica em `user.data` (`avatar_id`). A família `mushrooms` foi removida do acervo (10 arquivos
  inexistentes). A foto do provedor OAuth continua sendo importada pelo `social_auth`.
- Troca de foto: a foto anterior enviada pela pessoa é apagada quando não há outra referência (`PictureReplacementHooks`).
  Nunca apaga cópia do acervo (`public://pictures/acervo/`). No Runtime não há foto enviada por usuário hoje.
- Papel `associado`, sem permissões; só o administrador concede.
- Campos do perfil, sem cifra: `field_rg`, `field_orgao_emissor`, `field_profissao`, `field_data_nascimento`. O CPF/CNPJ
  continua no campo único existente (`field_documento`, cifrado pelo módulo de documento).
- Relatório `/admin/aculta/associados` (permissão `administer aculta associados`); RG mascarado na tela. Exportação CSV
  em `/admin/aculta/associados/exportar`, somente com `export aculta associados` (administrador).
- Pendências: botão "sortear outro avatar" em Minha Conta; campos de Outros Dados no formulário da conta; estado e data
  de adesão (não decidido: foi usado só o papel); endereço no relatório; teste de Kernel (ao final da série).

## 0.2.0-dev.2 — Atividade em Minha Conta (fase 4, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Rota `/atividade/{aba}` (wiki, forum, revista, comentarios, conquistas), só no propósito `account`. Cada aba lista
  só o que a própria pessoa fez e ainda está publicado; sem atividade de outra pessoa. Abas são links com a aba atual
  marcada (sem JavaScript). Vazio: mensagem curta.
- Wiki: verbetes publicados em que a pessoa tem revisão, com link para o histórico público do verbete.
- Item "Atividade" no menu da conta (`AccountShellBuilder`), depois de "Cursos". Aparece só se a rota existir: o Portal
  não depende do submódulo.
- Conquistas: placeholder até a fase 6 (badges).
- Pendências: flags de tópico seguido e salvo na aba Fórum; explicação dos limites de participação na aba; nome do
  item de menu e slugs das abas (decisão 20, usados os padrões do plano); verificação no Runtime (DEFERRED).

## 0.2.0-dev.1 — participação no Fórum (fase 3, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Regras de participação (`ParticipationRules`, derivadas de contagens, sem contador persistido): comentário de Fórum
  de quem não tem comentário publicado vai para a fila de moderação (`comment_presave`); os seguintes são publicados.
- Criação de tópico de Fórum exige comentários publicados (padrão 10) e fica bloqueada enquanto o primeiro comentário
  aguarda aprovação (`entity_create_access`). Mensagem de bloqueio no próprio acesso; a explicação na Atividade fica
  para a fase 4.
- Painel `/admin/config/aculta/portal/participacao` (permissão `administer aculta participacao`), com
  `aculta_user.settings`: limite ligado/desligado, mínimo, primeiro comentário moderado, bloqueio e papéis isentos
  (padrão: `content_editor` e `administrator`).
- Discussão vinculada: campo `field_discussao` (referência a tópico de Fórum) em `article`, `activity`, `project`,
  `document` e `wiki_entry`. A página mostra os últimos 5 comentários publicados e o link para a discussão completa no
  propósito `forum` (via `DomainPurposeManager`). Sem tópico, não há bloco.
- Pendências: configuração e campo ainda não aplicados no Runtime (`config:import` exige autorização); matriculados
  (fase LMS); cartão de pré-visualização (E6), que depende de apresentação no ACULTA420; testes (ao final da série).

# Changelog — aculta_user

## 0.1.0-dev.1 — perfil público por apelido (U1, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Rota pública `/u/{apelido}` (exceção de slug registrada em `FRIENDLY-PORTUGUESE-SLUGS.md`). Só serve no
  propósito `main`; perfil inexistente, privado ou fora do propósito responde 404.
- Visibilidade por item (`PublicProfilePolicy`): apelido público por padrão; foto, bio, e-mail e redes sociais
  só aparecem se a própria pessoa escolher. Cidade, UF, WhatsApp, nome completo e endereço nunca são públicos.
- Formulário `/configuracoes/perfil` (apelido, perfil público e escolhas por item). Apelido com formato, palavras
  reservadas e unicidade validados no formulário.
- Campos do perfil `participante` em `config/install`: `field_perfil_publico`, `field_exibir_foto`,
  `field_exibir_bio`, `field_exibir_email`, `field_exibir_redes`, `field_bio`, `field_redes_sociais`.
- `field_nickname` passa a ser público (`profile_private: false` em `config/sync`).
- Teste unitário de `PublicProfilePolicy` (5 testes).
- `RoleSyncHooks` (`#[Hook]` user_insert/user_update): concede `wiki_contributor` a quem tem `email_confirmed` (decisão 28). Não remove o papel quando `email_confirmed` sai (não decidido). Teste unitário (3 casos).
- Pendências: vínculo do link a `/configuracoes` (bloco do Portal); apresentação no ACULTA420 (sem `data-portal-ui`
  nesta entrega); badges (U4); teste de Kernel da rota e da privacidade; execução no Runtime (`drush en` e
  `config:import` exigem autorização).
# Changelog — aculta_user

## 0.4.0-dev.1 — workflow de amizade

- Adiciona a entidade privada `aculta_friendship`, com um registro por par e
  identificador composto como chave primária para garantir unicidade no banco.
- Adiciona `FriendshipManager` e API pública para solicitar, aceitar, recusar,
  cancelar, remover e consultar amizades. O serviço usa Entity API, DI e lock
  persistente; não copia SQL, service locator, endpoints GET nem plugins Views
  do projeto Friendship.
- Estudo de origem e decisões: `docs/AMIZADES.md`.
- `RUNTIME STATUS: DEFERRED` — entidade ainda não instalada; interfaces,
  formulários e integração de notificações seguem em desenvolvimento.


