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
| `field_local` | entity_reference (`aculta_event_location`) | `field_location` | Opcional se for virtual |
| `field_link_virtual` | link | `field_location_virtual` | Para evento on-line |
| `field_modo` | list_string | `field_attendance_mode` | Presencial, on-line ou híbrido |
| `field_status` | list_string | `field_event_status` | Agendado, adiado, cancelado |
| `field_organizador` | entity_reference | `field_organizer` (date) | Pode diferir do organizador do evento |
| `field_gratuito` | boolean | `field_forfree` | Entrada gratuita |
| `field_estreia` | boolean | `field_premiere` | Sinalização de estreia |
| `field_data_pai` | entity_reference (`aculta_event_date`) | `field_parent_date` | Subdatas de uma data (ex.: sessões de um mesmo dia); recorrência fica para o pós-v1 |
| `field_abertura_portas` | string | `field_door_time_relative` | Horário relativo de abertura |
| `field_seguranca` | text | `field_safety_info` | Informações de segurança do local |

Campos de preço e ingresso (`field_pricing`, `field_ticketing`) não entram na v1: a
venda fica fora do escopo (ver `PLANO-IMPLANTACAO.md`, seção 1). Só `field_gratuito`
representa o preço na v1.

Regras:

- Data sem local e sem link virtual não publica.
- Cancelamento mantém a página (com aviso), não apaga a data.
- O fuso é o do site (`America/Sao_Paulo`, decisão D9).

## Local — `aculta_event_location`

Lugar físico ou virtual onde ocorrem eventos.

| Campo | Tipo Drupal | Origem OpenCulturas | Observação |
| --- | --- | --- | --- |
| `title` | string | `title` | Nome do local |
| `field_tipo` | entity_reference (`aculta_location_type`) | `location_type` | Vocabulário próprio |
| `field_endereco` | address (paragrafo ou campo) | `field_address_data` (paragraph `address_data`) | Avaliar `drupal/address` |
| `field_coordenadas` | geofield | `field_address_location` (paragraph) | Lat/long para o mapa |
| `field_precisao` | list_string | Sem origem no OpenCulturas (o `field_location_precision` de lá marca áreas desenhadas à mão, não privacidade) | Exato ou aproximado; campo próprio (auditoria, achado T9) |
| `field_horario` | office_hours ou texto | `field_office_hours` / `field_opening_hours` | Escolher uma forma |
| `field_contato` | entity_reference_revisions | `field_contact_data` | Telefone, e-mail, site |
| `field_imagem` | image | `field_mood_image`, `field_gallery` | Galeria opcional |

Regras de privacidade:

- Local em residência particular: exibir somente o bairro/município, com `field_precisao = aproximado`. Regra a definir pelo responsável; o OpenCulturas não a fornece.
- Coordenadas não exibidas como texto; só no mapa.

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
