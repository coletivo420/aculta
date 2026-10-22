# Wiki420 como submódulo do Portal — planejamento

Status: **planejado**. Esta entrega é só documentação. Nenhum código foi movido,
nenhuma rota foi alterada e nenhum módulo foi habilitado.

Este documento é a fonte única da frente. Cada fase termina antes da próxima começar.

## Objetivos da frente

1. **Submódulo:** criar `aculta_wiki` em
   `web/modules/custom/aculta_portal/modules/aculta_wiki` e mover para ele o código inteiro
   da Wiki (`WikiController`, `WikiSearchFormBuilder` e as rotas `aculta_portal.wiki_*`),
   sem alterar URLs.
2. **Estatísticas prontas para a Wiki:** preparar o `aculta_statistics` para receber a Wiki,
   com `WikiProvider`, métricas de publicação e atualização, e ranking por verbete.
   O Statistics continua só lendo conteúdo; o submódulo `aculta_wiki` não depende dele.
3. **Construção contínua da Wiki:** depois dos objetivos 1 e 2, seguir com upload
   contextual, adaptador de discussão do Fórum, busca e notificações.

## Estado de partida (verificado no repositório)

**Conteúdo e configuração** (em `config/sync`):

- Tipo `wiki_entry`, fluxo `wiki_editorial` (rascunho, em revisão, publicado).
- Views `wiki_entries` (`/wiki/verbetes`) e `wiki_categories`.
- Domínio `wiki420` (produção `wiki420.aculta.org`).
- Formato de texto `wiki`, com mídia `document` no CKEditor.

**Código** (fora de `aculta_wiki`, ainda sem submódulo):

- `aculta_portal/src/Controller/WikiController.php` (287 linhas).
- `aculta_portal/src/Presentation/WikiSearchFormBuilder.php`.
- Rotas `aculta_portal.wiki_home` (`/wiki`), `aculta_portal.wiki_search` (`/wiki/busca`) e
  `aculta_portal.wiki_search_preview` (`/wiki/busca/previa`) em `aculta_portal.routing.yml`.

**Estatísticas** (`aculta_statistics`, 0.9.0-dev.5):

- Existe: `wiki.entries.read`, gravada pelo beacon `WikiReadController` e pelo
  `WikiReadHooks`, com antiabuso por `flood` e retenção de 90 dias. Há testes unitários e de kernel.
- Não existe: `wiki.entries.published`, `wiki.entries.updated`, `WikiProvider` e ranking por verbete.
  Esses itens aparecem no `INTEGRATIONS.md`, mas não estão no código.

**Divergências corrigidas nesta entrega:** `CURRENT-STATE.md` dizia que o `aculta_statistics`
não existia e que o contador de leitura não estava decidido. Ambos estavam desatualizados.

## Regras que valem para esta frente

- `docs/operations/AGENT-CONTENT.md`: mudança de escopo da Wiki (módulo próprio, busca,
  notificações, perfis) exige atualizar, no mesmo commit, o inventário LGPD, a política de
  privacidade e o `AGENTS.md` da Wiki.
- A Wiki não é reimplementada no Fórum. O Fórum só oferece o adaptador de discussão por verbete.
- O `aculta_statistics` só lê contagens e conteúdo. Nunca lê autoria privada.
- Não abrir PR nem fazer merge sem pedido explícito do responsável.
- Nenhuma métrica nova entra em produção antes de estar descrita no inventário LGPD.

## Fase 0 — Planejamento e documentação (esta entrega)

- Este documento.
- Correção das divergências em `aculta_statistics` (`CURRENT-STATE.md`, `ROADMAP.md`,
  `INTEGRATIONS.md`, `CHANGELOG.md`).
- Referências atualizadas em `AGENT-CONTENT.md`, `LGPD-INVENTARIO.md`, `docs/modules/README.md`
  e no adaptador de Fórum (`aculta_forum/docs/CONTEXTS.md`).

**Verificação:** `git diff --stat` mostra só arquivos `.md`. Nenhum YAML, PHP ou config alterado.

## Fase 1 — Casca do submódulo

- Criar `aculta_wiki/aculta_wiki.info.yml`, `AGENTS.md`, `README.md`, `CHANGELOG.md`,
  `docs/ROADMAP.md` e `docs/TEST-MATRIX.md`, seguindo o padrão dos outros submódulos do Portal.
- Dependências declaradas: `aculta_portal`, `domain`, `views`, `node`, `content_moderation`.
  Sem dependência de `aculta_statistics`.
- Ainda não habilitado nesta fase.

**Verificação:** validadores do repositório (`validate-own-module-packages` e
`validate-public-slugs`, já citados no CHANGELOG do Portal) passam.

## Fase 2 — Mover o código inteiro

- Mover `WikiController`, `WikiSearchFormBuilder` e as três rotas `aculta_portal.wiki_*` para
  `aculta_wiki`. Os caminhos e os nomes das rotas não mudam.
- Os demais pontos de `aculta_portal` que citam `wiki420` (`DomainPurposeManager`,
  `ContentPurposeResolver`, `PurposeFrontPathProcessor`, `DomainRouteSubscriber`,
  `DomainPresentationBuilder`) tratam de domínio e purpose, não da Wiki. Ficam no Portal, com
  revisão de cada um para confirmar que não há código da Wiki escondido.
- Configuração: manter os UUIDs e IDs de `config/sync`. A decisão de quem é dono de cada item
  está em "Decisões pendentes".
- Habilitar `aculta_wiki` só quando as rotas estiverem movidas e testadas.

**Verificação:**

- Unit e kernel do controlador e da busca.
- `drush cr` sem erro.
- Config Sync sem diferenças.
- HTTP 200 em `/wiki`, `/wiki/verbetes`, `/wiki/busca` e `/wiki/busca/previa` no domínio `wiki420`.
- Backup privado antes de qualquer `updb`, como no procedimento atual.

## Fase 3 — Documentação obrigatória da mudança de escopo

- `AGENT-CONTENT.md`: atualizar "Estado atual" e "Mudança de escopo" para refletir o módulo.
- `LGPD-INVENTARIO.md`: pendência 3 (base legal e retenção da Wiki) e a linha de
  `aculta_statistics`, que já cita `wiki.entries.read`. Incluir `wiki.entries.published` e
  `wiki.entries.updated` antes de criá-las.
- Política de privacidade: seção "Bases legais por uso do site".
- `aculta_wiki/AGENTS.md`: regras do submódulo.

## Fase 4 — `aculta_statistics` pronto para receber a Wiki

4.1. **Documentação corrigida** (feito na Fase 0).

4.2. **`WikiProvider`** com duas métricas diárias agregadas, sem autoria:

- `wiki.entries.published`: primeira publicação de um verbete. Seguir o padrão do Fórum (dev.5,
  com a correção de primeira publicação de revisão), para não contar revisões como novos verbetes.
- `wiki.entries.updated`: nova revisão publicada de um verbete já publicado.
- Conteúdo sincronizado ou importado não gera evento.

4.3. **Fronteira:** o provider lê entidades `wiki_entry` e eventos de publicação. Não importa
classes do `aculta_wiki`. Se o submódulo não estiver habilitado, o provider não gera dados.

4.4. **Ranking por verbete** no painel da área Wiki: os verbetes mais lidos no período, com
título resolvido na renderização. Verbetes excluídos saem do ranking sem erro. Exportação CSV
com o mesmo recorte.

4.5. **Catálogo e retenção:** registrar as duas métricas no `MetricCatalog` com sensibilidade
pública e retenção de 90 dias, como `wiki.entries.read`.

4.6. **Testes:**

- Unit: `WikiProvider` e o cálculo de primeira publicação e atualização.
- Kernel: agregado diário e ranking, incluindo verbete excluído.
- Runtime: beacon responde 204 em verbete publicado; ranking exibe dados reais.

**Gate:** nenhuma métrica nova é criada antes da atualização do inventário LGPD (Fase 3).

## Fase 5 — Construção contínua da Wiki

Ordem sugerida, depois das Fases 1–4:

1. Contagem de verbetes na view `wiki_entries` e campos faltantes.
2. Upload contextual pela política central `aculta_portal.user_upload_policy`, com aba própria.
3. Adaptador `wiki` do Fórum: uma discussão por verbete, em Comment no Node. A revisão
   editorial continua separada.
4. Busca e notificações. Mudam a retenção e exigem atualizar o inventário LGPD de novo.
5. Estatísticas de edição e revisão por verbete, a partir do histórico do Node.

## Decisões pendentes (responsável)

- **Dono da configuração:** manter os itens de `config/sync` no núcleo ou mover para
  `aculta_wiki/config/install`? Recomendação: manter os UUIDs e mover só depois de confirmar
  o Config Sync sem diferenças.
- **Ranking:** só no painel administrativo ou também público? Padrão adotado: só administrativo,
  como os demais painéis do Statistics.
- **Contagem de atualizações:** contar todas as revisões ou só revisões publicadas?
  Padrão adotado: só revisões publicadas.

## Riscos

- **Produção ativa:** `wiki420.aculta.org` está no ar. A mudança precisa ser invisível ao visitante.
- **URLs:** nenhuma URL pode mudar. Redirecionamentos não são previstos nesta frente.
- **Dependência circular:** `aculta_wiki` não depende de `aculta_statistics`, e o Statistics
  não importa classes do submódulo.
- **Configuração:** mover configuração entre módulos pode gerar diferenças no Config Sync.
  A Fase 2 cobre isso.
