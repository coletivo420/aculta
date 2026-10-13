# Plano do submódulo `aculta_user`

Status: **proposta para decisão do responsável**. Nenhum código foi escrito. Data: 2026-10-13.
Base: `main` em `ce0941a`. Versão atual do Portal: `0.3.0-dev.28`.

Escopo: avatar, perfil público e autoria, integração com Wiki, Fórum e revista, funções sociais e gamificação, dentro de `web/modules/custom/aculta_portal/modules/aculta_user/`.

## 1. Estado atual (o que já existe)

| Capacidade | Onde está | Situação |
| --- | --- | --- |
| Foto de perfil | Core `user_picture` (`config/sync/field.field.user.user.user_picture.yml`), estilo `aculta_avatar`, crop (`crop`, `image_widget_crop`) | Existe. Editor de foto na Minha conta (`PortalController`). Sem avatar padrão nem fallback público. |
| Apelido e dados do participante | Profile `participante` com `field_nickname`, nome, cidade, UF e WhatsApp (`config/sync/field.field.profile.participante.*`) | Existe. Não é público. `field_nickname` não é único nem é slug. |
| Conexões sociais | `social_auth` e `social_auth_google`; rota `/conexoes` | Existe. |
| Fórum | `forum` 1.1.3, `flag` 5.1.1, flags `seguir_topico` e `salvar_topico` (`0.2.0-dev.57`) | Adotado. Sem página "Minha participação". |
| Wiki | Nó `wiki_entry`, papéis `wiki_contributor` e `wiki_editor`, workflow `wiki_editorial`, módulo `diff` | Existe. Sem "Minhas contribuições". |
| Revista | Nós `article` (Notícia), `document`, `activity`, `project`; autor = `node.uid` | Autor vem do Core, sem página de autor. Rota `/autores/<nome>` só está nos slugs candidatos (`FRIENDLY-PORTUGUESE-SLUGS.md`). |
| Notificações | Symfony Mailer (`symfony_mailer`, Mailer Plus) | Transporte pronto. Sem assinaturas. |
| Gamificação, seguir pessoas, menções, denúncia | Não existem | `DT-P13`: denúncia de comentários pendente. |
| Anti-abuso | `flood_control`, Turnstile, `username_enumeration_prevention` | Existe. |

Rotas planejadas e ainda não existentes: `/minha-participacao`, `/contribuicoes`, `/meus-cursos` (existe), `/conexoes` (existe).

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
| Notificações de comentários | `comment_notify`, `scn` | `scn` cita suporte a D11 e manutenção mínima; `comment_notify` sem D11 confirmado. | Não adotar sem verificação. Preferir Mailer Plus + fila do Core. |
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
- Página `/autores/<slug>` com foto, nome público (apelido), resumo curto, papel na associação (quando houver) e listas de contribuições.
- Campos novos no perfil `participante`: `field_bio` (texto curto) e `field_visibilidade_publica` (booleano, padrão falso). Slug único em `field_slug`, gerado por Pathauto (se o `pathauto` aceitar o token de perfil; a confirmar) ou preenchido na validação.
- **Nunca público:** e-mail, CPF, endereço, WhatsApp, nome completo, cidade e UF (decisão 2).
- Alteração de slug troca o caminho e o antigo responde 404, sem 301 (`FRIENDLY-PORTUGUESE-SLUGS.md`). Por isso, o módulo `redirect` precisa ficar **fora** da criação automática de redirect quando o Pathauto mudar um alias.

### C. Autoria da revista (MAGAZINE)
- Autor = `node.uid`, do Core. Não criar campo "autor" paralelo.
- Cartão de autor no fim do artigo (`article`), com link para `/autores/<slug>` quando o perfil for público; sem link quando não for.
- Listagem por autor: View com filtro de `uid` e status publicado, no purpose MAGAZINE.
- Autor convidado sem conta: **não** criar entidade própria. Opção mínima: campo de texto de assinatura no nó (decisão 4).

### D. Wiki (`wiki_entry`): como a Wikipedia

**Decisão do responsável (2026-10-13):** o verbete não mostra autoria no corpo. A Wiki segue o modelo da Wikipedia.

- **Corpo do verbete:** sem autor, sem "escrito por", sem cartão de autor e sem data de criação. Só conteúdo, categorias e, se houver, data da última revisão.
- **Histórico do verbete:** aba "Histórico" no verbete, com as revisões (data, quem revisou, resumo da alteração), comparação entre revisões (módulo `diff`) e reversão somente para quem tem permissão de editor. Rotas com slug em português (`/verbete/<titulo>/historico`), sem rota técnica pública de revisão. A rota interna de revisão do Core fica sem link público (`FRIENDLY-PORTUGUESE-SLUGS.md`).
- **Contribuições da pessoa:** aba "Wiki" em "Minha participação" (seção H). Lista as revisões feitas por ela, com verbete, data e link para o histórico. Só revisões de verbetes publicados aparecem na visão pública do autor, se houver.
- **Privacidade:** o histórico mostra o nome de exibição de quem revisou. Quem não tem perfil público aparece como nome de exibição, sem link para perfil.
- **Verbete não publicado** não aparece em listagem, contagem ou histórico público (`GUARDRAILS.md`, seção Wiki).
- Contagens são derivadas (sobre `node_field_revision` com `revision_uid`). Nenhum contador persistido no Portal.

### E. Fórum (aba "Fórum" em "Minha participação")
- Tópicos: nós `forum` com `uid` do usuário. Respostas: `comment_forum` com `uid`.
- Seguir e salvar tópico: flags `seguir_topico` e `salvar_topico` já existentes; a aba lista os tópicos seguidos e salvos.
- Denúncia de comentário (`DT-P13`): Flag do tipo `entity:comment` com fila de moderação via Views. A moderação reusa permissões e o Comment do Core (`GUARDRAILS.md`, seção Fórum). Entra na fase U4.

### E2. Participação centralizada em "Minha participação"

**Decisão do responsável (2026-10-13):** Wiki, Fórum, Revista, comentários e outras formas de participação ficam em **uma seção** da Minha conta, dividida em abas.

- **Seção:** `/minha-participacao` com abas em URL própria, no mesmo padrão de "Segurança" (`/seguranca` e `/seguranca/senha`). Sem JavaScript para trocar de aba: cada aba é um link, com o item atual marcado.
- **Abas propostas** (slugs a confirmar, seção 7):
  - `/minha-participacao/wiki`: revisões feitas por ela (seção D);
  - `/minha-participacao/forum`: tópicos, respostas, seguidos e salvos (seção E);
  - `/minha-participacao/revista`: artigos da revista em que ela é autora (seção C);
  - `/minha-participacao/comentarios`: comentários em todos os tipos de conteúdo com comentário ativo, com link para o conteúdo;
  - `/minha-participacao/conquistas`: badges (seção G).
- **Menu lateral:** um único item "Participação" (ícone Bootstrap Icons, a escolher), depois de "Cursos". Substitui os itens separados previstos para Wiki e Fórum.
- **Mobile:** o conteúdo da seção entra na sanfona já existente. As abas aparecem como barra horizontal rolável, sem JavaScript.
- **Vazio:** cada aba sem conteúdo mostra mensagem curta e link para a área correspondente (verbetes, Fórum, revista).
- **Controle de acesso:** cada aba só lista o que a própria pessoa fez e o que ainda está publicado. Ninguém vê participação de outra pessoa por esta seção; a visão pública fica no perfil (seção B), e só com perfil público.
- **Organização no código:** rotas e controller dentro do `aculta_user` (Views de aba, sem entidade nova); o `AccountShellBuilder` do Portal só ganha o item de menu.

### F. Funções sociais
- **Seguir pessoa:** Flag sobre entidade `user`, nome `seguir_pessoa`. A confirmar que Flag 5.1.1 aceita `entity:user` (verificação no Homelab). Se não aceitar, a alternativa é um campo de referência em uma entidade de vínculo, o que cria storage e exige decisão.
- **Seguidores e seguindo:** contagens e listas por Views (`/seguindo`, `/seguidores`). Lista de seguidores de outras pessoas é privada por padrão.
- **Feed:** View de atividades (tópicos, respostas, verbetes publicados) das pessoas seguidas. Sem contrib de atividade.
- **Notificações:** somente opt-in. Envio por Mailer Plus, com fila do Core (`queue`), uma mensagem por evento de lote. Nenhum envio sem consentimento, e nada de PII no corpo além do necessário.

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

Cada fase tem PR próprio (`AGENTS.md`, regra de encerramento). Código sem Runtime fica com `RUNTIME STATUS: DEFERRED`.

| Fase | Entrega | Critério de conclusão |
| --- | --- | --- |
| **U0** | Decisões da seção 7 e verificação no Homelab: `composer show` e `dry-run` dos candidatos; checagem de `flag` com `entity:user`; checagem de `pathauto` com perfil. | Respostas registradas no `DEBT-REGISTER` ou neste documento. Nenhum código. |
| **U1** | Submódulo `aculta_user` (versão `0.1.0-dev.1`). Campos `field_bio`, `field_visibilidade_publica`, `field_slug`. `PublicProfilePolicy` e `PublicProfilePresenter`. `/autores/<slug>` (fallback sem perfil público). Avatar com fallback. | Testes de Kernel para privacidade e acesso; Unit dos presenters; gates do Portal, fronteira, slugs e endereços passam. |
| **U2** | Seção "Minha participação" com abas Wiki, Fórum, Revista e Comentários (seção E2); histórico do verbete como na Wikipedia (seção D); cartão de autor e listagem na revista (seção C). Só Views e leitura. | Verbete sem autoria no corpo (teste de conteúdo); histórico com permissão de reversão; aba sem dados de outra pessoa; conteúdo não publicado não aparece; teste de navegador em `TESTES-NAVEGADOR.md`. |
| **U3** | Seguir pessoas (Flag), seguidores e feed (Views), notificações opt-in por fila. | Consentimento validado; envio de e-mail só com opt-in; sem listas de seguidores públicas. |
| **A1** | Papel `associado` (sem permissão administrativa), campos de adesão, RG cifrado (padrão `aculta_id_cpf`), profissão e data de nascimento em "Outros Dados", relatório administrativo de associados. | RG nunca em claro (teste de gravação e de leitura); relatório restrito por permissão; teste de acesso negado para comum e para `wiki_editor`. |
| **U4** | Badges (automáticas e manuais, entidade `aculta_badge_award`), denúncia de comentário (DT-P13) com fila de moderação. | Sem ranking; concessão manual com autor e motivo; revogação registrada; badges sem PII. |
| **U5** (condicional) | Menções `@apelido`. | Só se a verificação de contrib D11 aprovar. |

Versionamento (`web/themes/custom/aculta420/docs/versioning.md`): U1 é **MINOR** no Portal (submódulo novo). Linha alvo sugerida: `0.4.0-dev.N`, com o submódulo em `0.1.0-dev.N`. Marcadores coerentes no mesmo PR: `aculta_portal.info.yml`, `CHANGELOG.md` do Portal e do submódulo, `ROADMAP.md`. Nenhuma tag sem pedido do responsável.

Gates por fase: `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-module-theme-boundary.php`, `php scripts/validate-public-slugs.php`, `php scripts/validate-environment-addresses.php`, e os testes do submódulo.

## 7. Decisões para o responsável

1. **Prioridade:** iniciar U0 agora, ou esperar S2–S4? (Roadmap: features depois do saneamento.)
2. **Visibilidade:** perfil público opt-in? Campos públicos aprovados: apelido, foto, resumo, papel na associação? Cidade e UF públicas: sim ou não?
3. **Slug do autor:** `field_slug` único gerado do apelido, ou o apelido atual com validação de unicidade?
4. **Autor convidado** (sem conta) na revista: aceitar com campo de assinatura de texto, ou só autores com conta?
5. **Purpose das páginas:** `/autores` no MAGAZINE? Perfil e participação ficam no MAIN, na ACCOUNT, ou em outro purpose? (`ADMIN-DOMAIN-POLICY.md`.)
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
20. **Slugs das abas:** confirma `/minha-participacao/{wiki,forum,revista,comentarios,conquistas}` e o nome do item de menu "Participação"?
21. **Histórico da Wiki:** quem pode ver o histórico completo (público, ou só usuários logados)? Quem pode reverter revisão (só `wiki_editor`)?
22. **Comentários:** a aba inclui comentários de artigos da revista, se os tipos de conteúdo tiverem comentário ativo? Hoje só o tipo de fórum tem comentário configurado.

## 8. Riscos e limites

- Compatibilidade D11 de todos os candidatos novos: **não confirmada** nesta sessão (bloqueio de egress em `drupal.org`).
- Código do Core e do contrib não está presente neste workspace; APIs citadas aqui (Flag `entity:user`, Pathauto para perfil, Views de `node_field_revision`) devem ser confirmadas no Homelab em U0.
- O módulo `redirect` pode criar redirect automático quando o Pathauto troca um alias. Isso conflita com a regra de slug sem 301. Precisa ser desligado para os aliases de autor.
- Dados pessoais e LGPD: a ativação de perfil público e de notificações depende de decisão do responsável e de revisão de texto de consentimento.
- Gamificação mal desenhada pode expor participação e apoio. O risco está em métricas, não só na interface.
- Não há Runtime validado para nenhum item deste plano.
