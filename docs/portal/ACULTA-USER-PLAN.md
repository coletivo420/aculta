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
- Manter o Core `user_picture` e o estilo `aculta_avatar`. Não criar campo de foto novo.
- Fallback neutro quando não há foto: o presenter entrega `has_photo: false` e iniciais. O tema desenha o círculo (sem SVG de interface; ícone via Bootstrap Icons).
- Não usar Gravatar. Motivo: envia e-mail (ou seu hash) a terceiro, o que exige revisão de privacidade e consentimento. Não há confirmação de D8+ para o módulo.
- Foto pública é opt-in (decisão 2).
- Moderação de imagem fica fora desta fase. Core não tem moderação de foto; registrar como risco.

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

### D. Wiki (`wiki_entry`)
- "Minhas contribuições": View sobre `node_field_revision` com `revision_uid` = usuário e tipo `wiki_entry`. Mostra data, verbete e link para `diff`.
- Verbete não publicado não aparece na listagem pública do autor (regra da Wiki em `GUARDRAILS.md`).
- Contagens são derivadas. Nenhum contador persistido no Portal.

### E. Fórum e "Minha participação" (`/minha-participacao`)
- Tópicos: nós `forum` com `uid` do usuário. Respostas: `comment_forum` com `uid`.
- Seguir e salvar tópico: flags `seguir_topico` e `salvar_topico` já existentes.
- Denúncia de comentário (`DT-P13`): Flag do tipo `entity:comment` com fila de moderação via Views. A moderação reusa permissões e o Comment do Core (`GUARDRAILS.md`, seção Fórum). Entra na fase U4.

### F. Funções sociais
- **Seguir pessoa:** Flag sobre entidade `user`, nome `seguir_pessoa`. A confirmar que Flag 5.1.1 aceita `entity:user` (verificação no Homelab). Se não aceitar, a alternativa é um campo de referência em uma entidade de vínculo, o que cria storage e exige decisão.
- **Seguidores e seguindo:** contagens e listas por Views (`/seguindo`, `/seguidores`). Lista de seguidores de outras pessoas é privada por padrão.
- **Feed:** View de atividades (tópicos, respostas, verbetes publicados) das pessoas seguidas. Sem contrib de atividade.
- **Notificações:** somente opt-in. Envio por Mailer Plus, com fila do Core (`queue`), uma mensagem por evento de lote. Nenhum envio sem consentimento, e nada de PII no corpo além do necessário.

### G. Gamificação (escopo restrito)
- **Recomendado:** badges **derivados**. Cada badge é uma condição sobre contagens reais (verbetes publicados, revisões, respostas no Fórum, anos de participação). Definições em `config/` (config entity do próprio módulo, ou `config` simples), sem tabela de pontos.
- **Sem:** ranking público, comparação entre pessoas, pontuação ligada a doação, compra ou pagamento (Mercado Pago e apoio não entram em métricas). Motivo: a associação é antiproibicionista e sem fins lucrativos; ranking e pressão por volume podem desincentivar a participação e expor dados de apoio.
- **Ledger (histórico de pontos):** só com decisão específica e justificativa. Seria storage novo (entidade própria), e a regra do projeto pede capacidade existente antes de criar storage paralelo.

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
| **U2** | Autoria da revista (cartão e listagem por autor), "Minhas contribuições" da Wiki, "Minha participação" do Fórum. Só Views e leitura. | Views com cache correto; conteúdo não publicado não aparece; teste de navegador em `TESTES-NAVEGADOR.md`. |
| **U3** | Seguir pessoas (Flag), seguidores e feed (Views), notificações opt-in por fila. | Consentimento validado; envio de e-mail só com opt-in; sem listas de seguidores públicas. |
| **U4** | Badges derivados; denúncia de comentário (DT-P13) com fila de moderação. | Sem ranking; badges sem PII; denúncia sem exposição do denunciante. |
| **U5** (condicional) | Menções `@apelido`. | Só se a verificação de contrib D11 aprovar. |

Versionamento (`web/themes/custom/aculta420/docs/versioning.md`): U1 é **MINOR** no Portal (submódulo novo). Linha alvo sugerida: `0.4.0-dev.N`, com o submódulo em `0.1.0-dev.N`. Marcadores coerentes no mesmo PR: `aculta_portal.info.yml`, `CHANGELOG.md` do Portal e do submódulo, `ROADMAP.md`. Nenhuma tag sem pedido do responsável.

Gates por fase: `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-module-theme-boundary.php`, `php scripts/validate-public-slugs.php`, `php scripts/validate-environment-addresses.php`, e os testes do submódulo.

## 7. Decisões para o responsável

1. **Prioridade:** iniciar U0 agora, ou esperar S2–S4? (Roadmap: features depois do saneamento.)
2. **Visibilidade:** perfil público opt-in? Campos públicos aprovados: apelido, foto, resumo, papel na associação? Cidade e UF públicas: sim ou não?
3. **Slug do autor:** `field_slug` único gerado do apelido, ou o apelido atual com validação de unicidade?
4. **Autor convidado** (sem conta) na revista: aceitar com campo de assinatura de texto, ou só autores com conta?
5. **Purpose das páginas:** `/autores` no MAGAZINE? Perfil e participação ficam no MAIN, na ACCOUNT, ou em outro purpose? (`ADMIN-DOMAIN-POLICY.md`.)
6. **Gamificação:** aprova badges derivados sem ranking? Algum badge pode depender de participação no Fórum e na Wiki?
7. **Gravatar:** confirma a recomendação de não usar?
8. **Notificações:** opt-in por tipo de evento? Frequência máxima?
9. **Menções:** entram no escopo ou ficam para depois?
10. **Verificação de contrib:** autoriza verificar no Homelab (`composer dry-run`) os candidatos da seção 3 antes de U1?

## 8. Riscos e limites

- Compatibilidade D11 de todos os candidatos novos: **não confirmada** nesta sessão (bloqueio de egress em `drupal.org`).
- Código do Core e do contrib não está presente neste workspace; APIs citadas aqui (Flag `entity:user`, Pathauto para perfil, Views de `node_field_revision`) devem ser confirmadas no Homelab em U0.
- O módulo `redirect` pode criar redirect automático quando o Pathauto troca um alias. Isso conflita com a regra de slug sem 301. Precisa ser desligado para os aliases de autor.
- Dados pessoais e LGPD: a ativação de perfil público e de notificações depende de decisão do responsável e de revisão de texto de consentimento.
- Gamificação mal desenhada pode expor participação e apoio. O risco está em métricas, não só na interface.
- Não há Runtime validado para nenhum item deste plano.
