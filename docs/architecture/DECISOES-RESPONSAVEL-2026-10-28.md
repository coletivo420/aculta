# Decisões do responsável — 2026-10-28

Registro das decisões tomadas em perguntas interativas sobre as pendências do comparativo
OpenCulturas (`OPENCULTURAS-COMPARATIVO.md`, seção 7). Cada decisão diz o que mudou, o que
continua pendente e quais itens de roadmap ela afeta.

Status: **vigente**, salvo decisão posterior registrada aqui. Uma decisão só muda por novo registro.

## Decisões

| # | Assunto | Decisão | Impacto nos itens |
| --- | --- | --- | --- |
| D-01 | Papel organizador (I-06) | **Não criar papel novo.** Usar `content_editor` com escopo por organização, aplicado no código. | I-06 vira escopo, não papel. C-03 (em nome de organização) depende do vínculo verificado. |
| D-02 | Reivindicação de posse (I-05) | **Moderação decide.** O pedido vai à fila de moderação. O padrão proposto era começar fechado até o dono liberar (C-02); a resposta não contestou esse padrão, então ele fica como padrão. | I-05 e C-02 com padrão fechado. |
| D-03 | Destinatários de aviso de denúncia (I-04, C-01) | **Por purpose.** Cada área (fórum, revista, wiki, eventos) tem sua lista, em entidade de configuração. | `aculta_ugc` U2 com entidade por purpose. |
| D-04 | Fila de denúncias (U0) | **Mover para `aculta_ugc`.** Uma única fila, com migração que conta antes e depois. | `aculta_ugc` U3 (migração do `ReportQueue`). |
| D-05 | Presença "quem está online": onde aparece | **Painel administrativo do Statistics e rodapé do fórum** (purpose fórum). Visitantes fora do fórum não veem a contagem. | Statistics (presença agregada). Rodapé do fórum novo. |
| D-06 | Rodapé do fórum: formato | **Só o total** ("N pessoas online"). Sem separar visitantes e registrados. | Evita revelar a composição do grupo. |
| D-07 | Janela "online" | **5 minutos.** | Contagem sobe e cai mais rápido que o padrão do OpenCulturas (15). |
| D-08 | Mapa (D7) | **Google Maps como provedor padrão do mapa, carregado já na exibição da página.** Não é exceção: é o comportamento padrão, com aviso de privacidade visível na página e link para a política. | Política de privacidade cita o envio do IP ao Google. Chave de API restrita por domínio, por ambiente. |
| D-09 | Canonical do calendário (D6) | **Eventos.** A versão no main aponta canonical para `eventos.aculta.org/calendario`. | `aculta_calendar` F5. |
| D-10 | Licença de mídia (I-13) | **Opcional.** Sem obrigação de licença e sem filtro por licença. | Derruba a parte de filtro por licença do C-04. Não há requisito de CC. |
| D-11 | Anti-spam (I-14) | **Manter só o captcha atual** (Turnstile). Honeypot não entra agora. | I-14 fica fora da fase U5. |
| D-12 | Cores de seção (I-09) | **Lista fechada aprovada**: os quatro tokens da paleta (vermelho, verde, amarelo, verde-escuro). | Revista, item I-09 liberado para implementação. |
| D-13 | Campo de data do evento (F2) | **`daterange` do core com `field_dia_inteiro`**, sem `smart_date`. O `smart_date` 4.x exige `simshaun/recurr`, que não aceita `doctrine/collections` 3.x; instalá-lo obrigaria a rebaixar a biblioteca. Recorrência, quando entrar, será própria do ACULTA. | `MODELO-DE-DADOS.md` (`field_quando`, `field_dia_inteiro`) e `aculta_calendar` F5. |
| D-14 | Recorrência de eventos | **Entra na v1.** Subconjunto de RRULE (`FREQ` DAILY/WEEKLY/MONTHLY, `INTERVAL`, `BYDAY`, `BYMONTHDAY`, `COUNT`/`UNTIL`). Replica o comportamento do OpenCulturas (`smart_date_recur`), com modificações próprias. Biblioteca: `rlanvin/php-rrule` 3.0. Respostas do responsável: cancelada conta em `COUNT`; editar uma ocorrência entra na v1; série aberta permitida com horizonte de 12 meses. Detalhes em `aculta_calendar/docs/RECORRENCIA.md`. | `MODELO-DE-DADOS.md` (`field_regra`, `field_limite_meses`, entidade `aculta_event_override`), F2 e F5 ampliadas. Substitui a parte "pós-v1" de D9 e do modelo. |
| D-15 | Integração do calendário e eventos ao LMS | **Em todos os pontos, restrito a cursos e usuários matriculados**: prazos das turmas no calendário, eventos de turma (`field_turma`) e próximos eventos no painel de aprendizado. Regra de acesso pela matrícula na turma e no curso. | `aculta_lms_agenda` (novo submódulo), `aculta_events` (`field_turma`), `aculta_lms` (painel). Desenho em `aculta_lms_agenda/docs/INTEGRACAO.md`. |
| D-16 | Dependências de recorrência e de coordenadas | **Autorizado** o `composer require` de `rlanvin/php-rrule` (3.0, MIT) e de `drupal/geofield` (1.67.0). As duas já estão em `composer.json`/`composer.lock` e instaladas: a autorização fecha a pendência, nada a reverter. `drupal/address` já estava no projeto. | D-14 (recorrência), E0 (dependências), E7/F4 (coordenadas). `aculta_events.info.yml` declara `address:address`, `geofield:geofield` e `key:key`. |
| D-17 | Provedor de mapa (substitui D-08) | **Leaflet com OpenStreetMap como padrão.** Google Maps como opção escolhida no painel administrativo, com a chave em `aculta_secrets` (`GOOGLE_MAPS_API_KEY`). O mapa segue carregando na exibição da página, com atribuição e aviso de privacidade. Motivo: o Google inviabiliza o uso (conta de cobrança e envio de IP ao Google). Detalhes e riscos em ADR-030. | `aculta_events` F4 e ADM7 (painel do mapa). ADR-014 superado. |
| D-18 | Mapa: padrão, fallback e opção (substitui a parte de provedor da D-17) | **Leaflet + OpenStreetMap público** é o padrão e o fallback. **Google Maps API** é opção, com a chave em `aculta_secrets` (`GOOGLE_MAPS_API_KEY`). **Link de rota do Google sem API** fica disponível em qualquer modo. A **URL de rota é configurável** no painel do mapa. | ADR-030. Painel do mapa (ADM7) e `aculta_events` F4. E7 e I-11 atualizados. |
| D-19 | Mapa como submódulo próprio | **Criar o submódulo `aculta_maps`** para o mapa e a geocodificação, usável por qualquer área do site. O código de mapa sai de `aculta_events` (MP1). `aculta_events` e os demais módulos consomem a API pública, sem chamar o provedor diretamente. ADM7 (painel de opções do mapa) passa a ficar em `/painel-administrativo/mapas`. Motivo: o mapa passou a ser usado por mais de uma área. | `web/modules/custom/aculta_portal/modules/aculta_maps/docs/` (plano e roadmap). Substitui a localização do mapa em `aculta_events` (F4 e ADM7). |

## Pendente

| # | Assunto | Situação |
| --- | --- | --- |
| P-01 | Endereço de divulgação responsável (I-17) | **Resolvido em 2026-10-28:** `abuso@aculta.org`, publicado no `SECURITY.md` da raiz. Prazos de resposta ainda não fixados. |
| P-02 | ADR do provedor de mapa (D-08) | **Resolvido em 2026-10-28:** `docs/decisions/ADR-014-provedor-mapa-google-maps.md`. Verificações de termos, cobrança e LGPD continuam listadas no próprio ADR, antes da F4. |

## Efeitos em cadeia

- Itens de **organização** (I-05, I-06, C-02, C-03) seguem, mas com escopo em vez de papel novo (D-01).
- **I-13 e C-04** perdem o filtro por licença. Nenhum código de licença é necessário para a v1.
- **I-14** não entra na fase U5 do `aculta_ugc`.
- **Presença (I-29 e Statistics)** tem superfície restrita: painel e rodapé do fórum, sem exposição pública geral.

## Histórico

- 2026-10-28: decisões D-01 a D-12 registradas, a partir de perguntas interativas. P-01 aguardando dado do responsável.
- 2026-10-28: P-02 resolvido com o ADR-014 (provedor de mapa).
- 2026-10-28: P-01 resolvido com o endereço `abuso@aculta.org` e criado o `SECURITY.md`.
- 2026-10-28 (alteração): D-08 deixa de ser exceção. O mapa carrega na exibição da página, como padrão. Motivo: pedido do responsável.
- 2026-10-28: D-16 registrada — autorização do responsável para `rlanvin/php-rrule` e `drupal/geofield`. Resolve a pendência 1 do plano de incorporação.
