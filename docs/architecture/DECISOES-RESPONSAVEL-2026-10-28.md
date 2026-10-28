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
| D-08 | Mapa (D7) | **Google Maps com exceção de privacidade**: carrega só após clique do visitante, aviso na página, chave restrita por domínio. | Exige ADR de exceção (ainda não escrito) e chave por ambiente. |
| D-09 | Canonical do calendário (D6) | **Eventos.** A versão no main aponta canonical para `eventos.aculta.org/calendario`. | `aculta_calendar` F5. |
| D-10 | Licença de mídia (I-13) | **Opcional.** Sem obrigação de licença e sem filtro por licença. | Derruba a parte de filtro por licença do C-04. Não há requisito de CC. |
| D-11 | Anti-spam (I-14) | **Manter só o captcha atual** (Turnstile). Honeypot não entra agora. | I-14 fica fora da fase U5. |
| D-12 | Cores de seção (I-09) | **Lista fechada aprovada**: os quatro tokens da paleta (vermelho, verde, amarelo, verde-escuro). | Revista, item I-09 liberado para implementação. |

## Pendente

| # | Assunto | Situação |
| --- | --- | --- |
| P-01 | Endereço de divulgação responsável (I-17) | **Aguardando o endereço do responsável.** Sem ele não há `SECURITY.md`. |
| P-02 | ADR de exceção do Google Maps (D-08) | A escrever, com a decisão de privacidade registrada. Bloqueia a fase F4 de eventos. |

## Efeitos em cadeia

- Itens de **organização** (I-05, I-06, C-02, C-03) seguem, mas com escopo em vez de papel novo (D-01).
- **I-13 e C-04** perdem o filtro por licença. Nenhum código de licença é necessário para a v1.
- **I-14** não entra na fase U5 do `aculta_ugc`.
- **Presença (I-29 e Statistics)** tem superfície restrita: painel e rodapé do fórum, sem exposição pública geral.

## Histórico

- 2026-10-28: decisões D-01 a D-12 registradas, a partir de perguntas interativas. P-01 aguardando dado do responsável.
