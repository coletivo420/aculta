# Plano de incorporação do OpenCulturas no ACULTA — status

Documento vivo. Mostra o que do código do OpenCulturas já foi reescrito no ACULTA, o que falta e o que foi
decidido não copiar. Fontes: `web/modules/custom/aculta_portal/modules/aculta_events/docs/AUDITORIA-OPENCULTURAS.md`
(achados S1–S8, T1–T14), `OPENCULTURAS-INCORPORACAO-CODIGO.md` (C-01 a C-10) e
`docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` (D-01 a D-15).

Regra de cópia: nenhum arquivo é copiado como está. Cada trecho derivado tem cabeçalho de atribuição e uma linha em
`aculta_events/docs/ORIGEM-OPENCULTURAS.md`. Repositório em GPLv3 e origem em GPL-2.0-or-later: compatível.

## Status por microfase

| Id | Origem no OpenCulturas | Destino no ACULTA | Situação | Evidência |
| --- | --- | --- | --- | --- |
| E0 | Dependências do `info.yml` | `aculta_events`, `aculta_calendar` sem `openculturas_custom` | **Feito.** Recorrência usa `rlanvin/php-rrule` 3.0 e a local usa `drupal/geofield`, ambos autorizados pelo responsável (D-16). `smart_date` não entra (D-13); nenhum submódulo do OpenCulturas é dependência | `7ae9e54e4`, D-16 |
| E1 | `node.type.event/date/location` e campos | Entidades `Evento`, `Local`, data com exceção; `aculta_event_type` e `aculta_location_type` | **Feito na parte de tipos.** Os dois vocabulários existem, com os 17 tipos de local do OpenCulturas em português. Campos de venda seguem fora (`drupal/geofield` instalado para as coordenadas). | `0e6d81397`, `bb0a907c8`, `8668fc957`, `fd70ddd8a` |
| E2 | Views `event_catalogue`, `locations`, `related_dates_*` | Catálogo e lista de locais em controller (desvio registrado no roadmap); views de datas relacionadas e exportação pendentes | **Parcial.** Catálogo e `/locais` textuais feitos. Faltam as views de datas relacionadas e a exportação administrativa, que continua sem decisão do responsável (S5) | `0174e641c`, `fd70ddd8a` |
| E3 | `DateEventHooks::nodeViewAlter` e `viewsPostExecute` | Listagens sem data passada e cache até a próxima data (`EventDateHooks`, `UpcomingDates`) | **Feito, com desvio.** `viewsPostExecute` virou `#[Hook('views_post_execute')]`; `nodeViewAlter` não tem equivalente em entity view no ACULTA, então a regra vive nos controllers da página e do catálogo. Sem cron (T5). `nodePrepareForm` (S1) não copiado. | `8bd6d832b` |
| E4 | `DateEventHooks::nodePrepareForm` | **Não copiar** (S1). Pré-preenchimento só com checagem de acesso, se for necessário | **Decidido: não copiado.** Pedido pendente se o responsável quiser o recurso | — |
| E5 | `openculturas_calendar_widget` (allowlist do `source_uri`, S3) | `EmbedSourceAllowlist` | **Feito** (lógica). Rota `/calendario/incorporar` pendente: depende de F1 com o purpose ativo | `7985bc36f` |
| E6 | `AddToCal` (regra, sem cópia) | `IcsEventBuilder` próprio, escape RFC 5545, CR/LF como espaço (S2, T10) | **Feito.** Exportação de página pendente | `a26bff741`, `e8e8d0151` |
| E7 | `openculturas_map` | Google Maps (ADR-014), sem Leaflet nem OSM | **Parcial.** Geocodificação e mapa carregados na página, com lista textual e aviso de privacidade; a chave fica em `secrets`/ambiente. Faltam o agrupamento de marcadores e o link da política de privacidade. As verificações de termos, cobrança e LGPD do responsável seguem pendentes | `f761d8593`, ADR-014 |
| E8 | `openculturas_section` (cor por vocabulário) | Frente B, B2 (cores fechadas) | **Feito** no tema. Consumidor na Revista pendente | `15a3f0488` |
| E9 | `openculturas_faq` | FAQ, opcional (I-15) | **Não iniciado.** Só se o responsável pedir | — |
| E10 | `openculturas_openstreetmap`: assistente administrativo (Find → Select → Review → Save) que importa dados de um local do OpenStreetMap e, em modo de desenvolvimento, envia alterações ao próprio OSM (`PushData`) | Importação de local a partir do OSM, no admin de `aculta_events`, **somente se aprovada** | **Não iniciado. Fora da v1.** Bloqueado pelas decisões abaixo | — |
| T5 | `SmartDateUpcomingFormatter` | `DateRangeUpcomingFormatter` com `daterange` do core, cache até a próxima data | **Feito** | `56725c71d` |
| D-15 | Calendário e LMS (decisão própria, sem origem no OC) | `aculta_lms_agenda` e painel do aluno | **Parcial.** Falta confirmar acesso por matrícula no `field_turma` | `ac43e7d94`, `9766bb731` |
| R | Recorrência `smart_date_recur` (replicada, com modificações próprias, D-14) | `aculta_calendar` (validador, expansão) e `aculta_events` (regra e ocorrência) | **Feito na v1.** Regra em `EventDate` (`field_regra`, `field_limite_meses`), expansão na janela, interface da regra em forma canônica e edição de uma ocorrência (cancelar, reagendar, restaurar) por início original. Falta só o link para os formulários na página do evento (F6) | `5f3b61ee7`, `1caa5fc7d`, `dcea19c4a`, `b9144b142` |

## E10 — importação de local a partir do OpenStreetMap (decisões antes de qualquer código)

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

Decisões necessárias: (a) a importação de OSM é desejada ou o local é só cadastrado pela organização? (b) aceita o
parecer de ODbL? (c) o envio ao OSM fica fora de escopo? Sem essas respostas, o E10 permanece como item de pós-v1.

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
6. **Exportação administrativa de datas**: sem decisão do responsável se deve existir (S5). O hook de listagem não exporta nada.

## Próximos passos, em ordem

1. E2: views de datas relacionadas (sem telefone nem e-mail, S5) e, se o responsável decidir, a exportação administrativa sob `/painel-administrativo/...`.
2. R: interface de edição da regra e de uma ocorrência (D-14), com teste de expansão no fuso de Brasília.
3. F5: visões de agenda e de mês, e a rota `/calendario/incorporar` quando o purpose estiver ativo.
4. F4 (E7): fechar o agrupamento de marcadores, o link da política de privacidade e as verificações da pendência 2.
5. Teste Kernel de acesso por purpose dos tipos novos (não localizado até aqui).
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
| `media`, `media_library`, `files` (uso de arquivo) | Mídia e uso de arquivos | `/painel-administrativo/imagens` (catálogo, quarentena, limpeza, relatório) | ADM3: acrescentar a visão "onde este arquivo é usado" |
| `oc_comments` | Comentários de todas as áreas | Só fórum (`forum/comentarios/...`) | Coberto por ADM1 quando houver comentário fora do fórum |
| `faq` (`/admin/content/faq`) | Perguntas frequentes | Não existe | ADM4: FAQ editável (I-15), com painel próprio |
| `block_content` (`/admin/content/block`) | Blocos de conteúdo editáveis | Não existe | ADM4: avaliar com o responsável; pode ficar como editorial highlight |
| `content` (`/admin/content/node`) | Lista geral de conteúdo, com filtro | Drupal padrão, sem painel do ACULTA | ADM5: painel geral com filtro por área, status e autor, sem exportar dado pessoal |
| `search_api` (índice) | Estado do índice de busca | Só Drush (`aculta_index`) | ADM6: painel de estado do índice (última reconstrução, pendências), só leitura |
| `related_dates_export` (CSV, JSON, XML) | Exportação de datas | Não existe | Entra com E2, com campos mínimos (S5), sem telefone nem e-mail |
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

| Id | Painel | Módulo dono | Dependência |
| --- | --- | --- | --- |
| ADM1 | Denúncias e conteúdo moderado, todas as áreas | `aculta_ugc` | Plano U1–U4 de `aculta_ugc` |
| ADM2 | Lista administrativa de pessoas (mínima) | `aculta_user` | Revisão de LGPD |
| ADM3 | Uso de arquivos em `imagens` | `aculta_image` | — |
| ADM4 | FAQ e blocos editáveis | `aculta_portal` (decisão sobre blocos) | Decisão do responsável |
| ADM5 | Conteúdo geral com filtros | `aculta_portal` | Lista de áreas e papéis |
| ADM6 | Estado do índice de busca (só leitura) | `aculta_index` | — |
| ADM7 | Configuração de mapa e de "como chegar" | `aculta_events` | ADR-014 e F4 |
| ADM8 | Painel de embed do calendário | `aculta_calendar` | E5 com rota (F5) |

### Decisões necessárias antes de ADM2, ADM4 e ADM5

1. **ADM2:** quais campos aparecem na lista de pessoas? Recomendação: nome de exibição, apelido e situação da conta, sem e-mail nem documento.
2. **ADM4:** FAQ e blocos editáveis são necessários agora, ou ficam para depois da v1?
3. **ADM5:** o painel geral de conteúdo deve listar todas as áreas ou só as que o usuário pode moderar?
