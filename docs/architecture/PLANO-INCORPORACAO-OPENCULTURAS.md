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
| E1 | `node.type.event/date/location` e campos | Entidades `Evento`, `Local`, data com exceção; `aculta_event_type` e `aculta_location_type` | **Feito.** Os dois vocabulários existem, com os 17 tipos de local traduzidos. Campos informativos em `aculta_event_date`: `field_gratuito`, `field_pricing`, `field_pricing_info` e `field_ticketing`; preço em texto/link externo, sem pagamento. Update 10004 é idempotente; Kernel cobre persistência e atualização. Venda dentro do ACULTA fica em plano separado (D-30). `drupal/geofield` instalado para coordenadas. | `0e6d81397`, `bb0a907c8`, `8668fc957`, `fd70ddd8a`, `b1f897b48`, `10fb091a2` |
| E2 | Views `event_catalogue`, `locations`, `related_dates_*` | Catálogo e `/locais` públicos seguem em controllers; views administrativas de locais e datas relacionadas em português | **Parcial.** Views administrativas de locais e datas relacionadas criadas com acesso de papel administrador e campos mínimos. Exportação administrativa aprovada para a v1 via provedor do `aculta_calendar` (ADR-031), pendente da API CA0/CA2; não criar exportação paralela em `aculta_events`. Configuração fica versionada; ativação no Runtime aguarda o fluxo autorizado de configuração | `2b9f58b07` |
| E3 | `DateEventHooks::nodeViewAlter` e `viewsPostExecute` | Listagens sem data passada e cache até a próxima data (`EventDateHooks`, `UpcomingDates`) | **Feito, com desvio.** `viewsPostExecute` virou `#[Hook('views_post_execute')]`; `nodeViewAlter` não tem equivalente em entity view no ACULTA, então a regra vive nos controllers da página e do catálogo. Sem cron (T5). `nodePrepareForm` (S1) não copiado. | `8bd6d832b` |
| E4 | `DateEventHooks::nodePrepareForm` | **Não copiar** (S1). Formulário novo em `aculta_events`, com checagem de acesso | **Feito (D-28):** `/painel-administrativo/eventos/datas/adicionar` preenche somente a referência de evento quando o usuário tem acesso de atualização; IDs inexistentes e eventos não editáveis deixam o campo em branco. Não copia pessoas nem clona parágrafos. Kernel: 58 testes da suíte, 619 assertions, 27 depreciações | `ce0853bbb` |
| E5 | `openculturas_calendar_widget` (allowlist do `source_uri`, S3) | `EmbedSourceAllowlist` | **Feito.** Rota `/calendario/incorporar` no ar no servidor de teste, com `frame-ancestors` explícito e `noindex`; smoke visual em 320, 390 e 1280 px | `7985bc36f`, `224c3925e`, `e81c841f0` |
| E6 | `AddToCal` (regra, sem cópia) | `IcsEventBuilder` próprio, escape RFC 5545, CR/LF como espaço (S2, T10), no `aculta_calendar` | **Feito** para período (séries com `RRULE`, `EXDATE`, `RECURRENCE-ID`) e para uma data (`/eventos/{slug}/data/{id}/calendario.ics`), a partir dos provedores de itens (ADR-031) | `a26bff741`, `e8e8d0151`, `224c3925e`, `c9b6b8e1f` |
| E7 | `openculturas_map` | `aculta_maps` (D-19): Leaflet + OSM público (padrão e fallback); Google Maps opcional; link de rota sem API (ADR-030) | **Pendente após conferência do código:** `aculta_maps` ainda só tem documentação; o controller e a geocodificação continuam em `aculta_events` e usam Google. Ainda faltam a migração MP0/MP1, engine OSM, agrupamento de marcadores e link público para a política de privacidade. Não alterar o mapa até a política ter URL publicada; nenhuma verificação visual OSM foi realizada | ADR-030, D-19 |
| E8 | `openculturas_section` (cor por vocabulário) | Frente B, B2 (cores fechadas) | **Feito** no tema. Consumidor na Revista pendente | `15a3f0488` |
| E9 | `openculturas_faq` | Submódulo `aculta_faq`: FAQ e blocos editoriais | **Código implementado** com revisão do fluxo e atribuição; não havia conteúdo FAQ editorial versionado na origem para migrar. Revisão/cadastro do conteúdo ACULTA continua pendente | `f34dbe5b5` |
| E10 | `openculturas_openstreetmap`: assistente administrativo (Find → Select → Review → Save) que importa dados de um local do OpenStreetMap e, em modo de desenvolvimento, envia alterações ao próprio OSM (`PushData`) | Importação e envio em fluxo revisável, com API do ambiente correspondente (D-26) | **Decidido (D-26), implementação pendente e fora da v1.** `config/secrets-contract.json` ainda não define credenciais OSM. Não foi feito envio. O teste autorizado depende de credenciais próprias da API de desenvolvimento e dados fictícios; não se deve usar a API de produção nesta execução | D-26; ver revisão de termos abaixo |
| T5 | `SmartDateUpcomingFormatter` | `DateRangeUpcomingFormatter` com `daterange` do core, cache até a próxima data | **Feito** | `56725c71d` |
| D-15 | Calendário e LMS (decisão própria, sem origem no OC) | `aculta_lms_agenda` e painel do aluno | **Acesso confirmado**: provedor exige membership na turma publicada e acesso ao curso publicado; Kernel cobre turma correta, turma diferente e ausência de matrícula | `ac43e7d94`, `9766bb731`, `df76938ae` |
| R | Recorrência `smart_date_recur` (replicada, com modificações próprias, D-14) | `aculta_calendar` (validador, expansão) e `aculta_events` (regra e ocorrência) | **Feito na v1.** Regra em `EventDate` (`field_regra`, `field_limite_meses`), expansão na janela, interface da regra em forma canônica e edição de uma ocorrência (cancelar, reagendar, restaurar) por início original. Falta só o link para os formulários na página do evento (F6) | `5f3b61ee7`, `1caa5fc7d`, `dcea19c4a`, `b9144b142` |

## E10 — revisão de termos e implementação pendente

O que o OpenCulturas faz: um assistente em `/admin/config/services/openculturas_openstreetmap` busca o elemento do
OSM correspondente a um local, deixa escolher quais campos copiar, mostra a revisão e salva. O envio ao OSM usa OAuth
e fica ativo no modo de desenvolvimento.

Não entra na v1 por quatro motivos, e cada um vira decisão antes do código:

1. **Licença dos dados.** Os dados do OpenStreetMap são sob ODbL, que exige atribuição e, em bases derivadas,
   compartilhamento pela mesma licença. Parecer antes de copiar dados para o banco do ACULTA.
2. **Integração externa.** Chamadas autenticadas a terceiros seguem o padrão de integrações do ACULTA
   (`/integracoes/<nome>/<ação>` e popup), e não a rota administrativa do OpenCulturas.
3. **Escrita no OSM.** O envio de alterações ao OSM não deve existir sem decisão própria. Recomendação: não implementar.
4. **Coerência com D-08.** O ACULTA usa Google Maps para o mapa. Importar dados do OSM para o local é outro serviço.

Revisão registrada em 2026-10-28: a [política da API do OpenStreetMap](https://operations.osmfoundation.org/policies/api/)
define a API de edição para alterar dados do mapa, não como API somente de leitura; importação e edição devem permanecer
no fluxo explícito de revisão e contribuição, sem consultas em lote. A página de
[copyright e licença do OSM](https://www.openstreetmap.org/copyright) identifica a licença ODbL e exige atribuição.
O ACULTA deve exibir atribuição e link para essa licença nos dados/mapas derivados. D-26 autorizou a funcionalidade,
mas nenhum envio real foi feito nesta execução. Antes de testar envio, provisionar credenciais separadas para
`master.apis.dev.openstreetmap.org` em `secrets/` e usar apenas dados fictícios; a
[documentação do servidor de desenvolvimento](https://wiki.openstreetmap.org/wiki/Dev_Server_Account) confirma que
ele é isolado dos dados de produção. Produção permanece fora desta execução.

## Não copiar (decidido)

- Mapa Leaflet e tiles do OpenStreetMap (substituídos pelo ADR-014).
- `AddToCal` como classe (reescrito, E6).
- `nodePrepareForm` (S1).
- `LocationPrecisionHooks` (áreas desenhadas, não privacidade; T9).
- `ECA`, `config_devel`, importação de conteúdo de exemplo, cookies, tours, venda e ingressos.
- Blocos e temas do OpenCulturas como cópia: só como referência (ver a frente B).

## Pendências que bloqueiam o próximo passo

1. ~~Confirmar a autorização de `composer require` de `rlanvin/php-rrule`~~ **Resolvido em 2026-10-28:** D-16 autoriza `rlanvin/php-rrule` e `drupal/geofield`, já instalados (`7ae9e54e4`, `b103692c8` e `fd70ddd8a`).
2. **Verificações do Google Maps antes da F4**: termos, cobrança, lista de hosts no deployer e LGPD (ADR-014).
3. **Rota `/calendario/incorporar`** depende de o purpose `eventos` estar com Domain ativo no servidor de teste.
4. **Revisão visual de eventos** não tem registro (F6).
5. **Página pública da política de privacidade**: o aviso do mapa já existe, mas o link para a política depende dessa página, que o site ainda não tem.
6. **Exportação administrativa de datas**: aprovada para a v1 via provedor de itens do `aculta_calendar` (S5, D-20, ADR-031). Implementar depois da API CA0/CA2; `aculta_events` não cria exportação paralela.

## Próximos passos, em ordem

1. E2: Views estão implementadas sem telefone, e-mail ou endereço. A exportação administrativa aprovada passa pelo provedor do `aculta_calendar`; continua pendente de API estruturada (CA0/CA2). Não criar exportação paralela.
2. E7: iniciar MP0/MP1 em `aculta_maps` conforme D-19; só depois fechar OSM, agrupamento e link da política quando a URL pública existir. O código atual em `aculta_events` ainda usa Google.
3. E10: implementar o fluxo Find → Select → Review → Save e o cliente da API de desenvolvimento com padrão seguro; bloqueado para teste de envio até credenciais de teste serem provisionadas no contrato de segredos.
4. R: regra e ocorrência têm interface e Kernel; o link na página do evento pertence à F6.
5. F5: **feito** no `aculta_calendar` (D-20): agenda, grade do mês, dia, `.ics` e `/calendario/incorporar`, com o `aculta_events` como provedor de itens. Falta o calendário no `main` (D6).
6. F6, F7, F8, F9.

## Painéis e opções administrativas do OpenCulturas (ADM)

Escopo: as telas de administração e as opções de configuração do OpenCulturas. Não é importação. Mapeamento
feito sobre as rotas (`/admin/...`), os formulários de configuração e as views administrativas do pacote, e
sobre as 82 telas que o ACULTA já tem em `/painel-administrativo`.

Regra de caminho: no ACULTA, toda tela administrativa fica em `/painel-administrativo/...` (`AGENTS.md`, seção de
integrações). Não há `/admin` nas telas novas.

### Mapeamento

| OpenCulturas | Função | ACULTA hoje | Ação |
| --- | --- | --- | --- |
| `flagged_content` (`/admin/content/reports`) | Denúncias de qualquer conteúdo | Só fórum: `/painel-administrativo/forum/denuncias` | ADM1: painel geral de denúncias, em `aculta_ugc` (plano U4) |
| `moderated_content` | Conteúdo moderado | Só fórum e wiki por workflow | ADM1: mesmo painel, com filtro por área |
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
| ADM1 | Denúncias e conteúdo moderado, todas as áreas | `aculta_ugc` | Plano U1–U4 de `aculta_ugc` | Planejado |
| ADM2 | Lista administrativa de pessoas (mínima) | `aculta_user` | D-22 | **Implementado**, `701fa1d87`: `/painel-administrativo/pessoas`, restrita a `administer users`; só nome do perfil, apelido e situação. Kernel: rota negada a usuário comum e conteúdo minimizado. |
| ADM3 | Uso de arquivos em `imagens` | `aculta_image` | — | **Feito**, commit `d80b4a748` |
| ADM4 | FAQ e blocos editáveis | `aculta_faq` | D-23 | **Implementado**, `f34dbe5b5`: painel `/painel-administrativo/faq`; pergunta/resposta e bloco reutilizável limitados ao formato `aculta_editorial`; Kernel cobre XSS e negação para usuário comum. Cadastro/revisão editorial de conteúdo continua pendente. |
| ADM5 | Conteúdo geral com filtros | `aculta_content_admin` | D-24 e allowlist confirmada pelo responsável | **Implementado**, `5804517b9`: `/painel-administrativo/conteudo` descobre Views de entidades de conteúdo e usa allowlist para coleções servidas por controllers. Checa o acesso nativo de cada rota; só lista áreas permitidas e nega o painel se nenhuma estiver acessível. Kernel cobre View com/sem permissão e FAQ. |
| ADM6 | Estado do índice de busca (só leitura) | `aculta_index` | — | **Feito**, commit `867bb05da` |
| ADM7 | Configuração de mapa (provedor, chave, centro, zoom) e URL de rota configurável | `aculta_events` | ADR-030 (D-18) e F4 | Pendente da F4 |
| ADM8 | Painel de embed do calendário | `aculta_calendar` | E5 com rota (F5) | Pendente da F5 |

ADM2, ADM4 e ADM5 têm decisões registradas em D-22, D-23 e D-24. A confirmação posterior de ADM5 inclui rotas de listagem de controllers e requer allowlist explícita.
