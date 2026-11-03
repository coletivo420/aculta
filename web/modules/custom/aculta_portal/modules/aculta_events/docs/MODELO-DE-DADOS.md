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

### Configuração estrutural de RSVP — D-60

Campos técnicos de RSVP em `aculta_event` representam as escolhas definidas em D-60. `field_rsvp_ativo` tem padrão `FALSE`; os campos não são exibidos no formulário do organizador enquanto não existir o fluxo completo de inscrição. `field_rsvp_capacidade` no Evento e na Data, e `field_capacidade` na oferta/lote, guardam limites nominais conforme `field_rsvp_escopo_capacidade`. Esses valores não reservam vagas nem medem disponibilidade consumida.

| Campo em `aculta_event` | Tipo | Padrão/contrato |
| --- | --- | --- |
| `field_rsvp_ativo` | boolean | `FALSE`; não inicia inscrição |
| `field_rsvp_escopo_capacidade` | list_string | `evento`, `data` ou `lote`; padrão `evento` |
| `field_rsvp_capacidade` | integer | Capacidade nominal opcional; mínimo 1 quando informado |
| `field_rsvp_limite_pessoa` | integer | Padrão 1; mínimo 1 |
| `field_rsvp_duplicidade` | list_string | `impedir` (padrão) ou `permitir` |
| `field_rsvp_reserva_minutos` | integer | Duração opcional; vazio significa que não há reserva temporária configurada |
| `field_rsvp_lista_espera` | boolean | `FALSE` |
| `field_rsvp_cancelamento` | list_string | Deve ser configurado quando o fluxo for habilitado |
| `field_rsvp_serie` | list_string | `ocorrencia` (padrão) ou `serie` |

`aculta_event_date.field_rsvp_capacidade` guarda limite por ocorrência. A entidade oferta usa `field_capacidade` quando o Evento seleciona escopo por lote. `RsvpPolicyResolver` valida a configuração e os vínculos do contexto antes de entregar uma política interna; oferta contextual precisa estar ativa e ser gratuita. A resolução não grava participantes nem reserva vagas. Concorrência, idempotência, deduplicação de participantes e expiração só serão efetivas quando o serviço transacional de inscrições existir.

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


## Ingressos gratuitos e pagos - integração planejada (ADR-036)

`field_gratuito`, `field_pricing` e `field_pricing_info` continuam informativos; `field_ticketing` é link externo. A entidade `aculta_event_offer` agora registra a estrutura de uma oferta/lote pertencente ao Evento e opcionalmente a uma Data. Ela não representa RSVP, pedido, pagamento, estoque consumido, ingresso ou check-in; ofertas são inativas por padrão e não têm rotas públicas.

| Campo em `aculta_event_offer` | Tipo | Contrato |
| --- | --- | --- |
| `title` | string | Nome obrigatório da oferta/lote |
| `field_evento` | entity_reference (`aculta_event`) | Evento obrigatório, sem duplicar Evento |
| `field_data` | entity_reference (`aculta_event_date`) | Data/ocorrência opcional |
| `field_modalidade` | list_string | `gratuita` ou `paga`; não inicia transação |
| `field_preco` | decimal | Valor nominal não negativo; não é pedido ou registro financeiro |
| `field_capacidade` | integer | Capacidade nominal opcional; disponibilidade consumida exige serviço transacional futuro |
| `field_limite_por_pedido` | integer | Limite positivo; valor inicial 1 |
| `field_inicio_vendas` / `field_fim_vendas` | datetime | Janela nominal da oferta, ainda sem fluxo público |
| `field_ativa` | boolean | Padrão `FALSE`; nenhum usuário pode ativar sem fluxo de gestão autorizado |

O tipo exige `administer aculta events` para qualquer operação e não declara links canônicos, criação ou edição. A validação de valores negativos usa constraints dos campos; regras cruzadas, capacidade atômica e administração por organizador permanecem pendentes.

- **Ownership:** `aculta_events` é dono das ofertas/lotes, inscrições/participantes, emissão e check-in. `aculta_commerce` mantém pedidos e order items pagos; `aculta_pagamentos_mercadopago` mantém o gateway; `aculta_portal` coordena os tipos/contextos no carrinho já existente. Sem gateway, carrinho ou storage financeiro paralelo.
- **Gratuito (proposta):** RSVP sem cobrança, nome e e-mail (D-33/D-40), sem CPF, pedido Commerce, carrinho ou Mercado Pago. Confirmar capacidade atomicamente e emitir credencial consumível uma vez no check-in. Não coletar dados reais em produção antes da revisão LGPD/base legal/aviso/retenção.
- **Pago (proposta):** lote ligado a Evento e Data; pedido Commerce `ingresso`; Checkout Pro; somente confirmação de pagamento validada e idempotente emite o ingresso. Sem frete. Somente pedidos pagos participam do carrinho integrado e seguem a precedência D-39. Em pedidos mistos, o ingresso não exige endereço nem compõe frete.
- **Credencial compartilhada:** RSVP e compra paga convergem em participante/credencial e check-in móvel, preservando estados financeiros distintos. QR leva apenas token opaco assinado; validar no servidor, exigir autorização contextual, gravar uma entrada e bloquear reuso. Não indexar pedido, PII ou token em `aculta_index`.
- **Domínios e interface:** inscrições e compras iniciadas em `eventos`; escrita validada no servidor e nunca intermediada pelo purpose `account`. Administração técnica/global fica no MAIN. Gestão do organizador pertence ao ACULTA Studio Culturas em `/culturas`; o código é parcial e a homologação está pendente.
- **Gates financeiros:** venda paga bloqueada até parecer fiscal/jurídico (D-31/D-35), políticas de taxa, estorno e repasse (D-32 a D-34), LGPD e testes em sandbox. Repasse manual por evento; sem marketplace/split na primeira versão.
- **Compatibilidade:** preservar os campos informativos atuais sem migração destrutiva. Quando houver lotes internos, definir precedência de exibição e migração antes de ativar; não inferir uma transação do booleano de gratuidade.

### Decisões ainda abertas

D-60/D-61 já definiram RSVP opcional, escopo de capacidade Evento/Data/Lote, regras configuráveis de limite/duplicidade/reserva/espera/cancelamento, abrangência por ocorrência/série e transferência auditável. Permanecem os gates LGPD para coleta pública, jurídico-fiscais para venda paga, contrato do item Commerce e especificação acessível de credencial/check-in. Definir precedência e migração de links externos antes de ativar ofertas públicas.

### Evolução restante do modelo

- O modelo estrutural da oferta/lote está implementado; falta a interface autorizada de gestão, validação das regras e o contrato de item de pedido Commerce. Não usar preço textual como valor transacional.
- Inscrição/participante e credencial devem atender RSVP gratuito sem pedido/pagamento e compra paga ligada a order item Commerce.
- Capacidade e reserva precisam ser transacionais e protegidas contra overbooking concorrente; a entidade de oferta não controla disponibilidade consumida. O contrato de item de pedido Commerce permanece separado.
- Preservar campos atuais até aprovar fonte de exibição e migração; não indexar PII, pedidos ou tokens.
