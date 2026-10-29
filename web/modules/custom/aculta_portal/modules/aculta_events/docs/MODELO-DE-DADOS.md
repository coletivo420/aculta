# Modelo de dados — ACULTA Eventos

Três conceitos, separados como no OpenCulturas 3.1.4 (tipos `event`, `date` e `location`).
Nomes de máquina seguem a decisão D4 do `PLANO-IMPLANTACAO.md`.

## Evento — `aculta_event`

Descrição reutilizável. Um evento pode ter várias datas.

| Campo | Tipo Drupal | Origem OpenCulturas | Observação |
| --- | --- | --- | --- |
| `title` | string | `title` | Obrigatório |
| `field_subtitulo` | string | `field_subtitle` | Opcional |
| `body` | text_long | `body` | Descrição |
| `field_tipo` | entity_reference (`aculta_event_type`) | `field_sub_type` / `event_type` | Vocabulário próprio |
| `field_categoria` | entity_reference | `field_category` | Só se a taxonomia do ACULTA não bastar |
| `field_classificacao_etaria` | integer | `field_min_age` | Faixa indicativa, opcional |
| `field_acessibilidade` | list/entity | `field_accessibility` | Recursos de acessibilidade |
| `field_url` | link | `field_url` | Site do evento, opcional |
| `field_imagem` | image (com foco) | `field_mood_image` | Usar `aculta_image` |
| `field_organizador` | entity_reference (`user`/perfil) | `field_people_reference` | Via `aculta_user`, opt-in |
| `field_status` | list_string | `field_status` | Rascunho, publicado, cancelado |

## Data (ocorrência) — `aculta_event_date`

Uma ocorrência do evento, em um local, em um intervalo.

| Campo | Tipo Drupal | Origem OpenCulturas | Observação |
| --- | --- | --- | --- |
| `field_evento` | entity_reference (`aculta_event`) | referência do `date` ao `event` | Obrigatório |
| `field_quando` | daterange (core) | `field_date` (`smartdate`) | Início e fim. Sem contrib: a decisão de 2026-10-28 dispensou `smart_date` (dependência de `simshaun/recurr` conflita com `doctrine/collections` 3.x) |
| `field_dia_inteiro` | boolean | `field_date` (`smartdate`) | Dia inteiro. Quando marcado, só a data de `field_quando` é usada e o fim é exclusivo (RFC 5545) |
| `field_gratuito` | boolean | `field_forfree` | Entrada gratuita; não representa uma operação de pagamento |
| `field_pricing` | string (255) | `field_pricing` | Texto informativo de preço, sem cobrança dentro do ACULTA (D-29) |
| `field_pricing_info` | string_long (texto simples) | `field_pricing_info` | Detalhes adicionais do preço, sem HTML livre (D-29) |
| `field_ticketing` | link externo | `field_ticketing` | Link externo para compra ou reserva; não inicia pagamento no ACULTA (D-29) |
| `field_local` | entity_reference (`aculta_event_location`) | `field_location` | Opcional se for virtual |
| `field_link_virtual` | link | `field_location_virtual` | Para evento on-line |
| `field_modo` | list_string | `field_attendance_mode` | Presencial, on-line ou híbrido |
| `field_status` | list_string | `field_event_status` | Agendado, adiado, cancelado |
| `field_organizador` | entity_reference | `field_organizer` (date) | Pode diferir do organizador do evento |
| `field_estreia` | boolean | `field_premiere` | Sinalização de estreia |
| `field_data_pai` | entity_reference (`aculta_event_date`) | `field_parent_date` | Subdatas de uma data (ex.: sessões de um mesmo dia) |
| `field_regra` | string (255) | `field_date` (recorrência do `smartdate`) | RRULE do subconjunto da v1 (`FREQ`, `INTERVAL`, `BYDAY`, `BYMONTHDAY`, `COUNT`/`UNTIL`). Vazio = evento único. Ver `aculta_calendar/docs/RECORRENCIA.md` |
| `field_limite_meses` | integer (padrão 12) | `month_limit` (`field_date`) | Horizonte de série aberta (sem COUNT nem UNTIL). Ver `aculta_calendar/docs/RECORRENCIA.md` |
| Entidade `aculta_event_override` | — | `smart_date_override` | Exceção por ocorrência, chaveada pelo início original (M1). Início e fim vazios = cancelada. Campos: `field_data_pai`, `field_inicio_original`, `field_inicio`, `field_fim`, `field_motivo` |
| `field_abertura_portas` | string | `field_door_time_relative` | Horário relativo de abertura |
| `field_seguranca` | text | `field_safety_info` | Informações de segurança do local |

Venda de ingressos e pagamento dentro do ACULTA continuam fora da v1 (D-30). Os campos
acima são apenas informativos; `field_ticketing` leva o usuário a um serviço externo.

Regras:

- Data sem local e sem link virtual não publica.
- Cancelamento mantém a página (com aviso), não apaga a data.
- O fuso é o do site (`America/Sao_Paulo`, decisão D9).

## Local - aculta_event_location

Estado revalidado no HEAD-base do [plano LOC](../../../../../../../docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md). Esta seção distingue
campos de código de requisitos futuros; as demais tabelas históricas não são
inventário automático do schema ativo.

| Campo presente em EventLocation | Tipo no código | Contrato |
| --- | --- | --- |
| title | string | Nome obrigatório, máximo 255 |
| field_tipo | entity_reference taxonomy_term | Vocabulário aculta_location_type |
| field_endereco | address | BR; formulário/adaptador postal central obrigatório |
| field_coordenadas | geofield | Maps geocodifica no salvamento conforme política existente |
| field_precisao | list_string | exato/aproximado; não representa visibilidade privada |
| field_horario | string_long | Texto; horário estruturado continua backlog próprio |
| field_osm_id / field_osm_version | string | Proveniência opcional; OSM não é requisito do cadastro manual |
| field_allow_claiming | boolean | Padrão fechado; serviço de reivindicação existente |
| field_organizador | entity_reference user | Responsável; não equivale automaticamente a Perfil ou Organização |

**Planejado em LOC-01/02, ausente nessa definição:** estado próprio de
publicação/visibilidade e descrição/telefone/site/contatos. Mapear contratos
existentes antes de criar campos; não tratar field_contato ou field_imagem
mencionados no desenho anterior como implementados.

A Data mantém field_local apontando ao mesmo ID reutilizável de Local. CRUD
independente e inline compartilham Forms/validação. Remover referência não
exclui Local; exclusão referenciada exige regra explícita.

Precisão aproximada omite rua/CEP e reduz coordenadas nas saídas públicas.
Visibilidade/autorização devem ser aplicadas antes da renderização e da
projeção pública no Index. Plano proposto de rascunho/público/privado será
formalizado em LOC-01, preservando registros existentes por migração controlada.

Index armazena projeção mínima por entidade/idioma e relação derivada
Evento > Local, não endereço mestre ou ocorrência duplicada; detalhes e gates
IDX-LOC na seção 6 do plano.

## Relações

```
aculta_event (1) ──< aculta_event_date (n) >── (1) aculta_event_location
     │                       │
     └─ organizador (user)   └─ link virtual
```

## Vocabulários

| Vocabulário | Origem | Uso |
| --- | --- | --- |
| `aculta_event_type` | `event_type` (OpenCulturas) | Tipo de evento (oficina, show, debate) |
| `aculta_location_type` | `location_type` | Tipo de local (espaço cultural, praça, on-line) |

## Visibilidade e purpose

- Os tipos pertencem ao purpose `eventos` (`ContentPurposeResolver`).
- Catálogo, local e data publicados aparecem em `eventos.aculta`. Em outros hosts respondem 404
  (como a Loja faz com o produto). A exceção é a agenda do calendário, que também aparece no `main`
  (D6), sem alterar a regra de propriedade dos tipos.
- Rascunho e cancelado não aparecem no sitemap.

## Índices e busca

- Provedor em `aculta_index` para `aculta_event` e `aculta_event_location`.
- Datas passadas saem da agenda principal e continuam acessíveis pelo arquivo do calendário.
