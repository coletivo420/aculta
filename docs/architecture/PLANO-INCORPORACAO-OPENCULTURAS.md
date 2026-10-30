## Frente arquitetural aprovada ARQ-01 a ARQ-09 (2026-10-29)

[Plano normativo de implementação seletiva](PLANO-ARQUITETURAL-OPENCULTURAS-ACULTA-ARQ-01-09.md): ARQ-01 ACL e privacidade de locais; ARQ-02 catálogo/ocorrências performático; ARQ-03 busca cultural via Index/Search API; ARQ-04 dados de locais/eventos; ARQ-05 organizações/reivindicação; ARQ-06 UGC/moderação; ARQ-07 salvos/recomendações; ARQ-08 imprensa/apoiadores/metadados/teasers; ARQ-09 homologação. Separado da frente visual OCS e do mobile-first global THEME-MF; dependências, decisões e estado definidos no plano. **Não confundir planejamento com implementação.**

# Plano de incorporação do OpenCulturas no ACULTA — status

Documento vivo. Mostra o que do código do OpenCulturas já foi reescrito no ACULTA, o que falta e o que foi
decidido não copiar. Fontes: `web/modules/custom/aculta_portal/modules/aculta_events/docs/AUDITORIA-OPENCULTURAS.md`
(achados S1–S8, T1–T14), `OPENCULTURAS-INCORPORACAO-CODIGO.md` (C-01 a C-10) e
`docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` (D-01 a D-42).

Regra de cópia: nenhum arquivo é copiado como está. Cada trecho derivado tem cabeçalho de atribuição e uma linha em
`aculta_events/docs/ORIGEM-OPENCULTURAS.md`. Repositório em GPLv3 e origem em GPL-2.0-or-later: compatível.

## Status por microfase

| Id | Origem no OpenCulturas | Destino no ACULTA | Situação | Evidência |
| --- | --- | --- | --- | --- |
| E0 | Dependências do `info.yml` | `aculta_events`, `aculta_calendar` sem `openculturas_custom` | **Feito.** Recorrência usa `rlanvin/php-rrule` 3.0 e a local usa `drupal/geofield`, ambos autorizados pelo responsável (D-16). `smart_date` não entra (D-13); nenhum submódulo do OpenCulturas é dependência | `7ae9e54e4`, D-16 |
| E1 | `node.type.event/date/location` e campos | Entidades `Evento`, `Local`, data com exceção; `aculta_event_type` e `aculta_location_type` | **Feito.** Os dois vocabulários existem, com os 17 tipos de local traduzidos. Kernel confirma criação/listagem dos termos e idempotência do update 10003; acesso ao catálogo fica no purpose `eventos`. Runtime de teste confirmou 17 termos e `updatedb:status` sem pendências. Campos informativos em `aculta_event_date`: `field_gratuito`, `field_pricing`, `field_pricing_info` e `field_ticketing`; preço em texto/link externo, sem pagamento. Update 10004 é idempotente; Kernel cobre persistência e atualização. Venda dentro do ACULTA fica em plano separado (D-30). `drupal/geofield` instalado para coordenadas. | `0e6d81397`, `bb0a907c8`, `8668fc957`, `fd70ddd8a`, `b1f897b48`, `10fb091a2`, `82efe4456` |
| E2 | Views `event_catalogue`, `locations`, `related_dates_*` | Catálogo e `/locais` públicos seguem em controllers; views administrativas de locais e datas relacionadas em português | **Feito e ativo no Runtime de teste.** Views com acesso de papel administrador e campos mínimos; `/painel-administrativo/eventos/locais` e `/painel-administrativo/eventos/datas-relacionadas` negam acesso anônimo. Exportação CSV por `CalendarItemSource`/`CalendarOccurrences`, sem endereço/contato, rota protegida por `administer aculta events`. Kernel/Unit da exportação: 2 testes/15 assertions; suíte Events verificada em 2026-10-28: 57 testes/671 assertions/26 depreciações. Importação seletiva das duas Views; status focal sync/DB limpo. `config:status` global ainda mostra diferenças fora desta frente; não houve import global. | `2b9f58b07`, `6a89ea471`, `0f1f384d8` |
| E3 | `DateEventHooks::nodeViewAlter` e `viewsPostExecute` | Listagens sem data passada e cache até a próxima data (`EventDateHooks`, `UpcomingDates`) | **Feito, com desvio.** `viewsPostExecute` virou `#[Hook('views_post_execute')]`; `nodeViewAlter` não tem equivalente em entity view no ACULTA, então a regra vive nos controllers da página e do catálogo. Sem cron (T5). `nodePrepareForm` (S1) não copiado. | `8bd6d832b` |
| E4 | `DateEventHooks::nodePrepareForm` | **Não copiar** (S1). Formulário novo em `aculta_events`, com checagem de acesso | **Feito (D-28), depois reescrito (2026-10-30):** a rota global `/painel-administrativo/eventos/datas/adicionar` foi aposentada; a criação passou a `aculta_events.event_date_add` em `/painel-administrativo/eventos/{aculta_event}/datas/adicionar`, com o Evento no caminho e verificação de vínculo no servidor. Não copia pessoas nem clona parágrafos. Ver ROADMAP do módulo. | `ce0853bbb` |
| E5 | `openculturas_calendar_widget` (allowlist do `source_uri`, S3) | `EmbedSourceAllowlist` | **Feito.** Rota `/calendario/incorporar` no ar no servidor de teste, com `frame-ancestors` explícito e `noindex`; smoke visual em 320, 390 e 1280 px | `7985bc36f`, `224c3925e`, `e81c841f0` |
| E6 | `AddToCal` (regra, sem cópia) | `IcsEventBuilder` próprio, escape RFC 5545, CR/LF como espaço (S2, T10), no `aculta_calendar` | **Feito** para período (séries com `RRULE`, `EXDATE`, `RECURRENCE-ID`) e para uma data (`/eventos/{slug}/data/{id}/calendario.ics`), a partir dos provedores de itens (ADR-031) | `a26bff741`, `e8e8d0151`, `224c3925e`, `c9b6b8e1f` |
| E7 | `openculturas_map` | `aculta_maps` (D-19): Leaflet + OSM público (padrão e fallback); Google Maps opcional; link de rota sem API (ADR-030) | **MP0–MP7 tecnicamente concluídas e módulo ativado no Runtime de teste por ordem explícita.** OSM é o engine configurado; viewport real 1280/320 e dark passaram no Firefox ESR, sem overflow. Kernel cobre marcador aproximado arredondado. D-46 mantém Nominatim para qualquer engine e atribui OpenStreetMap contributors/ODbL nos mapas Google com marcadores. O responsável decidiu não informar IP na política, que não foi editada; aviso/link e pendência editorial permanecem. Nenhuma atualização de banco nem import global de config. | MP0 `9ff609c1e`; MP1 `917cce3f1`; MP2 `cac0efbba`; MP3/MP6 `58359728f`; MP4 `d9ae563d4`; MP5 `33b638ccf`; MP7 `fd5c96526`; F0 `dc032d9e0`; ADR-030 |
| E8 | `openculturas_section` (cor por vocabulário) | `aculta_magazine` e `aculta420` (I-09; quatro tokens fechados) | **Feito e ativado no Runtime de teste por importação parcial dos cinco arquivos próprios do E8**, sem atualizar esquema nem aplicar divergências globais. O form de termo expõe `options_select`; plugin de formatter disponível. Kernel/Unit Magazine 39/609, PHPCS e design foundations passam. Firefox ESR: amostras sintéticas dos quatro tokens em 1280/320 sem overflow; os cinco termos existentes seguem sem cor definida e nenhum conteúdo foi alterado. Código escrito a partir de D-12, sem cópia de código OpenCulturas. | `da3fd99db` |
| E9 | `openculturas_faq` | Submódulo `aculta_faq`: FAQ e blocos editoriais | Código implementado; minuta em `aculta_faq/docs/CONTEUDO-PERGUNTAS-FREQUENTES-RASCUNHO.md` revisada tecnicamente contra rotas/controller atuais (eventos, calendário, locais, mapa e rotas). Nenhum texto editorial da origem foi copiado. Revisão editorial final, aprovação, cadastro e publicação permanecem pendentes. | `f34dbe5b5`, `5b3284bec`, revisão técnica `2026-10-28` |
| E10 | `openculturas_openstreetmap`: assistente administrativo (Find → Select → Review → Save) para importar e enviar dados de local ao OSM (`PushData`) | `aculta_events_openstreetmap`, integração OAuth2 e fluxo revisável por ambiente (D-45) | **Código implementado e submódulo ativo no Runtime de teste.** D-45 permite selecionar explicitamente a API ativa no teste; o endpoint live e as credenciais OAuth estão configurados no checkout de teste, com segredo e chave de cifra somente em `secrets/`. OAuth ainda não foi concluído e nenhuma escrita no OSM foi feita. Cada alteração segue sujeita a revisão e confirmação explícita no assistente. Kernel: 4 testes/53 assertions, 12 depreciações; lint, phpcs e contrato de segredos passam | `5dfb7ac0c`, `3b1fe6572`, `4078138b7`, ativação anterior `0f1f384d8`, seleção explícita `edb7e338f` |
| T5 | `SmartDateUpcomingFormatter` | `DateRangeUpcomingFormatter` com `daterange` do core, cache até a próxima data | **Feito** | `56725c71d` |
| D-15 | Calendário e LMS (decisão própria, sem origem no OC) | `aculta_lms_agenda` e painel do aluno | **Concluído tecnicamente.** `hook_entity_access` exige membership ativa na turma publicada e `takeAccess` no curso publicado; subscriber aplica a checagem às rotas de página e ICS; catálogo filtra pelo access handler e varia cache por usuário; `hook_simple_sitemap_links_alter()` remove do sitemap URLs de eventos vinculados a turmas. Kernel confirma turma correta, outra turma e sem matrícula, rotas diretas e sitemap. | `ac43e7d94`, `9766bb731`, `df76938ae`, `86f247d4e`, `937a07a07`, `7c37023ef`, `1b932d29c`, `dbf2d94e8`, `3be40be71`; Eventos 57/671/26, Agenda LMS 12/227/92 |
| R | Recorrência `smart_date_recur` (replicada, com modificações próprias, D-14) | `aculta_calendar` (validador, expansão) e `aculta_events` (regra e ocorrência) | **Feito na v1.** Regra, expansão e edição de recorrência/ocorrência concluídas; a página do evento agora mostra os links de administração apenas para quem tem permissão, com cache variado por `user.permissions`. Kernel confirma links para administrador e ausência para público | `5f3b61ee7`, `1caa5fc7d`, `dcea19c4a`, `b9144b142`, `c4919bbb3` |

## E10 — revisão de termos e implementação

O que o OpenCulturas faz: um assistente em `/admin/config/services/openculturas_openstreetmap` busca o elemento do
OSM correspondente a um local, deixa escolher quais campos copiar, mostra a revisão e salva. O envio ao OSM usa OAuth
e fica ativo no modo de desenvolvimento.

Decisão D-45 revogou D-26 e autorizou explicitamente o checkout de teste a usar a API ativa do OSM. A busca/importação
é distinta do provedor de mapa e não altera sua escolha. Chamadas OAuth seguem `/integracoes/openstreetmap/...` e o
popup padrão do Portal. O default segue a API de desenvolvimento; neste checkout de teste a API ativa está selecionada
explicitamente, e as credenciais de produção ficam no arquivo de segredos local, nunca em configuração versionada.

Revisão registrada em 2026-10-28: a [política da API do OpenStreetMap](https://operations.osmfoundation.org/policies/api/)
define a API de edição para alterar dados do mapa, não como API somente de leitura; importação e edição devem permanecer
no fluxo explícito de revisão e contribuição, sem consultas em lote. A página de
[copyright e licença do OSM](https://www.openstreetmap.org/copyright) identifica a licença ODbL e exige atribuição.
O ACULTA exibe atribuição e link para essa licença nos dados importados de local. O módulo está ativo no Runtime de
teste, e o `SecretsManager` confirma presença das credenciais e da chave de cifra sem revelar valores. A URL OAuth e
a API resolvidas são as de produção por configuração explícita local. OAuth ainda não foi concluído; nenhum envio real
foi feito. Uma escrita live requer escolha de um objeto real e de uma correção factual para revisão no assistente.

## Não copiar (decidido)

- Mapa Leaflet e tiles do OpenStreetMap (substituídos pelo ADR-014).
- `AddToCal` como classe (reescrito, E6).
- `nodePrepareForm` (S1).
- `LocationPrecisionHooks` (áreas desenhadas, não privacidade; T9).
- `ECA`, `config_devel`, importação de conteúdo de exemplo, cookies, tours, venda e ingressos.
- Blocos e temas do OpenCulturas como cópia: só como referência (ver a frente B).

## Pendências que bloqueiam o próximo passo

1. ~~Confirmar a autorização de `composer require` de `rlanvin/php-rrule`~~ **Resolvido em 2026-10-28:** D-16 autoriza `rlanvin/php-rrule` e `drupal/geofield`, já instalados (`7ae9e54e4`, `b103692c8` e `fd70ddd8a`).
2. ~~**Verificações do Google Maps antes da F4**~~ **Resolvido por D-46:** o ACULTA usa Nominatim para todos os engines, não chama Google Geocoding e mostra atribuição OSM/ODbL se seus resultados forem exibidos no mapa Google. A chave Google Geocoding permanece provisionada, mas sem uso pela aplicação. A transparência editorial da política pública segue como pendência separada. Ver [revisão F0](web/modules/custom/aculta_portal/modules/aculta_maps/docs/REVISAO-TERMOS-MAPAS-2026-10-28.md) e ADR-030.
3. **Rota `/calendario/incorporar`** depende de o purpose `eventos` estar com Domain ativo no servidor de teste.
4. **F6 parcial:** smoke Firefox no Runtime de teste, em 2026-10-28, passou 129/129 verificações em 1280/390/320 px e modo escuro, sem overflow e com ocorrências reais. A captura da página de evento mostra o título duplicado; `EventPageController.php` pertence à frente paralela e não foi alterado. Teclado e zoom 200% ainda não foram verificados; F6 permanece aberta. Artefatos em `/home/piradopirata/aculta-suite-out/f6-events`.
5. **Política de privacidade para os provedores do mapa**: os documentos institucionais agora estão em `/sobre/termos-de-uso` (nó 41) e `/sobre/politica-de-privacidade` (nó 11). O Runtime de teste confirma ambos HTTP 200 e os aliases antigos HTTP 404, sem redirects. O link interno nos Termos foi atualizado, sem mudança substantiva do texto. A Política ainda precisa revisão específica dos provedores; nenhum texto legal novo foi publicado.
6. **Exportação administrativa de datas**: aprovada para a v1 e entregue por `CalendarItemSource`/`CalendarOccurrences`; a rota CSV exporta só campos mínimos e exclui endereço e contatos (`6a89ea471`).

## Próximos passos, em ordem

1. E2: Views e exportação via provedor estão implementadas e ativas no Runtime de teste (`0f1f384d8`); o status focal está sincronizado. Não importar o restante do sync enquanto houver divergências amplas fora do escopo.
2. E7: MP0–MP7 implementadas e módulo ativo no Runtime de teste com OSM. Smoke Firefox passou; revisão editorial da política segue pendente conforme decisão do responsável.
3. E10: fluxo Find → Select → Review → Save implementado e módulo ativo no Runtime (`0f1f384d8`). D-45, commit `edb7e338f`, permite explicitamente API ativa a partir do checkout de teste; credenciais e chave de cifra foram provisionadas apenas em `secrets/` e a seleção live local foi verificada. OAuth e uma edição factual revisada ainda não foram concluídos.
4. R: regra, ocorrência e links de administração concluídos (`c4919bbb3`).
5. F5: **feito** no `aculta_calendar` (D-20); D6 também concluída (`9800e0d62`): agenda, grade do mês, dia, `.ics`, embed e visão interativa servidos por `eventos` e `main`, com canonical no host do purpose `eventos` definido pelo ACULTA Deployer. Runtime de teste: seis rotas HTTP 200 nos dois hosts; canonical único e sitemap inclui a URL do calendário somente sob `eventos.aculta.toca.net.br`.
6. F6 permanece parcial (129/129 no smoke Firefox); resolver o título duplicado pela frente dona e verificar zoom 200%/teclado antes de avançar para F8/F9.
7. **F7 concluída (D-44):** página de evento e sitemap geram URLs pelos hosts de ambiente mapeados em `domain_transition.entries` do ACULTA Deployer. No teste, index e filho de eventos retornam HTTP 200 e `X-Robots-Tag: noindex, nofollow, noarchive`; o filho de eventos lista apenas URLs de `eventos.aculta.toca.net.br`, e a página declara canonical para o mesmo host. `validate-final-sitemap.php` PASS. Código: `721e9453e`.

## Painéis e opções administrativas do OpenCulturas (ADM)

Escopo: as telas de administração e as opções de configuração do OpenCulturas. Não é importação. Mapeamento
feito sobre as rotas (`/admin/...`), os formulários de configuração e as views administrativas do pacote, e
sobre as 82 telas que o ACULTA já tem em `/painel-administrativo`.

Regra de caminho: no ACULTA, toda tela administrativa fica em `/painel-administrativo/...` (`AGENTS.md`, seção de
integrações). Não há `/admin` nas telas novas.

### Mapeamento

| OpenCulturas | Função | ACULTA hoje | Ação |
| --- | --- | --- | --- |
| `flagged_content` (`/admin/content/reports`) | Denúncias de qualquer conteúdo | `/painel-administrativo/moderacao/denuncias` (geral) e `/painel-administrativo/forum/denuncias` (fórum) | ADM1: painel geral de denúncias concluído em `aculta_ugc` |
| `moderated_content` | Conteúdo moderado | `/painel-administrativo/moderacao/denuncias` com filtro de status/área | ADM1: painel unificado com filtros por purpose e status |
| `legal_terms`, `legal_users` | Histórico de termos e aceites | `/painel-administrativo/termos/aceites` | Já existe. Sem ação |
| `user_admin_people` | Lista de pessoas | Não há painel geral (há `contas/nao-confirmadas`) | ADM2: lista administrativa mínima, sem dado sensível; revisão de LGPD antes |
| `media`, `media_library`, `files` (uso de arquivo) | Mídia e uso de arquivos | `/painel-administrativo/imagens` e `/painel-administrativo/imagens/{file}/usos` | ADM3: consulta somente leitura, restrita a quem administra imagens (commit `d80b4a748`) |
| `oc_comments` | Comentários de todas as áreas | Só fórum (`forum/comentarios/...`) | Coberto por ADM1 quando houver comentário fora do fórum |
| `faq` (`/admin/content/faq`) | Perguntas frequentes | Não existe | ADM4: FAQ editável (I-15), com painel próprio |
| `block_content` (`/admin/content/block`) | Blocos de conteúdo editáveis | Não existe | ADM4: avaliar com o responsável; pode ficar como editorial highlight |
| `content` (`/admin/content/node`) | Lista geral de conteúdo, com filtro | Drupal padrão, sem painel do ACULTA | ADM5: painel geral com filtro por área, status e autor, sem exportar dado pessoal |
| `search_api` (índice) | Estado do índice de busca | `/painel-administrativo/indice` (ADM6; geração ativa, última reconstrução, pendências, só leitura) e Drush (`aculta_index`, commit `867bb05da`) |
| `related_dates_export` (CSV, JSON, XML) | Exportação de datas | `aculta_calendar` após CA0/CA2 | Aprovada para v1 via provedor (S5, ADR-031); não criar exportação paralela em `aculta_events`; campos mínimos, sem telefone, e-mail ou endereço |
| `watchdog` | Log do sistema | Log do Drupal | Não copiar: é operação, não painel de conteúdo |
| `openculturas_map` (configuração) | Centro, zoom e ícone do mapa | Não existe | ADM7: configuração do mapa do Google (ADR-014), por ambiente, sem chave no formulário |
| `openculturas_calendar_widget` (configuração) | Limitar acesso, sites permitidos, CSS, cabeçalho e rodapé do embed | Só a allowlist em código (E5) | ADM8: painel de embed com a mesma allowlist; CSS e HTML livres não entram (S3) |
| `openculturas_address_links` (configuração) | URL base de rotas e transporte | Não existe | ADM7: configuração de "como chegar" (I-11), no mesmo painel de eventos |
| `openculturas_custom` (configuração) | Classes permitidas e assinatura de e-mail | Assinatura já existe em `aculta_mailer` | Não copiar: a assinatura fica no mailer |
| `eca-notification-recipient` (CRUD) | Destinatários de aviso | Não existe | Já previsto (C-01 e D-03): entidade de destinatários do `aculta_ugc` |
| `openculturas_openstreetmap` (configuração e assistente) | OSM | Não existe | Fora (E10) |
| `tour` e `dark_mode`, `cookies` | Tours e preferências | Não existe / tokens e aceite único | Não copiar |

### Microfases de painel (ADM)

Cada painel é uma microfase, com permissão própria, teste Kernel de acesso negado para usuário comum e um item no
índice de administração.

| Id | Painel | Módulo dono | Dependência | Situação |
| --- | --- | --- | --- | --- |
| ADM1 | Denúncias e conteúdo moderado, todas as áreas | `aculta_ugc` | Plano U1–U4 de `aculta_ugc` | **Implementado**: `/painel-administrativo/moderacao/denuncias` e `/painel-administrativo/moderacao/denuncias/{aculta_ugc_report}/decidir` com julgamento individual e em lote, restrito a moderadores de escopo. |
| ADM2 | Lista administrativa de pessoas (mínima) | `aculta_user` | D-22 | **Implementado**, `701fa1d87`: `/painel-administrativo/pessoas`, restrita a `administer users`; só nome do perfil, apelido e situação. Kernel: rota negada a usuário comum e conteúdo minimizado. |
| ADM3 | Uso de arquivos em `imagens` | `aculta_image` | — | **Feito**, commit `d80b4a748` |
| ADM4 | FAQ e blocos editáveis | `aculta_faq` | D-23 | **Implementado**, `f34dbe5b5`: painel `/painel-administrativo/faq`; pergunta/resposta e bloco reutilizável limitados ao formato `aculta_editorial`; Kernel cobre XSS e negação para usuário comum. Minuta editorial (`5b3284bec`) revisada tecnicamente; aprovação editorial e cadastro/publicação continuam pendentes. |
| ADM5 | Conteúdo geral com filtros | `aculta_content_admin` | D-24 e allowlist confirmada pelo responsável | **Implementado**, `5804517b9`: `/painel-administrativo/conteudo` descobre Views de entidades de conteúdo e usa allowlist para coleções servidas por controllers. Checa o acesso nativo de cada rota; só lista áreas permitidas e nega o painel se nenhuma estiver acessível. Kernel cobre View com/sem permissão e FAQ. |
| ADM6 | Estado do índice de busca (só leitura) | `aculta_index` | — | **Feito**, commit `867bb05da` |
| ADM7 | Configuração de mapa (provedor, estado da chave, centro, zoom) e URL de rota configurável | `aculta_maps` | ADR-030 (D-18) e F4 | Implementado em `cac0efbba`; painel `/painel-administrativo/mapas`, Kernel cobre acesso, validação da URL e ausência de credencial na configuração exportável |
| ADM8 | Painel de embed do calendário | `aculta_calendar` | E5 com rota (F5 concluída) | Implementado como consulta somente leitura dos hosts de teste e produção declarados em `aculta_deployer/config/deploy.json`; sem edição de allowlist. Kernel cobre acesso negado a usuário comum e acesso autorizado. Commit `1a423c042`. |

ADM2, ADM4 e ADM5 têm decisões registradas em D-22, D-23 e D-24. A confirmação posterior de ADM5 inclui rotas de listagem de controllers e requer allowlist explícita.

## Ampliação aprovada após nova auditoria do pacote 3.1.5 (2026-10-29)

[Inventário, matriz OC315-01 a OC315-16 e gates](AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md). As fases E0–E10 acima continuam com seu status histórico; a tabela seguinte **não** declara código concluído.

| Onda | Prioridade | Donos | Entrega prevista | Dependência / gate |
| --- | --- | --- | --- | --- |
| OC-A | P0 | Events, Index, Portal | Auditoria ACL para locais, consulta paginada/benchmark de eventos, busca cultural respeitando purpose e grants | Kernel A/B, resultados privados excluídos e teste de escala |
| OC-B | P1 | Events, Maps, tema | Acessibilidade estruturada, horário/agenda de locais, eventos online/híbridos, situação/idade/avisos, SDCs com consumidores | esquema idempotente, contrato SDC e WCAG AA |
| OC-C | P1 | User, Studio, UGC, Mailer | Organização distinta de conta/perfil, reivindicação com moderação, salvos, recomendações, avisos por purpose | D-01/D-03, cache privado e migração segura |
| OC-D | P2 | Magazine, Document Viewer, Image, tema | Clipping, apoiadores, metadados de download, override de teasers, arquivo opcional | autorização de marcas, revisão de licenças e consumers |
| OC-E | P2 | Maps, Portal | Horários estruturados para OSM e comparação do breadcrumb existente | OC-B; não duplicar serviço |
| OC-F | fechamento | Donos | Verificação integrada e rastreabilidade do 3.1.5 | testes efetivamente executados + Firefox ESR |

Proibições mantidas: sem cópia de `opcult` ou templates Twig, sem ECA, `smart_date`, Facets ou Office Hours adicionados sem decisão, nem `config:import` global. Separar código presente de integração funcional e homologação no Runtime. **Não reescrever `aculta_index`**, que já fornece índice transversal e providers próprios.

## Ondas complementares aprovadas - OpenCulturas 3.1.5 (2026-10-29)

Fonte de verdade: [auditoria 3.1.5](AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md). As fases E0-E10 permanecem com seus estados históricos, e as ondas abaixo são backlog novo, não execução de Runtime.

- [ ] **OC-A (P0, Index/Events/Portal):** Search API para descoberta cultural de eventos e locais com grants, purpose e testes intercontas; corrigir desempenho, limites e paginação da expansão de recorrências; revisar publicidade/acesso de locais e coordenadas numéricas zero.
- [ ] **OC-B (P1, Events/Maps/tema):** acessibilidade e horários estruturados; modalidade presencial/virtual/híbrida, idade mínima, segurança, abertura de portas e status da data; campos com update idempotente e consumidores reais de SDCs; teclado, zoom 200% e 320 px.
- [ ] **OC-C (P1, User/Studio/UGC/Mailer):** vínculo verificado pessoa/perfil/organização, reivindicação com padrão fechado e moderação, salvos/recomendações privados com cache por usuário, fila única e destinatários por purpose. Não criar papel genérico (D-01), nem instalar ECA.
- [ ] **OC-D (P2, Magazine/Image/Document Viewer/tema):** menções na imprensa, apoiadores autorizados, formatos/tamanhos de download, teaser editorial e arquivo histórico somente se houver consumidor real. Licença opcional (D-10).
- [ ] **OC-E (P2, Maps/Portal):** comparar Office Hours/OSM e breadcrumbs com funcionalidades existentes antes de portar, sem duplicidade.
- [ ] **OC-F (aceite):** testes Unit/Kernel/Functional, cache/acesso, proveniência e revisão visual com UI Patterns, Firefox ESR, acessibilidade e Olivero quando aplicável.

Inventário retificado para `profile/config/install` do 3.1.5: **42 Views** (7 desabilitadas), **9 vocabulários**, **3 workflows**, **19 tipos de parágrafo**, **3 facets** e **5 flags**. `archive`, `glossary` e `content_recent` estão desabilitadas. Nenhuma evidência confirmou funcionalidade de polígonos ou declaração de uso de IA nesta versão: estudar separadamente, sem contar como paridade. Facets e Office Hours são candidatos, **não dependências automaticamente autorizadas**. Respeitar D-10, D-11, D-13/D-14, D-17/D-20 e D-45.
