# Plano do submódulo `aculta_user`

**Ciclo vigente:** Dev → RC (código, testes, gates e revisão visual aplicável prontos) → deploy/validação Alpha no servidor de desenvolvimento → produção, conforme [ciclo de entrega](../operations/RELEASE-LIFECYCLE.md). Este plano histórico mantém registros antigos, mas não adia requisitos para depois do RC.

Status: **plano de 2026-10-13, com implementação já iniciada**. O submódulo existe em `aculta_portal/modules/aculta_user` (versão 0.4.0-dev.2, `CHANGELOG.md` do submódulo). Esta seção 1 descreve a base de 2026-10-13 (`main` em `ce0941a`, Portal 0.3.0-dev.28) e não é mais o estado atual. O estado de cada decisão e de cada entrega está em [ACULTA-USER-DECISOES-E-IMPLEMENTACAO.md](ACULTA-USER-DECISOES-E-IMPLEMENTACAO.md); a verificação da fase U0 está em [ACULTA-USER-U0-VERIFICACAO.md](ACULTA-USER-U0-VERIFICACAO.md).

Escopo: avatar, perfil público e autoria, integração com Wiki, Fórum e revista, funções sociais e gamificação, dentro de `web/modules/custom/aculta_portal/modules/aculta_user/`.

**Evolução aprovada em 2026-10-15:** o planejamento do Fórum passa a ser mantido
no [projeto ACULTA Fórum/Growroom](../../web/modules/custom/aculta_portal/modules/aculta_forum/README.md)
e o de conquistas/missões no [projeto ACULTA Game](../../web/modules/custom/aculta_portal/modules/aculta_game/README.md).
As decisões novas incluem memorial coletivo `/u/lendashistoricas` com nomes e
autorias individuais, sem perfis públicos individuais de legados, e conquistas
públicas por padrão com opções de ocultação e destaque. Essas decisões superam
formulações antigas contraditórias de apresentação/gamificação deste plano;
não significam migração dos hooks, provider da entidade ou configuração do Runtime.
A nova pesquisa compara releases e código, inclusive diferenças com páginas antigas.

**Atualização de arquitetura (2026-10-19):** a reconstrução aprovada em
`aculta_forum` substitui Forum contrib por um motor compartilhado de tópicos e
comentários contextuais. O código e as tabelas de implementação desta página
continuam descrevendo o estado atual; os novos contratos de identidade/cartões
estão em [`aculta_user/docs/AUTHOR-CARDS.md`](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/AUTHOR-CARDS.md).
Não apagar hooks ou configurações legadas sem a fase de migração descrita em
`aculta_forum/docs/MIGRATION-REMOVAL.md`.

## 1. Estado atual (o que já existe)

| Capacidade | Onde está | Situação |
| --- | --- | --- |
| Foto de perfil | Core `user_picture` (`config/sync/field.field.user.user.user_picture.yml`), estilo `aculta_avatar`, crop (`crop`, `image_widget_crop`) | Existe. Editor de foto na Minha conta (`PortalController`). Sem avatar padrão nem fallback público. |
| Apelido e dados do participante | Profile `participante` com `field_nickname`, nome, cidade, UF e WhatsApp (`config/sync/field.field.profile.participante.*`) | Existe. Não é público. `field_nickname` não é único nem é slug. |
| Conexões sociais | `social_auth` e `social_auth_google`; rota `/conexoes` | Existe. |
| Fórum | `forum` 1.1.3, `flag` 5.1.1, flags `seguir_topico` e `salvar_topico` (`0.2.0-dev.57`) | Adotado. Sem página "Atividade". |
| Wiki | Nó `wiki_entry`, papéis `wiki_contributor` e `wiki_editor`, workflow `wiki_editorial`, módulo `diff` | Existe. Sem "Minhas contribuições". |
| Revista | Nós `article` (Notícia), `document`, `activity`, `project`; autor = `node.uid` | Autor vem do Core, sem página de autor. Perfil público em `/u/<apelido>`, exceção registrada em `FRIENDLY-PORTUGUESE-SLUGS.md`; sem rota no código ainda. |
| Notificações | Symfony Mailer (`symfony_mailer`, camada de envio E-Mails ACULTA) | Transporte pronto. Sem assinaturas. |
| Gamificação, seguir pessoas, menções, denúncia | Não existem | `DT-P13`: denúncia de comentários pendente. |
| Anti-abuso | `flood_control`, Turnstile, `username_enumeration_prevention` | Existe. |

Rotas planejadas e ainda não existentes: `/atividade`, `/contribuicoes`, `/meus-cursos` (existe), `/conexoes` (existe).

## 2. Bloqueios e enquadramento no roadmap

- `docs/portal/ROADMAP.md` diz que features de produto (F1–F4) só começam depois de S0–S4, ou quando o responsável priorizar a fase.
- Hoje S2 (pagamento) e S3 (validação em navegador) estão abertas, e S4 tem a decisão DT-P10 (módulos de administração em produção) aberta.
- O `AGENTS.md` exige decisão do responsável para novas fases. Por isso este plano é uma proposta, não uma ordem de execução.
- O escopo cai em **F3 (Participação)** e **F4 (Engajamento)**, com parte do perfil em **F1 (Conta completa)**.
- O `aculta_user` depende da política de Domain: as páginas de autor e a participação precisam de purpose definido (seção 7).

## 3. Módulos contrib: o que a pesquisa encontrou

**Limite desta sessão:** `drupal.org` está bloqueado pela política de egress do proxy (403 em `www.drupal.org:443`). Não contornei o bloqueio. A compatibilidade com Drupal 11 **não está confirmada** para nenhum candidato novo. O código do Core e do contrib não está instalado aqui (`web/core` e `vendor/` ausentes), então nenhuma API de contrib foi verificada no código.

Verificação a fazer no Homelab, antes de qualquer adoção: `composer show -a <pacote>` e `composer require --dry-run`, usando o repositório `packages.drupal.org/8` já declarado em `composer.json`.

| Necessidade | Candidato | O que a busca encontrou | Recomendação |
| --- | --- | --- | --- |
| Pontos e gamificação | `userpoints` / `user_points` | Projeto de D8 com alpha antigo; `user_points` marcado como obsoleto. Sem D11 confirmado. | Não adotar. |
| Pontos e badges | `gamification` 1.1.0-beta6 | Declara `^9 || ^10`, sem D11. Manutenção mínima. | Não adotar. Ver seção 5.G. |
| Seguir pessoas | `friendship` | Documenta seguir e seguidores com campos de Views. Versão D11 não confirmada. | Avaliar após verificação no Homelab. Alternativa: Flag sobre entidade `user`. |
| Seguir pessoas | `follow`, `follow_unfollow` | `follow` é para links de redes sociais; `follow_unfollow` é de era D7. | Não adotar. |
| Notificações de comentários | `comment_notify`, `scn` | `scn` cita suporte a D11 e manutenção mínima; `comment_notify` sem D11 confirmado. | Não adotar sem verificação. Preferir camada de envio E-Mails ACULTA + fila do Core. |
| Notificações genéricas | `message`, `message_notify` | Branch de desenvolvimento de 2024, "use por sua conta e risco". | Não adotar. |
| Avatar externo | `gravatar` | Não portado para D8+. | Não adotar (ver 5.A). |
| Avatar | `avatar_kit` | Citado como alternativa ao Gravatar. D11 não confirmado. | Não adotar sem verificação; a necessidade é pequena. |
| Menções `@usuário` | `mentions`, `ckeditor_mentions` | `mentions` declara `^8 \|\| ^9 \|\| ^10`. `ckeditor_mentions` não notifica. | Adiar (fase U5). |
| Atividades | `activity_creator` | Sem verificação. | Não adotar. Feed por Views (5.F). |
| Autor, perfil, slug | `profile` 1.14, `pathauto` 1.15, `token` 1.17, Views (Core) | Já instalados. | Usar. |
| Participação | `flag` 5.1.1, `forum` 1.1.3, `diff` 2.1.1 | Já instalados. | Usar. |

Regra do projeto: não adicionar módulo quando Core ou contrib já instalado resolve. Nenhuma dependência nova é proposta nesta fase. Qualquer nova dependência exige explicação prévia (`AGENTS.md`, regra 14).

## 4. Arquitetura proposta

```text
Core (user, node, comment, Views, image, Mailer)  +  contrib instalado (profile, flag, forum, pathauto, diff)
            |
            v
   aculta_user  (submódulo do aculta_portal)
   serviços DI · presenters · Views de agregação · eventos (#[Hook])
            |
            v
   contrato neutro de dados (data-portal-ui, render arrays, cache metadata)
            |
            v
   ACULTA420  (SDC/Bootstrap de cartão de autor, avatar, badges — só apresentação)
```

Princípios, conforme `GUARDRAILS.md` e `DRUPAL-11-STANDARDS.md`:

- **Sem storage novo nas fases U1–U3.** Nenhuma tabela de pontos, de seguidores, de contribuições ou de comentários. Contagens saem de Views e de entidades reais.
- **Sem service locator** em `src/`: injeção de dependência nos serviços.
- **Hooks novos em `#[Hook]`**. Eventos de entidade (`entity_insert`, `entity_update`) são OOP. Nenhum `hook_*` procedural novo.
- **Sem classes de design no módulo.** Marcação com `data-portal-ui` (`account-avatar`, novos tokens como `author-card`, `badge-list`). O ACULTA420 mapeia em `PortalUiHooks.php`, com autorização explícita para tocar o tema.
- **Cache e access.** Cada presenter acumula `CacheableMetadata`: tags `user:<uid>`, `profile:<id>`, `node_list`, `comment_list`; contexto `user.permissions` quando a visibilidade depender do papel.
- **Caminhos em português.** Gate `validate-public-slugs.php`.
- **Endereços de ambiente** só em `aculta_deployer/config/deploy.json`. Gate `validate-environment-addresses.php`.

Serviços previstos (nomes provisórios):

- `PublicProfilePresenter`: monta apenas os campos públicos e o avatar (ou o fallback neutro).
- `PublicProfilePolicy`: decide, por campo, o que é público. Centraliza a privacidade.
- `ContributionSummary`: contagens de Wiki, Fórum e revista por Views ou `EntityTypeManager`.
- `AuthorshipPresenter`: autor de um nó publicado, sem vazar conteúdo não publicado.
- `FollowService`: envolve a Flag de pessoa. Não duplica estado.
- `BadgeEvaluator`: avalia condições derivadas, sem persistir resultado.

## 5. Funções por área

### A. Avatar

**Regra do responsável (2026-10-13):** quem não tem foto recebe um avatar aleatório do acervo do módulo. Isso vale para quem não enviou foto e para quem entrou por OAuth sem foto importada. Em Minha conta > Visão geral, a pessoa pode trocar o avatar do acervo por outro sorteado ("rotacionar").

**Inventário atual dos assets** (`web/modules/custom/aculta_portal/assets/avatars/`, versionado no Git, 112 arquivos):

- `meta/avatars.json`: 117 entradas, todas `active: true`, com `id`, `file`, `family`, `label` e `alt` (texto alternativo em português).
- `meta/avatar-families.json`: 12 famílias, com `id`, `label`, `count`, `active`, e campos opcionais `version`, `format` e `crop`.
- `meta/avatars-monsters.json` e `meta/avatars-aliens.json`: listas de 10 entradas cada, sobras de versões anteriores. Não são lidas por nenhum código hoje.
- `README.txt`: descreve a coleção v2 e o destino sugerido.
- Imagens: 107 PNG 1:1 em 11 pastas (`aliens`, `brazilian`, `cerrado`, `ghosts`, `lighter-rats`, `monsters`, `plants`, `reggae`, `reptiles`, `undead`, `urban`).

**Divergências encontradas, a corrigir antes de U1:**

- A família `mushrooms` ("Cogumelos e fungos") tem 10 entradas no `avatars.json` e nas famílias, mas **nenhum arquivo** existe no disco nem no histórico do Git (`git log --all -- '*mushroom*'` vazio). Um sorteio hoje falharia para esses 10 itens. Ou os arquivos são recuperados, ou as entradas saem do metadado.
- Os contadores do `README.txt` ("117 avatares", "12 famílias") batem com o `avatars.json` e não com os arquivos existentes (107).
- Não há validação de que cada `file` do JSON existe. Propor um gate que falhe quando houver `file` ausente, `alt` vazio ou `id` duplicado.
- Os JSON `avatars-monsters.json` e `avatars-aliens.json` são duplicatas de `avatars.json` sem uso. Remover só com autorização.

**Como o sistema deve funcionar (proposta):**

- Fonte da verdade do acervo: `meta/avatars.json`, lido por um serviço do `aculta_user` (DI). Entradas com `active: false` não são sorteadas.
- Novos avatares entram por acréscimo: arquivo PNG 1:1 na pasta da família, nova entrada no JSON com `alt` e `label`, e a família em `avatar-families.json` se for nova. Nenhuma alteração de código.
- Sorteio aleatório na primeira vez que a conta fica sem foto (e na troca), com `random_int` sobre as entradas ativas e existentes. Sem garantia de unicidade entre contas, a não ser que seja pedido.
- Persistência: o avatar sorteado precisa virar uma escolha estável da conta (não sortear a cada página). Como não criar storage paralelo, a escolha vai para o próprio `user_picture`: o acervo é copiado para o campo como arquivo gerenciado (`file_managed`, com origem marcada) ou, se o Core não der conta da marcação, a escolha fica em `user.data` do próprio módulo (decisão a confirmar no Homelab em U0).
- Quem enviou foto própria nunca perde a foto: o sorteio só ocorre sem foto. A "rotação" só troca entre avatares do acervo; ela não apaga foto enviada sem confirmação.
- OAuth: hoje o `aculta_portal` não importa a foto do provedor (não há referência a `picture` em `SocialAuth*Subscriber`). Decisão: importar ou não a foto do Google, e, se importar, aplicar o acervo quando o provedor não trouxer foto.
- Fallback sem foto e sem acervo ativo: inicial, como hoje (`account-avatar-empty`).
- Estilo: `aculta_avatar` (1:1, 256 px, crop `aculta_avatar_1x1`). O acervo já está 1:1, então o crop automático não deve cortar as artes; validar com `image_style` antes de publicar.
- Apresentação: o presenter entrega o caminho do arquivo e o `alt` do acervo; o tema desenha. Nenhuma classe de design no módulo.
- Gravatar: não usar (ver decisão 7).
- Foto pública é opt-in (decisão 2). Avatar do acervo não depende de opt-in: é imagem institucional, não dado pessoal.
- Moderação de imagem enviada pela pessoa fica fora desta fase; Core não a oferece.

### B. Perfil público e autoria
- Página `/u/<apelido>` (caminho confirmado pelo responsável em 2026-10-13; proposta de host: `aculta.*/u/<apelido>`) com foto, nome público (apelido), resumo curto, papel na associação (quando houver) e listas de contribuições.
- **Host e purpose ainda não definidos** (decisão 5). Enquanto não houver decisão, nenhum host é fixado no código. Pela política de domínio, rota pública em host de outro purpose responde 404 (`ADMIN-DOMAIN-POLICY.md`, `CROSS-DOMAIN-REQUEST-POLICY.md`).
- Campos novos no perfil `participante`: `field_bio` (texto curto) e `field_visibilidade_publica` (booleano, padrão falso). Não há campo de slug separado: o caminho público é o próprio apelido (`field_nickname`).
- **Unicidade do apelido:** `field_nickname` é texto livre, opcional, sem restrição de unicidade em `config/sync/field.field.profile.participante.field_nickname.yml` e sem validação de unicidade no `aculta_portal` (o `PortalHooks` só limita os campos exibidos no formulário). A unicidade deve ser garantida **por validação** no formulário e no cadastro, antes de o Pathauto gerar o alias. Não se deve depender do Pathauto para isso: quando um alias já existe, o `AliasUniquifier` acrescenta sufixo numérico (`web/modules/contrib/pathauto/src/AliasUniquifier.php:91-93`), o que muda o caminho público e viola a regra de slug estável.
- Pathauto gera o alias a partir do apelido (token `[user:participante:field_nickname]`; verificação de geração no Runtime pendente, U0 e U1).
- **Nunca público:** e-mail, CPF, endereço, WhatsApp, nome completo, cidade e UF (decisão 2).
- Alteração de slug troca o caminho e o antigo responde 404, sem 301 (`FRIENDLY-PORTUGUESE-SLUGS.md`). Por isso, o módulo `redirect` precisa ficar **fora** da criação automática de redirect quando o Pathauto mudar um alias.

### B1. Todo usuário tem perfil

**Decisão do responsável (2026-10-13):** todos os usuários do site têm perfil.

**Situação atual:** o perfil `participante` é criado só de forma lazy, em memória, quando a pessoa abre "Meus dados" (`PortalController::loadParticipantProfile`). Não há criação no cadastro. Por isso muitas contas não têm perfil salvo.

**Proposta:**

- **Criação no cadastro:** `#[Hook('user_insert')]` cria o perfil `participante` para toda conta nova, inclusive as criadas por OAuth (que também passam por `user_insert`). Sem depender de a pessoa abrir alguma página.
- **Apelido provisório:** a conta nova recebe apelido gerado (ex.: `pessoa-<id>`), único, com perfil privado. A pessoa troca o apelido em Configurações (B2). Apelido gerado não é público enquanto o perfil estiver privado.
- **Regras do apelido:** minúsculas, sem acento, letras, números e hífen, 3 a 40 caracteres, único. Lista de palavras reservadas (ex.: `admin`, `u`, `perfil`, `entrar`, `minha-conta`, `atividade`) para não colidir com rotas.
- **Contas já existentes:** precisam de perfil salvo. Isso é um update hook de dados, que cria perfil e apelido para cada conta. **Não roda sem autorização explícita**, e antes disso é preciso um backup do Runtime (`estados/` não é versionado).
- **Nome de exibição:** quando o perfil não tem nome público, o site usa o apelido. Nunca o e-mail.
- **Cache:** tags do perfil; a criação do perfil invalida a cache do usuário.

### B2. Perfil público ou privado (Minha conta > Configurações)

**Pedido do responsável (2026-10-13):** a pessoa pode tornar o perfil privado em Minha conta > Configurações.

- **Padrão (decisão 2, respondida nesta sessão): todos os perfis são públicos por padrão** (campo `field_perfil_publico` com valor padrão 1, em `config/sync/field.field.profile.participante.field_perfil_publico.yml`). A pessoa pode tornar o perfil privado. Foto, bio, e-mail e redes sociais são privados por padrão e só aparecem se a pessoa escolher exibi-los. Esta regra substitui o padrão privado anterior.
- **Autores e editores são obrigatoriamente públicos** (regra do responsável, 2026-10-13): perfis com o papel `content_editor` (Editor de conteúdo) ou `wiki_editor` (Editor da Wiki420) não podem ser privados. O controle fica bloqueado, com o motivo exibido. Implementação: `PublicProfilePolicy::PAPEIS_PUBLICO_OBRIGATORIO` e `isObrigatorioPublico()`, usados pelo formulário de privacidade, pela página pública e pela caixa de autores da Revista.
- **Lugar:** o formulário fica em `/configuracoes/perfil` (submódulo `aculta_user`, `ProfileVisibilityForm`). Campo `field_perfil_publico` no perfil `participante`, booleano, padrão verdadeiro. Ligar ou desligar tem efeito imediato. Vínculo a partir de `/configuracoes` pendente.
- **Com perfil privado:**
  - `/u/<apelido>` mostra somente foto e apelido (decisão 40 substituída em 2026-10-13: o perfil privado não responde 404).
  - Cartão de autor da revista mostra o nome de exibição sem link.
  - Badges (seção G) não aparecem em lugar público.
  - Conteúdos da pessoa (artigos, verbetes, tópicos) continuam públicos, porque são contribuições editoriais, não o perfil. O que muda é a página de perfil e os dados pessoais expostos.
- **Mesmo com perfil privado, aparecem:** nome de exibição em históricos públicos de verbete (decisão 21) e autoria de artigo. Isso é conteúdo, não perfil. Pergunta de consentimento na decisão 39.
- **Nunca público, mesmo com perfil público:** CPF, RG, endereço, WhatsApp, data de nascimento, cidade e UF (seção B). E-mail e redes sociais só aparecem se a pessoa escolher exibi-los.
- **Acesso:** a própria pessoa vê e altera a opção. Administrador não altera a opção de outra pessoa; pode ver o status no relatório (seção G2), sem dados pessoais além do necessário.
- **Cache:** a página pública do perfil acumula a tag do perfil e o contexto `user.permissions`; ao mudar a opção, as tags invalidam o cache.
- **Auditoria:** mudança da opção registra data e origem (pessoa), sem valor de outros campos.

### C. Autoria da revista (MAGAZINE)

**Decisão (2026-10-13, opção 1 — caixa de autor programada, sem módulo contrib):** a caixa de autor das publicações da
Revista é montada no `aculta_user` e renderizada pelo ACULTA420. Não usa Display Suite, Field Group nem Views como fonte
de privacidade: esses caminhos não aplicam `PublicProfilePolicy`. Layout Builder (Core) fica como possibilidade futura de
posicionamento, não como fonte de dados.

- Autor = `node.uid`, do Core. Não criar campo "autor" paralelo. O campo `field_editorial_author` (taxonomia
  `editorial_author`) não entra na caixa: é autoria institucional ou de convidado, outro conceito.
- Cartão de autor no fim do artigo (`article`, e futuro tipo de publicação da Revista), com link para `/u/<apelido>`
  somente quando o perfil for público; sem link quando não for.
- Listagem por autor: View com filtro de `uid` e status publicado, no purpose MAGAZINE (fase U2, depois do cartão).
- Autor convidado sem conta: **não** criar entidade própria. Opção mínima: campo de texto de assinatura no nó (decisão 4).

#### C1. Caixa de autor: arquitetura da opção 1

Fluxo de dependência (Core/contrib → Portal → contrato neutro → tema → SDC/Bootstrap):

```text
Core (node, user, profile, image)
   |
   v
aculta_user: AuthorshipPresenter  (autor do nó publicado; reusa PublicProfilePolicy)
   |   hook OOP #[Hook('node_view')] injeta o render array 'author_box' (só em nós article da Revista)
   v
contrato neutro: data-portal-ui="author-card" + props escalares + slots renderizáveis
   |
   v
ACULTA420: PortalUiHooks (TOKENS) e/ou SDC `author-card` (Bootstrap card), escolhido pelo tema
```

- **Presenter:** `AuthorshipPresenter` (serviço DI, sem `\Drupal::` em `src/`). Entrada: o nó. Saída: somente
  `display_name`, `avatar` (render array de imagem, estilo de imagem de avatar do Core), `bio` (texto filtrado, render
  array) e `profile_url` (`Url` absoluta do purpose `main`, via `DomainPurposeManager`, ou ausente).
- **Nome de exibição:** apelido (`field_nickname`), nunca o e-mail. Sem apelido, o nome de usuário do Core.
- **Privacidade (fonte única: `PublicProfilePolicy`):**
  - `profile_url` só se `field_perfil_publico` estiver ligado; sem perfil público, o nome aparece sem link.
  - bio só se `field_exibir_bio` estiver ligado e o perfil for público.
  - foto só se `field_exibir_foto` estiver ligado e o perfil for público; senão, avatar neutro.
  - e-mail, redes, CPF, RG, endereço, WhatsApp, data de nascimento, cidade e UF nunca entram no presenter.
- **Conteúdo não publicado:** a caixa só aparece em nó publicado. Nó não publicado ou de outro tipo não recebe a caixa.
- **Purpose:** a caixa aparece somente no purpose `magazine`. O link para o perfil é construído no purpose `main`, porque
  `/u/<apelido>` só existe lá (decisão 5 e `ADMIN-DOMAIN-POLICY.md`). Nenhum host é fixado no código.
- **Sem componente do Portal referenciando o tema:** o Portal não emite `#component: aculta420:*` nem classes de design.
  O elemento leva `data-portal-ui="author-card"`; o tema traduz em `PortalUiHooks::TOKENS` e, se for reutilizado,
  mapeia as props escalares para um SDC `author-card`. Bio e avatar são slots/render arrays; nome e URL são props simples.
- **Cache e access:** o presenter acumula `CacheableMetadata` com tags `node:<nid>`, `user:<uid>`, `profile:<id>` e o
  contexto `user.permissions` quando a visibilidade depender do papel. A alteração de visibilidade do perfil invalida a
  caixa em todas as publicações da pessoa.
- **Sem storage novo.** Nenhuma tabela, nenhum campo novo no nó.
- **Não-objetivo:** a caixa não mostra contagens, badges nem seguidores. Badges entram só na fase U4, com a regra de não
  aparecer em lugar público quando o perfil for privado.

**Testes exigidos antes de marcar a entrega como concluída:**
- Kernel: matriz de privacidade (perfil público/privado × exibir bio/foto ligado/desligado), conta sem perfil, conta
  bloqueada, nó não publicado, nó de outro tipo e nó de outro purpose.
- Unit: `AuthorshipPresenter` com `PublicProfilePolicy` simulada; nome nunca é e-mail.
- Cache: tags presentes no render array; invalidação ao mudar a visibilidade.
- Gates: `validate-aculta-portal-drupal11.php`, `validate-module-theme-boundary.php`, `validate-public-slugs.php` e
  `validate-environment-addresses.php`.
- Navegador no servidor de testes (`TESTES-NAVEGADOR.md`): caixa visível em artigo da Revista, link só com perfil público.

**Entrega:** fase U2 em commits na branch autorizada. Código, testes, gates e
revisão visual aplicável ficam prontos antes de RC; o Runtime é validado em
Alpha. Marcadores de versão do `aculta_user` (`aculta_user.info.yml` e
`CHANGELOG.md`) acompanham mudança de código conforme a política de versão.

**Pendências dependentes do responsável** (registradas em `ACULTA-USER-DECISOES-E-IMPLEMENTACAO.md`, seção 2):
- tipo de publicação "blog" (hoje a Revista usa `article`, "Notícia");
- mapeamento de "chamada" para `field_subtitle` e de "resumo" para `field_summary` no tipo de publicação da Revista.

### D. Wiki (`wiki_entry`): como a Wikipedia

**Decisão do responsável (2026-10-13):** o verbete não mostra autoria no corpo. A Wiki segue o modelo da Wikipedia.

- **Corpo do verbete:** sem autor, sem "escrito por", sem cartão de autor e sem data de criação. Só conteúdo, categorias e, se houver, data da última revisão.
- **Histórico do verbete é público (decisão do responsável, 2026-10-13):** aba "Histórico" no verbete, como na Wikipedia, com as revisões de todas as pessoas, comparação (`diff`) e nome de exibição de quem revisou.
- **Reversão é do `wiki_editor`.** Nenhum outro papel reverte revisão.
- **Histórico de atividade da pessoa é individual:** a lista de revisões que uma pessoa fez aparece só para ela, na aba Wiki de "Atividade". Outros usuários, inclusive administradores, recebem 403 (inclusive por URL com o identificador da pessoa).
- **Contribuições da pessoa:** aba "Wiki" em "Atividade" (seção H). Lista as revisões feitas por ela, com verbete, data e link para o histórico público do verbete.
- **Verbete não publicado** não aparece em listagem, contagem ou histórico público (`GUARDRAILS.md`, seção Wiki).
- **Discussão do verbete:** ver seção E3 (discussão unificada no Fórum).
- **Verbete não publicado** não aparece em listagem, contagem ou histórico público (`GUARDRAILS.md`, seção Wiki).
- Contagens são derivadas (sobre `node_field_revision` com `revision_uid`). Nenhum contador persistido no Portal.

### E. Fórum (aba "Fórum" em "Atividade")
- Tópicos: nós `forum` com `uid` do usuário. Respostas: `comment_forum` com `uid`.
- Seguir e salvar tópico: flags `seguir_topico` e `salvar_topico` já existentes; a aba lista os tópicos seguidos e salvos.
- Denúncia de comentário (`DT-P13`): Flag do tipo `entity:comment` com fila de moderação via Views. A moderação reusa permissões e o Comment do Core (`GUARDRAILS.md`, seção Fórum). Entra na fase U4.

### E3. Comentários unificados: a discussão mora no Fórum

**Proposta (pedido do responsável, 2026-10-13):** um único sistema de comentários. A discussão completa fica no Fórum. Nas páginas de conteúdo aparece um trecho dela, com link para a discussão completa.

**Como funciona:**

- **Discussão vinculada:** cada verbete, artigo da revista, atividade ou projeto pode ter **um tópico de Fórum vinculado**. O vínculo é uma referência do tópico para o conteúdo (campo de referência no tipo `forum`, a criar). Como na Wikipedia, o verbete tem uma aba "Discussão", que abre esse tópico.
- **Os comentários moram só no tópico.** Não há campo de comentário nos tipos de conteúdo de revista, wiki ou atividade. Um único tipo de comentário (`comment_forum`) e uma única moderação. Não há tabela nem entidade nova.
- **Página do conteúdo:** bloco com os últimos comentários do tópico vinculado (View de comentários filtrada pelo tópico, com limite de quantidade), e o link "Ver discussão completa (N comentários)". O link aponta para o purpose FORUM, obtido por `DomainPurposeManager`, nunca por nome de host.
- **Resposta:** o formulário de resposta fica **só** na discussão completa, no host FORUM. A página de conteúdo não envia POST para outro host (`CROSS-DOMAIN-REQUEST-POLICY.md`).
- **Sem tópico vinculado:** a página do conteúdo não mostra bloco nem link de discussão. Nada de comentário solto no conteúdo.
- **Criação do tópico:** manual, pelo editor do conteúdo ou pelo `wiki_editor`, por ação no próprio conteúdo. Não criar tópico automaticamente para todo conteúdo (a confirmar, decisão 24).
- **Decisão substituída (2026-10-19):** publicações da Revista não criam tópicos públicos. Cada publicação usa Comment diretamente na entidade; os hooks de sincronização automática foram removidos em `aculta_magazine` 0.1.0-dev.10. Os tópicos históricos permanecem preservados durante a transição.
- **Atividade:** a aba Comentários (seção E2) lista os comentários da pessoa nas discussões. Com isso, a decisão 22 fica resolvida: não há comentário em artigo.

**Por que esta forma:**

- Reaproveita o Forum e o Comment do Core já instalados. Não cria storage paralelo (`GUARDRAILS.md`).
- A discussão completa e a denúncia (`DT-P13`) ficam em um lugar só.
- O histórico de contribuição e as flags `seguir_topico` e `salvar_topico` funcionam sem mudança.

**Pontos a verificar no Homelab antes de U2:**

- O Core permite usar o tipo `comment_forum` com um campo de comentário em outro tipo de nó? Não precisa: a proposta usa campo de referência do tópico para o conteúdo, não comentário no conteúdo. Mesmo assim, verificar que o módulo `forum` não reage a comentários de tópicos fora do tipo `forum`.
- View de comentários com filtro pelo tópico, e cache correto (tags do tópico e dos comentários).
- Acesso anônimo de leitura do tópico no host FORUM (`access content` no papel anônimo já existe).
- Pesquisa: a busca em drupal.org não confirmou detalhes de Views sobre comentários de outra entidade. Ver o registro de mudança de comentários como campos (`https://drupal.org/node/2100015`) e a documentação de Views de comentários no Homelab.

#### E3.1 Como os comentários funcionavam no desenho anterior (histórico)

Esta subseção preserva o registro do desenho anterior e sua homologação em 2026-10-14. A decisão foi substituída em
2026-10-19: Revista usa Comment diretamente na publicação, e os hooks que sincronizavam tópicos foram removidos.

- **Onde moram:** só no tópico do Fórum (`comment_forum`). A publicação da Revista não tem campo de comentário. Ela
  mostra a contagem e o link "Ver discussão completa (N comentários)" (`aculta_user` `DiscussionHooks`).
- **Quem escreve:** a resposta fica no tópico, no host do Fórum. A publicação não envia POST para outro host.
- **Autor:** cada comentário guarda o próprio autor (`uid`), e o nome exibido é o dele. Nada disso muda quando a
  publicação troca de tipo (`PublicationTypeConverter`), porque o tópico e seus comentários não são recriados.
  Verificado no runtime em 2026-10-14 com comentário de outro autor, publicado explicitamente, em ida e volta.
- **Moderação:** a do Core. Quem tem `skip comment approval` publica direto. Quem não tem entra na fila do
  `content_editor` (`administer comments`). Um comentário de quem não pode publicar sem aprovação não aparece na
  página até ser aprovado. Isso não é efeito da troca de tipo, nem de publicação.
- **Exclusão da publicação:** no desenho antigo, o tópico era despublicado e comentários permaneciam. No código atual,
  não há despublicação automática de tópico legado; vínculos e conteúdo histórico aguardam o corte seguro autorizado.
- **Troca de tipo (Notícia ↔ blog):** o tópico segue o nó da publicação nova. Comentários, autor e pasta permanecem
  (`docs/README.md`, seção "Trocar o tipo de uma publicação").
- **Login para comentar:** o Core mostra "Efetue login ou registre-se para postar comentários". Nos tópicos do
  Fórum da Revista, a mensagem aparece uma vez, na área de comentários do tópico. Nos links de cada comentário ela
  é removida (`ForumTopicHooks::commentLinksAlter`, `aculta_magazine` 0.1.0-dev.5). Motivo: a repetição era
  visível quando havia comentário.

As regras de moderação descritas nas subseções atuais e em `aculta_forum/docs/CONTEXTS.md` são a referência vigente.

### E4. Verbetes chamados em todo o site, e papel de autor

**Pedido do responsável (2026-10-13):** editores de conteúdo podem chamar verbetes em qualquer parte do site. Exemplo: ao publicar um artigo na revista, o autor chama um verbete da Wiki. Autores do site viram automaticamente editores da Wiki, com as mesmas funções.

**Como chamar um verbete (sem copiar conteúdo):**

- **Campo "Verbetes relacionados"** nos tipos `article`, `activity`, `project` e `document`: referência múltipla para `wiki_entry`, só verbetes publicados aparecem. Renderiza um cartão (título, resumo e link). Nenhuma cópia do texto do verbete no conteúdo; o verbete é sempre a fonte.
- **Link no corpo do texto:** link interno para o verbete, pelo editor de texto. A verificar no Homelab se o CKEditor 5 do Core faz link para nó sem módulo extra (`entity_embed` não entra sem verificação de D11).
- **Link para outro host:** o link ao verbete aponta para o purpose WIKI, obtido por `DomainPurposeManager`. Nenhum host escrito no código.
- **Verbete não publicado** não aparece no cartão, nem no link, nem na contagem.
- **Cache:** o cartão acumula tags do verbete chamado, para atualizar quando o verbete mudar.

**Mão dupla (opcional, a decidir):** no verbete, bloco "Citado em" com os conteúdos publicados que chamam o verbete. É uma View reversa sobre os campos de referência. Só títulos publicados, nunca conteúdo não publicado.

**Papel de autores da revista e da Wiki:** papel único `content_editor` (já existente). Decisão do responsável (2026-10-13): editor de conteúdo e editor de conteúdo são a mesma função, então não há papel separado.

- Concedido **somente pelo administrador** (decisão do responsável, 2026-10-13). Sem papel novo.
- Configuração: `content_editor` recebe `wiki_editor` diretamente no `config/sync`. Não há sincronização automática entre papéis.
- **Risco a registrar:** `content_editor` passa a editar qualquer verbete e publicar, além do que já tem (navegação administrativa, reversão de todas as revisões). Ver decisão 26.
- **Reversão de revisão:** a decisão 21 atribui reversão ao `wiki_editor`, mas o `wiki_editor` não tem `revert wiki_entry revisions` hoje (só `content_editor` tem `revert all revisions`). Precisa ser adicionada à configuração exportada do papel, com `config/sync` e gate.

### E5. Papéis de Wiki e moderação do Fórum

**Decisões do responsável (2026-10-13):**

- `content_editor` recebe **`wiki_editor`**.
- Todo usuário com **e-mail confirmado** (papel `email_confirmed`) recebe **`wiki_contributor`**. As contribuições são moderadas.
- `content_editor` também é **moderador dos fóruns**.

**Moderação da Wiki (já existe no workflow `wiki_editorial`):**

- `wiki_contributor` cria rascunho (`create_new_draft`), envia para revisão (`draft_to_review`) e volta para rascunho (`review_to_draft`). **Não publica.**
- Uma edição de verbete publicado vira nova revisão em rascunho. O texto publicado continua no ar até um editor publicar.
- `wiki_editor` publica (`review_to_published`) e reverte revisões (decisão 21). Permissão de reversão ainda **não está** na configuração do papel: `revert wiki_entry revisions` precisa ser adicionada, com `config/sync` e gate.

**Ponto a corrigir, conflito com a política de e-mail:** a regra da seção 1 de `EMAIL-CONFIRMATION-POLICY.md` diz que quem não confirmou o e-mail não pode editar a Wiki. Por isso `wiki_contributor` é concedido ao papel `email_confirmed`, e não a `authenticated` (decisão 28, respondida em 2026-10-13).

**Moderação do Fórum:** os comentários existem só nas discussões do Fórum (seção E3). Por isso o escopo de moderação de comentários é o próprio Fórum.

- `content_editor` recebe `administer comments` (aprovar, editar e excluir comentários) e `edit any forum content` (editar tópicos de qualquer autor).
- **Não** recebe `administer forums` (estrutura de fóruns e taxonomia), que continua com administrador.
- A permissão `administer comments` é do Core e não é restrita por tipo de conteúdo. Hoje o único tipo com comentário é o Fórum, então o efeito fica restrito ao Fórum. Se um tipo de comentário novo surgir, a permissão passa a valer para ele também: registrar no gate.
- Denúncias de comentário (`DT-P13`, fase U4) vão para a mesma fila de moderação.

### E6. Pré-visualização do verbete, "Citado em" e regras do Fórum

**Decisões do responsável (2026-10-13):**

- Ao passar o mouse (ou focar) em uma referência a verbete, aparece um **cartão de pré-visualização**. O bloco "Citado em" fica nesse cartão.
- Criar tópico de Fórum exige **no mínimo 10 comentários publicados**.
- O **primeiro comentário** de quem não tem comentário publicado vai para a fila de moderação.
- As limitações são **explicadas à pessoa com mensagens** no formulário e na Atividade.

**Cartão de pré-visualização (e "Citado em"):**

- Conteúdo: título, resumo, categoria e "Citado em" (até 5 títulos publicados, com contagem total e link para a lista completa).
- **Acesso por teclado e toque:** o cartão abre também com foco e com toque. Não depende de mouse. A lista completa de "Citado em" fica numa aba do próprio verbete, para que a informação não exista só no cartão.
- **Restrição técnica:** o cartão aparece em páginas de outro host (ex.: artigo da revista chamando verbete da Wiki). O AJAX é de mesma origem e não há CORS (`CROSS-DOMAIN-REQUEST-POLICY.md`). Logo, os dados do cartão são montados **no servidor da página que os exibe**, e apenas exibidos ao passar o mouse. Não há busca ao host Wiki no navegador.
- Dentro do próprio verbete (verbete para verbete, mesmo host WIKI), o cartão pode buscar os dados por AJAX de mesma origem, com fallback de link simples.
- Cache: tags do verbete chamado e das referências publicadas.

**Criação de tópico com 10 comentários:**

- "Comentário" conta apenas comentários **publicados** no Fórum (`comment_forum`, `status` publicado).
- Regra derivada, sem armazenamento novo: contagem por entidade, verificada na criação (`#[Hook('entity_create_access')]` do tipo `forum`). Sem permissão nova por papel.
- Cache: contexto `user` e tags de comentários.
- Mensagem a quem não atinge o limite: "Você pode criar tópicos a partir de 10 comentários publicados. Você tem N." A mesma informação aparece em Atividade, com barra de progresso.

**Primeiro comentário em moderação:**

- Se a pessoa não tem nenhum comentário publicado, o comentário é salvo como **não publicado**, na fila de moderação do Core (`admin/content/comment/approval`, aba "Não aprovados"). Quem modera: `content_editor` (decisão 29) e administrador.
- A regra vale **somente para o primeiro comentário**. Depois da primeira aprovação, os comentários são publicados direto.
- Implementação: presave de comentário no `aculta_user` (`#[Hook]`), com `skip comment approval` como exceção para papéis que não passam pela fila (a confirmar, decisão 32).
- Mensagem no formulário de comentário, antes de enviar: "Seu primeiro comentário passará por moderação antes de aparecer. Depois da aprovação, os próximos são publicados na hora."
- Mensagem após envio: "Seu comentário foi recebido e aguarda moderação."
- Textos em `translations/aculta_user.pt-br.po`, com a mesma formulação em Atividade.

### E7. Regras administráveis, exceções e integração de comentários

**Decisões do responsável (2026-10-13):**

- **Editor de conteúdo** (`content_editor`) não tem restrições editoriais, de comentários nem de outros tipos. Fica fora dos limites de criação de tópico e da moderação do primeiro comentário.
- **Publicação bloqueada até a aprovação:** enquanto o primeiro comentário de uma pessoa estiver na fila, ela não publica outros comentários nem cria tópicos. Quando o primeiro comentário é aprovado, o bloqueio acaba.
- **Opções no painel administrativo do `aculta_portal`** para definir e ligar/desligar esses limites.
- **Comentários da Revista e o fórum de discussão do LMS** devem se integrar ao sistema de Fórum.

**Painel administrativo (`aculta_user`, linkado pelo hub do `aculta_portal`):**

- Caminho: `/painel-administrativo/configuracoes/aculta/portal/participacao` (técnico, sem slug público). Permissão própria `administer aculta participacao`, com `restrict access`.
- Configuração em `aculta_user.settings` (exportável; sem segredo):
  - `topico_limite_ativo` (liga/desliga o limite de criação de tópico);
  - `topico_comentarios_minimos` (padrão 10; número inteiro não negativo);
  - `primeiro_comentario_moderado` (liga/desliga a fila do primeiro comentário);
  - `bloqueio_ate_aprovacao` (liga/desliga o bloqueio de novas publicações enquanto o primeiro está na fila);
  - `papeis_isentos` (lista de papéis; padrão: `content_editor` e `administrator`).
- Mudanças são registradas em log do Drupal (quem alterou e o que mudou, sem valores pessoais).
- Desligar um limite remove a restrição na hora. Sem reconstrução de dados.

**Regras de aplicação:**

- A isenção vale por papel, e não por lista de usuários.
- Se a pessoa perde o papel isento, a regra volta a valer na próxima ação. Nada é gravado no usuário.
- O bloqueio de publicação aparece como mensagem no formulário de comentário e na Atividade: "Seu primeiro comentário aguarda moderação. Novas publicações ficam disponíveis após a aprovação."

**Integração com o Fórum:**

- **Revista:** a discussão de cada artigo é um tópico do Fórum vinculado (seção E3). O artigo mostra o trecho e o link para a discussão completa. Nenhum comentário é gravado no artigo.
- **LMS (decisões 34 e 36, respondidas em 2026-10-13):** a discussão de cada atividade do LMS é um **comentário no Fórum**, como em qualquer LMS. Cada curso e cada atividade têm um tópico do Fórum vinculado. Os tópicos são criados por quem tem autorização para criar cursos (decisão 38, respondida em 2026-10-13), e não por contagem de comentários. Os comentários da atividade são comentários comuns do Fórum. O repositório hoje não tem essa discussão: o tipo de atividade configurado é só `no_answer` e `true_false` (`lms.lms_activity_type.*`), e não há campo de comentário em curso ou lição. Antes de implementar, verificar no Homelab o que o módulo `lms` 1.2.3 oferece para a atividade.
- **Matriculados (decisão 36, respondida em 2026-10-13):** quem está matriculado no curso fica **isento da moderação do primeiro comentário** e tem **aprovação automática** nas discussões do LMS. A matrícula é a associação ao grupo do curso (`group.role.lms_course-member`), e não um papel Drupal novo. A isenção vale nas discussões do curso. **Ao comentar no curso, a isenção vale em todo o Fórum** (decisão 37, respondida em 2026-10-13).
- **Regra comum:** qualquer conteúdo com discussão usa tópico do Fórum. Nenhum tipo de conteúdo guarda comentário próprio.

### E2. Atividade centralizada em "Atividade"

**Decisão do responsável (2026-10-13):** Wiki, Fórum, Revista, comentários e outras formas de participação ficam em **uma seção** da Minha conta, dividida em abas.

- **Seção:** `/atividade` com abas em URL própria, no mesmo padrão de "Segurança" (`/seguranca` e `/seguranca/senha`). Sem JavaScript para trocar de aba: cada aba é um link, com o item atual marcado.
- **Abas propostas** (slugs a confirmar, seção 7):
  - `/atividade/wiki`: revisões feitas por ela (seção D);
  - `/atividade/forum`: tópicos, respostas, seguidos e salvos (seção E);
  - `/atividade/revista`: artigos da revista em que ela é autora (seção C);
  - `/atividade/comentarios`: comentários feitos pela pessoa nas discussões do Fórum (seção E3), com link para a discussão;
  - `/atividade/conquistas`: badges (seção G).
- **Menu lateral:** um único item "Atividade" (ícone Bootstrap Icons, a escolher), depois de "Cursos". Substitui os itens separados previstos para Wiki e Fórum.
- **Mobile:** o conteúdo da seção entra na sanfona já existente. As abas aparecem como barra horizontal rolável, sem JavaScript.
- **Vazio:** cada aba sem conteúdo mostra mensagem curta e link para a área correspondente (verbetes, Fórum, revista).
- **Controle de acesso:** cada aba só lista o que a própria pessoa fez e o que ainda está publicado. Ninguém vê a atividade de outra pessoa por esta seção. O administrador vê **somente a própria atividade** (decisão 23, respondida em 2026-10-13). A visão pública fica no perfil (seção B), e só com perfil público, e sem o histórico de revisões.
- **Organização no código:** rotas e controller dentro do `aculta_user` (Views de aba, sem entidade nova); o `AccountShellBuilder` do Portal só ganha o item de menu.

### F. Funções sociais
- **Seguir pessoa:** Flag sobre entidade `user`, nome `seguir_pessoa`. A confirmar que Flag 5.1.1 aceita `entity:user` (verificação no Homelab). Se não aceitar, a alternativa é um campo de referência em uma entidade de vínculo, o que cria storage e exige decisão.
- **Seguidores e seguindo:** contagens e listas por Views (`/seguindo`, `/seguidores`). Lista de seguidores de outras pessoas é privada por padrão.
- **Feed:** View de atividades (tópicos, respostas, verbetes publicados) das pessoas seguidas. Sem contrib de atividade.
- **Notificações:** somente opt-in. Envio por camada de envio E-Mails ACULTA, com fila do Core (`queue`), uma mensagem por evento de lote. Nenhum envio sem consentimento, e nada de PII no corpo além do necessário.

### G. Gamificação: badges, sem ranking

**Decisão do responsável (2026-10-13):** sem ranking. Só badges, de dois tipos:

- **Automáticas:** concedidas por regra sobre dados reais (verbetes publicados, revisões aceitas, respostas no Fórum, tempo de participação). Definição da regra em configuração do módulo.
- **Manuais:** atribuídas pelo administrador a uma pessoa, com motivo registrado.

Regras:

- **Sem ranking público**, sem comparação entre pessoas e sem pontuação.
- **Sem métricas de doação, compra ou pagamento.** Mercado Pago e apoio não entram em regra de badge.
- **Armazenamento (muda o plano anterior):** a regra "sem storage novo" não se aplica a concessões. Uma badge concedida é fato histórico. Se fosse derivada, sumiria quando a regra mudasse ou quando o conteúdo fosse removido, e não há como registrar quem concedeu uma badge manual. Por isso, proposta: entidade de conteúdo própria `aculta_badge_award` (pessoa, badge, origem automática ou manual, quem concedeu, data, motivo). Justificativa: Core e contrib não oferecem concessão auditável com esse vínculo. Definições de badge são configuração (`config`).
- **Revogação:** só o administrador revoga badge manual, e a revogação fica registrada, sem apagar a linha.
- **Exibição:** badges aparecem no cartão público do autor (somente se o perfil for público, decisão 2) e na Minha conta. Badge sem perfil público não aparece em nenhum lugar público.
- **Avaliação das regras automáticas:** em lote (cron), não a cada salvamento de conteúdo.

### G2. Associado (tipo de conta) e dados de cadastro

**Decisão do responsável (2026-10-13):** existe o tipo de conta **Associado**. Associado é associado e tem acesso a algumas ferramentas. Os dados de associado geram relatório para o administrador.

**Papel (role), sem sobrepor os papéis do Drupal:**

- Proposta: role Drupal `associado`, somada a `authenticated` e `email_confirmed`. É o mecanismo nativo de permissão do Drupal; cada ferramenta do associado é uma permissão concedida a esse papel. Não cria um sistema paralelo de permissão.
- O papel não recebe permissão administrativa nem de conteúdo. Não altera permissões de `wiki_editor`, `lms_teacher` ou `content_editor`.
- Quem vira associado é decidido pelo administrador. Autocadastro como associado: não, a não ser que o responsável diga o contrário.
- Pergunta em aberto: "tipo de conta" é só o papel, ou também um estado (ativo, suspenso, desligado) com data de adesão? Proposta: papel `associado` mais campos de adesão no perfil (data de adesão e situação). Quem tira o papel mantém o histórico.

**Dados (aba "Outros Dados" em Minha conta):**

- **Novos campos:** RG, órgão emissor, profissão, data de nascimento.
- **RG é dado sensível de identificação.** Deve seguir o mesmo padrão do CPF/CNPJ: cifrado e com índice HMAC pelo submódulo `aculta_id_cpf`, chaves por Drupal Key (provider `env`). Não gravar em claro. Só o administrador vê o valor completo, e a revelação fica registrada (padrão do painel de segredos).
- **Profissão e data de nascimento:** campos comuns do perfil `participante` (texto e datetime). Data de nascimento entra no relatório, mas não é pública.
- **Não duplicar:** e-mail, nome e endereço vêm dos campos que já existem (conta, perfil participante, Endereço único). Nada de endereço novo (regra permanente de endereço único).
- **Já existente:** a aba "Outros Dados" hoje guarda CPF/CNPJ cifrado (`AccountOtherDataForm`, `aculta_id_cpf`); os campos PF/PJ ainda são manuais no Portal. RG entra junto desse fluxo, não em outro.

**Relatório do administrador:**

- View administrativa de associados (papel `associado`): nome, e-mail, endereço, profissão, data de nascimento, data de adesão, situação, badges.
- RG aparece mascarado no relatório; o valor completo só por revelação registrada.
- Exportação CSV pelo Views Data Export **só se** o módulo for verificado no Homelab (contrib não confirmado). Alternativa: exportação própria pelo Views (CSV via Views do Core) sem contrib.
- Acesso: permissão específica de relatório de associados, restrita ao administrador. Sem cache público; contexto `user.permissions`.
- Dados pessoais no relatório: LGPD, finalidade declarada, e acesso só a quem tem a permissão.

### H. Menções (`@apelido`)
- Adiado (U5). Depende de verificação de contrib D11 ou de filtro de texto próprio. Sem essa verificação, não entra.

### I. Privacidade e LGPD
- Visibilidade pública opt-in por pessoa, com campo e texto de consentimento.
- Exportação e eliminação: fluxos do Core (`user_cancel`, reatribuição de conteúdo). Antes de U1, mapear o que o perfil e a foto guardam, e como o cancelamento trata verbetes e tópicos.
- Nenhum dado pessoal em logs, URLs ou Views públicas.
- Atualizar `docs/portal/` com a política aprovada pelo responsável.

## 6. Fases propostas

Cada fase termina em commits na branch autorizada; PR só quando solicitada. Os
critérios de código, testes, gates e revisão visual aplicável ficam prontos
antes de RC. Código ainda não implantado registra Runtime como `DEFERRED`; a
validação do servidor de desenvolvimento ocorre em Alpha.

| Fase | Entrega | Critério de conclusão |
| --- | --- | --- |
| **U0** | Decisões da seção 7 e verificação no Homelab: `composer show` e `dry-run` dos candidatos; checagem de `flag` com `entity:user`; checagem de `pathauto` com perfil. | Respostas registradas no `DEBT-REGISTER` ou neste documento. Nenhum código. |
| **U1** | Submódulo `aculta_user` (versão `0.1.0-dev.1`). Campos `field_bio` e `field_visibilidade_publica`; apelido único em `field_nickname`. `PublicProfilePolicy` e `PublicProfilePresenter`. `/u/<apelido>` (fallback sem perfil público). Avatar com fallback. | Testes de Kernel para privacidade e acesso; Unit dos presenters; gates do Portal, fronteira, slugs e endereços passam. |
| **U2** | Seção "Atividade" com abas Wiki, Fórum, Revista e Comentários (seção E2); histórico público do verbete e aba Discussão vinculada ao Fórum (seções D e E3); cartão de autor e listagem na revista (seção C). Só Views e leitura. | Verbete sem autoria no corpo (teste de conteúdo); histórico com permissão de reversão; aba sem dados de outra pessoa; conteúdo não publicado não aparece; teste de navegador em `TESTES-NAVEGADOR.md`. |
| **U3** | Seguir pessoas (Flag), seguidores e feed (Views), notificações opt-in por fila. | Consentimento validado; envio de e-mail só com opt-in; sem listas de seguidores públicas. |
| **A1** | Papel `associado` (sem permissão administrativa), campos de adesão, RG cifrado (padrão `aculta_id_cpf`), profissão e data de nascimento em "Outros Dados", relatório administrativo de associados. | RG nunca em claro (teste de gravação e de leitura); relatório restrito por permissão; teste de acesso negado para comum e para `wiki_editor`. |
| **U4** | Badges (automáticas e manuais, entidade `aculta_badge_award`), denúncia de comentário (DT-P13) com fila de moderação. | Sem ranking; concessão manual com autor e motivo; revogação registrada; badges sem PII. |
| **U5** (condicional) | Menções `@apelido`. | Só se a verificação de contrib D11 aprovar. |

Versionamento (`web/themes/custom/aculta420/docs/versioning.md`): U1 é **MINOR** no Portal (submódulo novo). Linha alvo sugerida: `0.4.0-dev.N`, com o submódulo em `0.1.0-dev.N`. Marcadores coerentes no mesmo PR: `aculta_portal.info.yml`, `CHANGELOG.md` do Portal e do submódulo, `ROADMAP.md`. Nenhuma tag sem pedido do responsável.

Gates por fase: `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-module-theme-boundary.php`, `php scripts/validate-public-slugs.php`, `php scripts/validate-environment-addresses.php`, e os testes do submódulo.

## 7. Decisões para o responsável

1. **Prioridade:** iniciar U0 agora, ou esperar S2–S4? (Roadmap: features depois do saneamento.)
2. **Visibilidade:** respondida nesta sessão: o apelido é público por padrão. Cidade e UF nunca são públicas. Foto, bio, badges, e-mail e redes sociais podem ser exibidos pela pessoa ou ficar privados.
3. **Slug do autor:** respondida em 2026-10-13: o apelido é o caminho público, `/u/<apelido>`, único.
4. **Autor convidado** (sem conta) na revista: aceitar com campo de assinatura de texto, ou só autores com conta?
5. **Purpose das páginas:** respondida nesta sessão: a rota `/u/{apelido}` é registrada e serve somente no purpose `main`; em outro purpose responde 404. Participação (Atividade, Fórum) continua em aberto.
6. **Gamificação:** respondida em 2026-10-13: badges automáticas e manuais, sem ranking. Ver seção G e a decisão 18.
7. **Gravatar:** confirma a recomendação de não usar?
8. **Notificações:** opt-in por tipo de evento? Frequência máxima?
9. **Menções:** entram no escopo ou ficam para depois?
10. **Verificação de contrib:** autoriza verificar no Homelab (`composer dry-run`) os candidatos da seção 3 antes de U1?
11. **Acervo de avatares:** recuperar os 10 arquivos de cogumelos (`mushrooms/`) ou remover essa família do `avatars.json`?
12. **Persistência do avatar sorteado:** copiar o arquivo do acervo para `user_picture` (arquivo gerenciado com origem marcada) ou guardar a escolha em `user.data`?
13. **OAuth:** importar a foto do Google quando houver, ou sempre usar o acervo?
14. **Associado:** papel `associado` basta, ou também há estado (ativo, suspenso, desligado) com data de adesão? Quem tira o papel mantém o histórico?
15. **Adesão:** só o administrador torna alguém associado, ou haverá fluxo de pedido de adesão?
16. **RG:** confirma cifragem igual ao CPF/CNPJ (chave em Drupal Key, índice HMAC, revelação registrada)?
17. **Relatório:** quais colunas o administrador vê? Pode exportar CSV? Quem além do administrador tem acesso?
18. **Badges manuais:** quem pode conceder (só o administrador, ou também editores de Wiki e de Fórum)? Badge pode ser revogada?
19. **Badges automáticas:** quais regras entram na primeira versão? (Sugestão: verbetes publicados, respostas no Fórum, tempo de participação.)
20. **Slugs das abas:** confirma `/atividade/{wiki,forum,revista,comentarios,conquistas}` e o nome do item de menu "Atividade"?
21. **Histórico:** respondida em 2026-10-13: o histórico do verbete é público; a reversão é do `wiki_editor`; o histórico de atividade de cada pessoa é individual.
22. **Comentários:** respondida em 2026-10-13: comentários moram só nas discussões do Fórum; o conteúdo mostra trecho com link para a discussão completa (seção E3).
23. **Administrador:** respondida em 2026-10-13: o administrador vê só a própria atividade.
24. **Tópico de discussão:** respondida em 2026-10-13: criação de tópico exige 10 comentários publicados (seção E6). Pergunta restante: quais tipos de conteúdo recebem discussão vinculada, e o editor pode criar a discussão do próprio conteúdo sem os 10 comentários?
25. **Papel de autores:** respondida em 2026-10-13: quem concede é o administrador.
26. **Função na Wiki:** respondida em 2026-10-13: `content_editor` recebe `wiki_editor`.
27. **Citado em:** respondida em 2026-10-13: aparece no cartão de pré-visualização do verbete (seção E6), com a lista completa em aba do verbete.
28. **Wiki para todo autenticado:** respondida em 2026-10-13: `wiki_contributor` é concedido a quem confirmou o e-mail (`email_confirmed`).
29. **Moderação do Fórum:** respondida em 2026-10-13 (confirmação): `administer comments` e `edit any forum content` para `content_editor`; `administer forums` fica com o administrador.
30. **Editor sem limite:** respondida em 2026-10-13: o editor de conteúdo não tem restrições de comentário nem editoriais (seção E7).
31. **Primeiro comentário:** respondida em 2026-10-13: a publicação fica bloqueada até a aprovação do primeiro comentário (seção E7).
32. **Quem fica fora da fila:** respondida em 2026-10-13: `content_editor` e `administrator` ficam isentos (padrão em `papeis_isentos`).
33. **Editor de conteúdo e contribuidor editorial:** respondida em 2026-10-13: são a mesma função, no papel `content_editor`.
34. **Discussão do LMS:** respondida em 2026-10-13: a discussão de cada atividade do LMS é um comentário no Fórum; cada curso e cada atividade têm tópico vinculado (seção E7).
35. **Valores iniciais:** respondida em 2026-10-13: 10 comentários como limite padrão, alterável no painel administrativo do `aculta_portal` (seção E7).
36. **Matriculados:** respondida em 2026-10-13: matriculados no curso ficam isentos da moderação do primeiro comentário e têm aprovação automática nas discussões do LMS.
37. **Escopo da isenção dos matriculados:** respondida em 2026-10-13: vale nas discussões do curso; ao comentar no curso, a isenção vale em todo o Fórum.
38. **Criação dos tópicos do LMS:** respondida em 2026-10-13: os tópicos de curso e de atividade são criados por quem tem autorização para criar cursos.
39. **Consentimento do nome em históricos:** o nome de exibição de quem revisou verbete aparece no histórico público mesmo com perfil privado? Proposta: sim, porque é a regra da Wiki (histórico público); a pessoa é informada ao criar a conta.
40. **Perfil privado acessado por URL:** 404 (não revela que o perfil existe) ou 403? Proposta: 404.
41. **Caminho do perfil:** respondida em 2026-10-13: `/u/<apelido>` é confirmado como exceção registrada em `FRIENDLY-PORTUGUESE-SLUGS.md`.
42. **Apelido no cadastro:** o apelido é pedido no cadastro, ou gerado e trocado depois em Configurações? Proposta: gerado no cadastro e trocável depois.
43. **Contas existentes:** respondida nesta sessão de forma parcial: apelidos provisórios `pessoa-1` e `pessoa-5` foram criados no Runtime para as duas contas sem apelido (backup prévio em `scratchpad`). Não há update hook versionado para contas futuras.
44. **Palavras reservadas:** a lista proposta basta, ou há outras rotas a reservar?

## 8. Riscos e limites

- Compatibilidade D11 de todos os candidatos novos: **não confirmada** nesta sessão (bloqueio de egress em `drupal.org`).
- Core 11.4.8 e contrib estão instalados no Homelab. As APIs citadas foram verificadas por leitura de código em U0 (`docs/portal/ACULTA-USER-U0-VERIFICACAO.md`). Continuam **não confirmadas**: a unicidade de alias gerado pelo Pathauto e a geração real de alias no Runtime; ambas exigem teste em U1.
- Revisão de U0 corrigiu premissas deste plano: `revision_uid` fica em `node_revision`, não em `node_field_revision`; `skip comment approval` não distingue o primeiro comentário; `lms_activity` não tem campo de comentário.
- O módulo `redirect` pode criar redirect automático quando o Pathauto troca um alias. Isso conflita com a regra de slug sem 301. Precisa ser desligado para os aliases de autor.
- Dados pessoais e LGPD: a ativação de perfil público e de notificações depende de decisão do responsável e de revisão de texto de consentimento.
- Gamificação mal desenhada pode expor participação e apoio. O risco está em métricas, não só na interface.
- Não há Runtime validado para nenhum item deste plano.

## 9. Implementação autorizada e bloqueio atual

**Decisão do responsável (2026-10-13):** funcionalidades que ainda não existem serão implementadas. Inclui as seções E2 a E7, A1, G2 e o cartão de pré-visualização do verbete.

**Status do bloqueio (atualizado após U0):** o bloqueio de 2026-10-13 não existe mais. O Homelab tem `composer install` concluído, Core 11.4.8 e Drush respondendo, com acesso ao banco `aculta_runtime`. Nenhum item deste plano foi validado no Runtime.

**Status da ordem de implementação** (itens em commits na branch autorizada;
Runtime `DEFERRED` até deploy/validação em Alpha):

1. **U0 (no Homelab): concluído** no branch `aculta-user-u0` (commit `6d11bb2`, publicado, sem PR). Relatório: `docs/portal/ACULTA-USER-U0-VERIFICACAO.md`. Parada registrada: `lms_activity` não tem discussão nem comentário.
2. **Configuração de papéis:** **parcial**, no branch `aculta-user-papeis` (commit `0fc367a`, `RUNTIME STATUS: DEFERRED`). Feito: `content_editor` recebe as permissões de `wiki_editor`, `administer comments` e `edit any forum content` (sem `administer forums`); `wiki_editor` recebe `revert wiki_entry revisions`; gate `scripts/validate-aculta-user-roles.php`. **Pendente:** `wiki_contributor` para `email_confirmed`. Drupal não mapeia papel para papel; a forma de conceder (hook de atribuição ou outra) é decisão do responsável.
3. **Participação e Fórum:** tópico vinculado (E3), limite de 10 comentários e fila do primeiro comentário com painel administrativo (E7), e cartão de pré-visualização (E6).
4. **Atividade:** seção com abas (E2), histórico individual e progresso de limites.
5. **Perfil, avatar e associado:** perfil público, acervo de avatares (com a família `mushrooms` resolvida), papel `associado`, campos de "Outros Dados" (A1).
6. **Badges:** automáticas e manuais, com `aculta_badge_award` (G).
7. **Discussão do LMS:** depois de confirmar o módulo `lms` no Homelab (decisão 34), com a isenção dos matriculados (decisão 36).

**Padrões que valem para cada PR:** `#[Hook]` OOP, injeção de dependência, sem `\Drupal::` em `src/`, marcação `data-portal-ui` sem classes de design, textos em `translations/*.pt-br.po`, gates do Portal passando, e atualização de `CHANGELOG.md` e da versão.

**Decisões 33 a 35 respondidas em 2026-10-13:** papel único `content_editor`; discussão do LMS por curso e por atividade, com comentário no Fórum; matriculados isentos da primeira moderação (decisão 36); 10 comentários como padrão, alterável no painel administrativo.
45. **Skip CAPTCHA:** respondida nesta sessão: manter `skip CAPTCHA` só em `email_confirmed`; `authenticated` não muda. Wiki: `wiki_contributor` segue `email_confirmed` em todos os casos (decisão 28).

## 10. Decisões de cadastro, avatar e associado (2026-10-12)

- **Nome de usuário vira apelido** no cadastro; a pessoa troca depois. Conta OAuth usa a parte local do e-mail, sem o
  domínio (ex.: `fulano.silva@gmail.com` -> `fulano.silva`).
- **Família `mushrooms` removida** do acervo (decisão 11).
- **Arquivo do avatar vai para `user_picture`** (decisão 12). O sorteio só ocorre sem foto.
- **Foto do Google importada quando houver** (decisão 13): já é feito pelo `social_auth`.
- **Só o administrador torna uma pessoa associada** (decisão 15). Papel `associado`, sem permissões (decisão 14 não tratada: sem estado nem data de adesão).
- **RG não é cifrado** (decisão 16). CPF/CNPJ continua no campo único existente.
- **Só o administrador exporta** o relatório (decisão 17).
- **Fotos antigas enviadas pela pessoa são apagadas** ao trocar a foto, quando não há outra referência. Acervo nunca é apagado.
- **Troféus (badges) são concedidos e revogados só pelo administrador** (decisão 18, 2026-10-12).
- **Sortear outro avatar** fica no popup de foto, com AJAX que troca só a imagem (2026-10-12).

