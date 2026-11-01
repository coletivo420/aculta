## Priorização arquitetural vinculante - 2026-10-29

O [plano ARQ-01 a ARQ-09](PLANO-ARQUITETURAL-OPENCULTURAS-ACULTA-ARQ-01-09.md) orienta a incorporação de capacidades, sem portar módulos inteiros ou substituir o `aculta_index`. P0: ACL/precisão geográfica de locais, catálogo de ocorrências escalável e descoberta cultural com ACL; P1: eventos/locais estruturados, organizações, UGC, salvos; P2: menções, apoiadores, teasers e metadados. **Continua separada a implementação visual do Studio e o mobile-first de todo o tema.** Fonte quantitativa verificada é a auditoria 3.1.5, não inventário de versões diferentes.

# Comparativo OpenCulturas × ACULTA — ideias, visuais e destinos

**Retificação em 2026-10-29:** para o pacote **3.1.5** e prioridades aprovadas, consulte [a nova auditoria](AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md). Contagens, propostas de licença obrigatória, Honeypot e criação de papel `organizador` abaixo são **históricas**, supersedidas por decisões D-01/D-10/D-11. Não executar itens I-xx sem reconciliar com o programa OC315.

Documento de arquitetura. Compara o ACULTA com o OpenCulturas 3.1.4 (distribuição Drupal, GPL-2.0-or-later)
e decide o que vira melhoria, em qual módulo e em qual roadmap. Não copia código: a auditoria de cópia está
em `web/modules/custom/aculta_portal/modules/aculta_events/docs/AUDITORIA-OPENCULTURAS.md`.

Referência do OpenCulturas: `/home/piradopirata/projetos/aculta-openculturas/` (somente leitura).

Regra de uso: cada ideia abaixo tem um destino (roadmap de módulo) e não cria roadmap novo, exceto onde
indicado (`aculta_ugc`). Um roadmap por projeto, sem status copiado de outros documentos.

## 1. Como comparar

Foram lidos: README, DEVELOPMENT, SECURITY e CONTRIBUTING do pacote; as 42 configurações de views do pacote (conteúdo, listagens, relacionados e moderação); os 4 papéis (`oc_admin`, `magazine_editor`, `oc_organizer`, `oc_member`); as 4 automações ECA; os 3
tours; os módulos de conteúdo (`discussions`, `faq`, `section`, `teaser`, `media`, `address_links`, `content`,
`cookies_popover`, `dark_mode_toggle`); e os READMEs dos temas.

Do lado do ACULTA, foram conferidos os roadmaps dos módulos e os documentos de planejamento
(`docs/portal/ROADMAP.md`, `USER-UPLOADS.md`, `AVISOS-E-NOTIFICACOES.md`, `ACULTA-USER-PLAN.md`,
`STUDIO-MAGAZINE-ROADMAP.md`, os roadmaps de Studio e Statistics), além de `composer.json`, os
`info.yml`, `config/sync` e os módulos em `aculta_portal/modules/`.

O que o OpenCulturas faz e o ACULTA **já faz** (não é ideia nova):

| Área | OpenCulturas | ACULTA (já existe) |
| --- | --- | --- |
| Denúncia | flag `abuse` + ECA | Flags `sinalizar_topico`, `sinalizar_comentario`, `sinalizar_post_forum` e `aculta_user/src/Moderation/ReportQueue.php` |
| Moderação | Workflow Core + ECA de notificação | Workflows `aculta_forum_post`, `editorial`, `revista`, `wiki_editorial`; notificações em `aculta_messenger` |
| Relações entre conteúdos | Views `related_*` | `aculta_index` com relações nomeadas |
| Papéis | 4 papéis | 7 papéis (`content_editor`, `lms_teacher`, `gerente_loja` etc.) |
| Captcha | honeypot | Turnstile e captcha em login e cadastro |
| Estatística | `who_s_online`, `watchdog` (painéis do Drupal) | `aculta_statistics` (central de métricas, privacidade) |
| Sitemap | `simple_sitemap` | `aculta_portal_sitemap` (multidomínio) |
| Marca por tema | variáveis CSS (OpCult) | tokens `--aculta-*`, marca por purpose (`branding-*.md`) |
| Modo escuro | `dark_mode_toggle` | tokens light/dark |
| Cookies | `cookies_popover` | aceite único (`ACEITE-UNICO.md`) |

## 2. O que falta no ACULTA (camadas de experiência)

| Lacuna | Evidência no ACULTA | Equivalente OpenCulturas |
| --- | --- | --- |
| Listagens filtráveis padronizadas | Sem facets, sem autocomplete de filtro (`composer.json`) | `views.view.content`, `locations`, `event_catalogue`, `faq`, `media_library` |
| Bloco de conteúdo relacionado | `aculta_index` tem relações, mas nenhum bloco as entrega | `related_article`, `related_event`, `related_location`, `related_profile`, `recommended_by` |
| Painel de moderação | Fila de denúncias existe (`ReportQueue`), sem painel de acompanhamento | `flagged_content`, `moderated_content` |
| Notificação de denúncia | Não localizada como aviso específico para moderadores | ECA "Abuse report" |
| Reivindicação de posse | Não existe para locais, eventos ou perfis de organização | Flag `claim_ownership` + ECA "Claim ownership" |
| Papel de organizador | Não existe (`content_editor` é genérico) | `oc_organizer` (10 permissões) e `oc_member` (7) |
| "Meus salvos" | Flags `salvar_topico` e `seguir_topico` existem, sem lista no Studio | `my_bookmarks`, `my_recommendations`, `my_content` |
| Primeiro acesso | Não há tour nem cartão de orientação | Tours `oc-event`, `oc-location`, `oc-profile` |
| Cor de seção | Categorias editoriais sem cor | `openculturas_section` (cor por vocabulário) |
| Variações de cartão | SDCs de conteúdo existem; falta a lista oficial de variações | `teaser`, `teaser_big`, `teaser_unified`, `compact`, `search_result` |
| JSON-LD | Só tags Organization no metatag | `json_ld_schema` |
| Licença de mídia | Sem campo de licença em galerias ou uploads | `attribution` com licenças CC |
| Índice A-Z da wiki | Sem índice alfabético | view `glossary` |
| Anti-spam sem terceiros | Captcha externo | `honeypot` |
| Política de segurança pública | Não há `SECURITY.md` na raiz | `SECURITY.md` com divulgação responsável |
| Lint de CSS e JS | Sem stylelint, eslint nem prettier | `lint:css`, `lint:js`, `lint:scss`, `prettier` |

## 3. Ideias priorizadas

Escala: valor para a missão e para o usuário (A/M/B), esforço (A/M/B), risco (A/M/B). A ordem é a de
execução sugerida, e não substitui as prioridades já decididas em `docs/portal/ROADMAP.md`.

| ID | Ideia | Área | Valor | Esforço | Risco | Destino |
| --- | --- | --- | --- | --- | --- | --- |
| I-01 | Listagem filtrável padrão (View + filtros expostos + SDC de cartão + contagem + paginação sem JS obrigatório) | Portal | A | M | B | `docs/portal/ROADMAP.md`; catálogo de `aculta_events` |
| I-02 | Blocos de conteúdo relacionado pré-configurados sobre as relações nomeadas do `aculta_index` | Índice + Portal | A | M | M | `aculta_index/README.md`; `docs/portal/ROADMAP.md` |
| I-03 | Painel de moderação (fila, denúncias abertas, itens sinalizados, tempo de resposta) | Statistics + User | A | M | M | `aculta_statistics/docs/ROADMAP.md` (0.8.0 Dashboards) |
| I-04 | Aviso aos moderadores a cada denúncia nova (por código, não por ECA) | UGC | A | B | B | `aculta_ugc` (plano novo) |
| I-05 | Reivindicação de posse para locais, eventos e perfis de organização | User + Events | M | M | M | `ACULTA-USER-PLAN.md`; `aculta_events` |
| I-06 | Papéis de organizador e membro, com privilégio mínimo | User | A | M | A | `ACULTA-USER-PLAN.md` |
| I-07 | "Meus salvos" e "Minhas recomendações" no Studio, a partir das flags existentes | Studio | A | B | B | `aculta_studio/docs/ROADMAP.md` |
| I-08 | Primeiro acesso em cartões, sem JavaScript obrigatório | Studio | M | B | B | `aculta_studio/docs/ROADMAP.md` |
| I-09 | Cor de seção por vocabulário, com lista de cores em tokens | Magazine | M | B | B | `aculta_magazine/docs/README.md` |
| I-10 | Lista oficial de variações de cartão (`compact`, `teaser`, `destaque`, `busca`) | Portal + tema | A | B | B | `web/themes/custom/aculta420/docs/component-catalog.md` |
| I-11 | Links "como chegar" para rotas, abertos só por clique do usuário | Events | M | B | B | `aculta_events/docs/ROADMAP.md` |
| I-12 | JSON-LD de Event, Article, Place e Organization | Portal | A | M | B | `docs/portal/ROADMAP.md`; F7 de `aculta_events` |
| I-13 | Licença por mídia (CC ou própria) obrigatória para upload público | Image + Gallery + UGC | A | M | M | `aculta_image`; `USER-UPLOADS.md` |
| I-14 | Honeypot antes do captcha externo em formulários públicos | UGC | A | B | B | `aculta_ugc` |
| I-15 | Perguntas frequentes com item de conteúdo editável | Apoio, Institucional | B | B | B | `docs/portal/ROADMAP.md` |
| I-16 | Índice A-Z da wiki (por `wiki_category` e verbete) | Wiki | M | B | B | `docs/portal/ROADMAP.md` (wiki) |
| I-17 | Página de divulgação responsável (`SECURITY.md`) | Repositório | A | B | B | `docs/portal/ROADMAP.md`; contato a definir pelo responsável |
| I-18 | Regras de acessibilidade do OpenCulturas: link para navegação e botão para ação; unidades que respeitam a fonte do usuário | Tema | A | B | B | `web/themes/custom/aculta420/docs/accessibility.md` |
| I-19 | Lint de CSS e JS do tema (`stylelint`, `eslint`) e regra de dependências opcionais | Tema + ops | M | M | B | `docs/operations/` |
| I-20 | Comando `doctor` para patches (`aculta_patcher`) | Ops | B | M | B | `aculta_patcher/README.md` |

## 4. Por área

### 4.1 ACULTA Studio (`aculta_studio`)

Referência: `my_bookmarks`, `my_recommendations`, `my_content`, `my_bookmarks_taxonomy`, tours
`oc-event`, `oc-location`, `oc-profile`.

- **I-07:** a aba "Meus salvos" lista o que o usuário marcou com `salvar_topico`, `seguir_topico` e
  `salvar_conteudo` (a criar). A aba "Minhas recomendações" entra depois, com o mesmo padrão. Nenhum dado
  novo é coletado.
- **I-08:** "Primeiro acesso" é uma sequência de cartões no hub (`/`), com um passo por ação real (criar
  apelido, completar perfil, salvar algo). Sem JavaScript obrigatório, sem biblioteca de tour. Fecha com um
  link "Não mostrar de novo" que grava preferência na conta.
- Papel de organizador (I-06) aparece na Studio como uma seção "Minha organização", só para quem tem o papel.

### 4.2 ACULTA Statistics (`aculta_statistics`)

Referência: `who_s_online`, `who_s_new`, `watchdog`, `flagged_content`, `moderated_content`.

- **I-03:** o painel de moderação mostra **contagens e tempos agregados** (denúncias abertas, idade da mais
  antiga, sinalizações por tipo). Não mostra quem denunciou nem quem está online.
- "Quem está online" **entra no Statistics** (decisão do responsável, 2026-10-28), como contagem agregada com
  supressão abaixo de 5 pessoas e sem nomes. A lista nominal do OpenCulturas (nome de login) não é copiada.
- Encaixa no roadmap 0.8.0 (Dashboards) já previsto no módulo.

### 4.3 ACULTA Magazine (`aculta_magazine`)

Referência: `openculturas_section` (cor por vocabulário), `teaser` e `teaser_unified`,
`magazine_editor` (44 permissões), `views.view.frontpage`.

- **I-09:** cada categoria editorial recebe uma cor escolhida de uma lista fixa de tokens do `aculta420`.
  Não há cor livre (texto e contraste são controlados). A cor vale como reforço, nunca como único sinal.
- **I-10:** as variações de cartão passam a ser uma lista fechada: `compact` (lista), `teaser` (padrão),
  `destaque` (capa) e `busca` (resultado). Cada uma é um SDC, com as mesmas regras de imagem.
- Editor da revista: manter `content_editor` com permissões de revista. Não criar papel novo para
  isso (privilégio mínimo).

### 4.4 ACULTA User (`aculta_user`) e papéis

Referência: `oc_organizer`, `oc_member`, `claim_ownership`, `profile` com endereço e horário.

- **I-06 (papéis):** `organizador` e `membro` com privilégio mínimo. `organizador` gerencia só os conteúdos
  que reivindicou ou criou. `membro` não tem permissão de publicação. O OpenCulturas dá ao `oc_organizer` 10
  permissões e ao `oc_member` 7; o ACULTA deve listar as permissões exatas, com teste de acesso negado.
- **I-05 (reivindicação):** o pedido de posse de um local, evento ou perfil de organização passa por
  um flag, uma fila de moderação e uma decisão registrada. O ECA "Claim ownership" do OpenCulturas vira
  código, com teste. O pedido não transfere posse sem aprovação de moderador.
- Não adotar `oc_admin` com 108 permissões. O ACULTA mantém `administrator` com o escopo atual.

### 4.5 Índice e conteúdo relacionado (`aculta_index`, Portal)

Referência: `related_article`, `related_event`, `related_location`, `related_profile`, `related_sponsor`,
`related_pages`, `recommended_by`.

- **I-02 (implementado):** o `aculta_index` já guarda relações nomeadas. O bloco "Conteúdo relacionado" (`RelatedContentBlock`, ID `aculta_index_related_content`) consulta o índice via `ContentIndexRelationQuery`, respeita o acesso por entidade e por purpose, e cai para uma lista vazia sem casca HTML (falha fechada). O bloco nunca faz consulta direta às tabelas de origem; cache tags incluem a entidade fonte e os alvos vinculados. Testado por `RelatedContentBlockKernelTest` (7 testes / 146 asserções PASS).
- Uma relação nova exige um nome e um dono, como hoje. Não criar relação por texto livre.
- O cache do bloco usa as tags das entidades relacionadas, sem contexto de usuário.

### 4.6 Moderação e UGC (`aculta_ugc`, plano novo)

Referência: `abuse report`, `moderation state`, `honeypot`, `comment` com flags.

O ACULTA já tem moderação em vários lugares (Marley e WE SATIVA com contratos próprios, `AVISOS-E-NOTIFICACOES.md`,
fórum com workflow). A proposta **não substitui** esses contratos. O `aculta_ugc` reúne o que é comum:

- política de denúncia (motivos, registro, limite de denúncias por pessoa);
- aviso aos moderadores por código (I-04), com teste;
- honeypot e limitação de taxa antes do captcha externo (I-14);
- política de mídia de UGC: licença obrigatória (I-13) e consumo da política de upload já decidida em
  `USER-UPLOADS.md` (o `aculta_ugc` não cria uma segunda política de envio).

Plano completo: `web/modules/custom/aculta_portal/modules/aculta_ugc/docs/PLANO-IMPLANTACAO.md`.

### 4.7 Eventos, calendário e locais (`aculta_events`, `aculta_calendar`)

Referência: `event_catalogue`, `locations`, `related_event`, `related_location`, `address_links`, `oc_map_*`.

- **I-01:** o catálogo de eventos é a primeira listagem filtrável padrão (já previsto na F3).
- **I-02:** "eventos relacionados" e "locais relacionados" usam o bloco do `aculta_index`.
- **I-11:** "como chegar" usa links de rota para provedores externos, abertos só por clique. Não há
  requisição automática ao carregar a página.
- **I-12:** JSON-LD `Event` e `Place` (F7).
- **I-13:** imagens de eventos e locais declaram licença.

### 4.8 Wiki e apoio

- **I-16:** índice A-Z na wiki, por `wiki_category` e por título de verbete. Mesmo padrão de listagem (I-01).
- **I-15:** FAQ do apoio como conteúdo editável (um tipo de item, sem página nova por pergunta).

### 4.9 Tema e documentação (`aculta420`, `docs/`)

Referência: `docs/DEVELOPMENT.md` do OpenCulturas, `SECURITY.md`, `theme/README.md`.

O OpenCulturas tem princípios que o ACULTA aplica em partes, mas não reuniu em um lugar:

| Princípio (OpenCulturas) | Estado no ACULTA | Ação |
| --- | --- | --- |
| Privacidade por padrão, sem CDN nem terceiros | Parcial (Turnstile e Google Maps como provedor padrão, com aviso) | Registrar o tratamento de dados do Google no ADR do provedor de mapa |
| Acessibilidade: link para navegação, botão para ação | Não escrito | I-18 em `accessibility.md` |
| Acessibilidade: unidades que respeitam o tamanho de fonte do usuário | Não escrito | I-18 em `accessibility.md` |
| Acessibilidade: cores de variáveis que funcionam no modo escuro | Feito (tokens) | Nenhuma |
| Site-builder: só novo módulo quando necessário; reusar campos | Parcial (`AGENTS.md` pede reuso) | Adicionar à `DOCUMENTATION.md` do projeto |
| Site-builder: evitar sobrescrever template; preferir configuração | Não escrito | Adicionar ao `aculta420/docs/development.md` |
| Dependências opcionais de terceiros | Parcial | Regra em `docs/architecture/environments.md` |
| Divulgação responsável | Não há `SECURITY.md` | I-17 |
| Idioma: inglês primeiro, texto traduzível | ACULTA é em português | Manter português; exigir `t()` em todo texto (regra já existente) |
| Configuração versionada, com manifesto | `config/sync` completo | Não adotar o manifesto de `config_devel` |

Positivo que ACULTA deve manter: os tokens `--aculta-*` (equivalentes às variáveis do OpCult, sem
renomear variáveis). Os `branding-*.md` por purpose já seguem a mesma lógica.

## 5. Visuais

Os diagramas são de layout e de fluxo. Não definem estilo final: o estilo vem dos tokens do `aculta420`.

### V-1 — Listagem filtrável (I-01)

```
┌───────────────────────────────────────────────────────────────┐
│ Eventos                                    42 resultados      │
├───────────────────────────────────────────────────────────────┤
│ [Tipo ▾]  [Local ▾]  [Período ▾]        [Limpar filtros]      │
│ Filtros ativos: Oficina ✕  Gratuito ✕                         │
├───────────────────────────────────────────────────────────────┤
│ ┌──────────┐  Título do evento                                 │
│ │ 14 OUT   │  Oficina · Centro Cultural · gratuito             │
│ └──────────┘                                                  │
│ ┌──────────┐  Título do evento                                 │
│ │ 15 OUT   │  Show · Praça · R$ 0                              │
│ └──────────┘                                                  │
│                              [Carregar mais]                   │
└───────────────────────────────────────────────────────────────┘
Regras: filtros em <fieldset> com legend; resultado anunciado em aria-live;
sem JavaScript, os filtros funcionam como formulário GET.
```

### V-2 — Bloco de conteúdo relacionado (I-02)

```
┌──────────────────────────────────────────────┐
│ Conteúdo relacionado                          │
├──────────────────────────────────────────────┤
│ [imagem]  Título do verbete          Wiki     │
│           resumo de duas linhas               │
│ [imagem]  Título da reportagem       Revista  │
│           resumo de duas linhas               │
│ [imagem]  Evento na mesma região     Eventos  │
│           14 OUT · Centro Cultural            │
└──────────────────────────────────────────────┘
Ordem: relação nomeada (prioridade), depois data de publicação.
Sem relação suficiente: o bloco não aparece (sem "nenhum resultado").
```

### V-3 — Painel de moderação (I-03)

```
┌──────────────────────────────────────────────────────────────┐
│ Moderação · últimos 30 dias                                    │
├───────────────┬───────────────┬───────────────┬──────────────┤
│ Abertas        │ Mais antiga    │ Resolvidas     │ Tempo médio   │
│ 12             │ 3 dias         │ 48             │ 1 dia 4 h     │
├───────────────┴───────────────┴───────────────┴──────────────┤
│ Por tipo:  tópico 5 · comentário 4 · post de fórum 2 · outro 1 │
│ Por área:  Fórum · Revista · Wiki · Eventos                    │
└──────────────────────────────────────────────────────────────┘
Sem nome de quem denunciou e sem lista de presença.
Acesso: papel de moderação. Cada número abre a fila filtrada.
```

### V-4 — Fluxo de denúncia (I-04 e I-14)

```
[ Pessoa ] ──denuncia──▶ [ Honeypot + limite de taxa ] ──ok──▶ [ Registro da denúncia ]
                                 │ bloqueado                          │
                                 ▼                                    ▼
                         [ Mensagem genérica ]           [ Aviso aos moderadores (código) ]
                                                                    │
                                                                    ▼
                                             [ Fila de moderação ] ──▶ [ Decisão registrada ]
                                                                              │
                                                    [ Autor é avisado: mantido ou removido ]
Motivo, data e decisão ficam no registro. Não há dado de quem denunciou no aviso.
```

### V-5 — Reivindicação de posse (I-05)

```
Local "Centro Cultural X"                          [ Reivindicar ]
   │
   ▼  pedido: nome, vínculo, documento de contato (opcional)
[ Fila de moderação ] ──aprovar──▶ posse concedida (papel organizador)
                      ──recusar──▶ motivo informado ao solicitante
Nada muda até a decisão. O pedido não transfere edição de conteúdo antigo.
```

### V-6 — "Meu espaço": salvos e recomendações (I-07 e I-08)

```
Meu espaço
┌──────────────────────────────────────────────────────┐
│ Primeiros passos                    (somem ao concluir) │
│ ☐ Escolha um apelido                                  │
│ ☐ Complete seu perfil                                 │
│ ☐ Salve um conteúdo                                   │
│                              [Não mostrar de novo]    │
├──────────────────────────────────────────────────────┤
│ Salvos (6)   Seguindo (3)   Recomendações (0)          │
│ Lista de cartões do tipo "compact", com link e remover  │
└──────────────────────────────────────────────────────┘
```

### V-7 — Cor de seção (I-09)

```
Seção                  Cor (token)           Exemplo
Opinião                --aculta-red          ■ Opinião
Cultura viva           --aculta-green        ■ Cultura viva
Agenda                 --aculta-yellow       ■ Agenda   (texto escuro sobre amarelo)
Entrevista             --aculta-green-dark   ■ Entrevista
Regra: o nome da seção sempre aparece; a cor reforça, não identifica sozinha.
```

### V-8 — Variações de cartão (I-10)

```
compact   ▭ Título · data                                 (lista)
teaser    ┌─────┐ Título / resumo / autor                 (padrão)
          └─────┘
destaque  ┌──────────────────────────────┐
          │        imagem de capa         │ Título grande / resumo
          └──────────────────────────────┘
busca     Título  ·  trecho com o termo destacado  ·  purpose
```

## 6. Não adotar

| Item do OpenCulturas | Motivo |
| --- | --- |
| ECA para notificações e fluxos | Regras em código são testáveis e revisáveis; o Aculta já usa código |
| `oc_admin` com 108 permissões | Contraria privilégio mínimo; o Aculta mantém papéis com escopo |
| Lista nominal de "quem está online" | Mostra o nome de login da conta; no ACULTA só contagem agregada (seção 4.2) |
| Redirect automático de URL antiga | Política do Aculta: sem alias ou redirect temporário |
| Cookies popover e dark mode toggle | Aceite único e tokens já cobrem |
| Bookable e ingressos | Venda fora da v1 de eventos (`aculta_commerce` e Mercado Pago) |
| OpenStreetMap e Nominatim | Decisão D7 (Google Maps como provedor padrão) |
| Matomo | Estatística própria (`aculta_statistics`) com retenção configurável |
| Manifesto de `config_devel` | `config/sync` completo já atende |

## 7. Decisões que dependem do responsável

1. **Papéis de organizador e membro (I-06):** aprovar a criação dos dois papéis e a lista de permissões.
2. **Reivindicação de posse (I-05):** aprovar o fluxo de V-5 e quem decide (moderação de conteúdo ou administração).
3. **Aviso de denúncia (I-04):** aprovar o destinatário dos avisos (equipe de moderação ou por purpose).
4. **Contato de segurança (I-17):** informar o endereço de divulgação responsável.
5. **Cor de seção (I-09):** aprovar a lista fechada de cores.

## 8. Ligações

- Auditoria de cópia do OpenCulturas: `web/modules/custom/aculta_portal/modules/aculta_events/docs/AUDITORIA-OPENCULTURAS.md`
- Plano de UGC: `web/modules/custom/aculta_portal/modules/aculta_ugc/docs/PLANO-IMPLANTACAO.md`
- Roadmap do portal: `docs/portal/ROADMAP.md` (seção "Melhorias inspiradas no OpenCulturas")

## 9. Segunda leva: visual, blocos e views (2026-10-28)

### 9.1 Inventário comparado

| Lado | Quantidade | Observação |
| --- | --- | --- |
| OpenCulturas: views de configuração | 42 | Conteúdo, listagens (`content`, `locations`, `event_catalogue`, `glossary`, `faq`), relacionados (`related_*`, `recommended_by`), usuários (`who_s_new`, `who_s_online`, `main_profile`), moderação (`flagged_content`, `moderated_content`) e exportação |
| OpenCulturas: plugins de bloco | 7 | Mapa, cookies, modo escuro, hero, título de página, marca "powered by", ícone de favorito |
| OpenCulturas: layouts | 4 | 1, 2, 3 colunas e "5 seções" (título, destaque, essenciais, conteúdo, lateral) |
| OpenCulturas: tipos de parágrafo | 19 | Grade de teasers, carrossel de texto, galeria, menção na imprensa, membro, texto, bloco, view embutida, contato, download, acessibilidade |
| ACULTA: componentes SDC | 47 | Padrões (`card`, `carousel`, `content-grid`, `rail`, `hero`, `figure`, `quote`), conteúdo (`author-card`, `course_card`, `editorial-card`), doação, feedback, Messenger e QR |
| ACULTA: blocos (plugins) | 3 | Doação, retorno ao site e doação rápida |

A regra de reuso do roadmap visual (2026-10-22) classifica cada peça do OpenCulturas: template Twig e
bloco PHP sem contrato SDC são **LEGADO-MIGRAR** (referência, nunca cópia). Por isso todo item abaixo vira
**SDC candidato** ou **view**, com story, consumidor real e WCAG 2.2 AA.

### 9.2 Ideias

| ID | Peça do OpenCulturas | Ideia no ACULTA | Tipo | Classificação inicial | Destino |
| --- | --- | --- | --- | --- | --- |
| I-21 | Layout "OC 5 sections" | Página de evento com cinco regiões (título, destaque, essenciais, conteúdo, lateral) | Layout | Verificar UI Patterns Layouts (já é dependência do Aculta) antes de criar | `aculta_events` F3/F6 |
| I-22 | Layouts 1, 2 e 3 colunas | Composição de página em 1, 2 ou 3 colunas | Layout | Verificar `patterns/rail` e `content-section` antes de criar | Catálogo do tema |
| I-23 | View `content_recent` | "Recentes e em destaque" com "mais lidos" por contagem agregada, sem cookie | View + cartão | Depende de I-10 e de Statistics | Revista; Wiki |
| I-24 | View `archive` | Arquivo por ano e mês da Revista | View | Nova view | Revista |
| I-25 | Views `taxonomy_term` e `vocabulary` | Página de termo ou categoria com listagem filtrável | View | Depende de I-01 | Revista; Wiki |
| I-26 | Parágrafo `media_mention` | "Na imprensa": veículo, data e link externo | SDC candidato `content/press-mention` | Nova | Portal (`ROADMAP.md`) |
| I-27 | View `related_sponsor` | Apoiadores e parceiros com logotipo, só com autorização registrada | SDC candidato `content/sponsor-logos` | Nova; exige consentimento | Apoio; Institucional |
| I-28 | Parágrafo `a11y_wheelchair` | Selos de acessibilidade do local ou evento (ícone e texto) | SDC candidato `content/accessibility-badges` | Nova; cor nunca é o único sinal | `aculta_events` F4 e F6 |
| I-29 | View `who_s_new` (sem nomes) | Contagem de novos membros na semana | SDC candidato `feedback/stat-counter` | Nova; supressão de 5 | Statistics |
| I-30 | View `related_date` | "Próximos eventos" na vitrine e na home de eventos | View + cartão | Depende de I-10 | `aculta_events`; vitrine do main |
| I-31 | Bloco `BookmarkFlagIconBlock` | Botão de salvar no cartão, com estado salvo ou não | SDC candidato `patterns/bookmark-toggle` | Nova; sobre a flag existente | Studio (I-07) |
| I-32 | View `dashboard: finder` | Busca no hub de conta | — | **Já existe** no hub do Studio | Nenhuma ação |
| I-33 | Parágrafo `text_slider` (swiffy) | Carrossel de texto | — | **Já existe** (`patterns/carousel`); não adotar a biblioteca swiffy | Nenhuma ação |
| I-34 | Parágrafo `teaser_wrapper` | Grade mista (conteúdo, termo e link externo) | — | `patterns/content-grid` existe; verificar se aceita mistura | Catálogo do tema |
| I-35 | Parágrafo `member` | Cartão de membro da equipe | — | **Já existe** (`content/author-card`) | Nenhuma ação |

### 9.3 Views e blocos não adotados

| Item | Motivo |
| --- | --- |
| `user_admin_people` | Lista de pessoas com dados de conta; exige revisão de LGPD e de minimização antes de qualquer uso |
| `legal_users` | Já coberto pelo registro de aceite único (`ACEITE-UNICO.md`) |
| `watchdog` | Log de sistema não é painel de conteúdo; fica com a operação |
| `oc_er_mod_states` | Estados de moderação já ficam nos workflows e na fila do `aculta_ugc` |
| Bloco "marca powered by" | Não agrega à missão; o crédito de software vive na documentação |
| Dark mode e cookies como blocos | Tokens e aceite único já cobrem (ver seção 6) |
| Layout Builder genérico | Composição fica com UI Patterns Layouts, para não criar um segundo sistema de layout |

### 9.4 Visuais novos

**V-9 — Página de evento em cinco regiões (I-21)**

```
┌──────────────────────────────────────────────────────────┐
│ TÍTULO                                    [Ver no mapa]    │
├──────────────────────────────────────────┬───────────────┤
│ DESTAQUE (imagem com proporção fixa)       │ ESSENCIAIS     │
│                                           │ data · local   │
│                                           │ gratuito       │
│                                           │ acessibilidade │
├──────────────────────────────────────────┴───────────────┤
│ CONTEÚDO (descrição, datas, organizador)                  │
├──────────────────────────────────────────────────────────┤
│ LATERAL: eventos relacionados (I-02)                       │
└──────────────────────────────────────────────────────────┘
Em telas pequenas: uma coluna na ordem TÍTULO, DESTAQUE, ESSENCIAIS, CONTEÚDO, LATERAL.
```

**V-10 — Contagem agregada com supressão (I-29 e presença)**

```
Pessoas online agora            Novos membros nesta semana
┌───────────────┐               ┌───────────────┐
│      23       │               │   menos de 5  │   ← abaixo do limite: sem número
│   visitantes │               │               │
└───────────────┘               └───────────────┘
Sem nomes, sem lista. Atualização a cada minuto; texto alternativo com o período.
```

### 9.5 Destinos

- Itens de layout e componente: `web/themes/custom/aculta420/docs/visual-cross-product-roadmap.md`
  (seção "Candidatos da segunda leva OpenCulturas").
- Itens de view e de módulo: roadmap de cada módulo (Statistics, Revista, `aculta_events`, Studio, Apoio).
- Itens de portal: `docs/portal/ROADMAP.md`.

### 9.6 Código

Análise de funções, hooks e padrões de código, com os itens C-01 a C-10 e os defeitos a corrigir antes do
reuso: `docs/architecture/OPENCULTURAS-INCORPORACAO-CODIGO.md`.

## 10. Decisões registradas (2026-10-28)

As perguntas da seção 7 foram respondidas. Decisões, impactos e pendências estão em
`docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`. Pendente: endereço de divulgação responsável
(I-17) e ADR do provedor de mapa (D-08).

## 10. Retificação auditada para OpenCulturas 3.1.5 (2026-10-29)

**Fonte de verdade para a nova rodada:** [AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md](AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md), no HEAD da PR #299. As seções 1–9 registram análise histórica do **3.1.4** e decisões preliminares; não equivalem ao inventário do 3.1.5. Este pacote possui **42 Views (7 desabilitadas)**, **9 vocabulários**, **19 tipos de parágrafos**, **14 módulos próprios**, **3 facetas**, **5 flags**, **3 workflows** e **4 automações ECA**. `archive`, `glossary` e `content_recent` vêm desabilitadas, e `views.view.oc_map_dates.yml` não foi encontrado em `config/install`.

**Decisões posteriores prevalecem:** D-01 usa vínculo/escopo editorial e não papel `organizador`; D-10 não torna licença de mídia obrigatória; D-11 não autoriza Honeypot; D-17/D-20 preservam Maps/Calendar próprios. Este ajuste **não** reabre decisões e não marca itens antigos como implementados.

**Nova prioridade aprovada:** OC-A (auditoria de acesso/performance e busca de eventos/locais), OC-B (campos e consumidores SDC), OC-C (organizações/Studio/UGC), OC-D (imprensa, apoiadores, download, teasers e arquivo opcional), OC-E (horários OSM e breadcrumbs, condicionados à comparação). Os critérios e arquivos exatos estão na nova auditoria. Nenhuma alteração de código nesta rodada.

## Retificação da comparação histórica - 3.1.5 (2026-10-29)

Este comparativo foi elaborado com base no 3.1.4; para planejar novas implementações, prevalece a [auditoria 3.1.5](AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md). São 42 Views de instalação (7 desabilitadas), 9 vocabulários e 3 workflows, não as contagens preliminares. `archive`, `glossary` e `content_recent` não são funcionalidades ativas prontas. `aculta_ugc` permanece plano, não módulo executável; vários SDCs (selos, patrocinadores, layout de cinco regiões) já existem, mas nem todos são consumidos em telas reais. `bookmark-toggle` já é consumido no Fórum; `AcultaBreadcrumbBuilder` já implementa ancestrais de taxonomia; `aculta_index` tem API própria e não deve ser refeito. Lacuna P0 real: descoberta cultural no Search API, hoje sem eventos e locais customizados.

Aplicar decisões posteriores ao texto histórico: D-01 **escopo por vínculo**, sem novo papel genérico; D-10 **licença opcional**; D-11 **sem Honeypot agora**; D-17/D-18 **Leaflet/OSM padrão e Google opcional**; D-13/D-14 **recorrência ACULTA**, sem Smart Date. Metadados de IA e polígonos não foram comprovados no tarball 3.1.5. Execução por ondas OC-A a OC-F: [plano](PLANO-INCORPORACAO-OPENCULTURAS.md).
