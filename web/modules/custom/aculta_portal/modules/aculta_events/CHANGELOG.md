# Changelog — aculta_events

## 0.1.0-dev.4 — validar UNTIL contra o início da série (2026-10-29)

- O formulário de recorrência recusa `UNTIL` anterior ao início da série, com
  mensagem no campo, em vez de gravar uma série que se expandiria para nada.
  O expansor mantém a mesma regra como guarda de dados.
- Suíte Events: 55 testes/669 assertions PASS (26 depreciações); PHPCS sem
  erros nos arquivos alterados.

## 0.1.0-dev.3 — smoke visual do purpose eventos (2026-10-29)

- `tests/browser/eventos/agenda-embed.mjs` passou 129/129 verificações no
  candidato atual `a4f878d55` (events dev.3), servido no checkout de teste, no
  purpose `eventos`: catálogo,
  página de evento, agenda, grade do mês, agenda do dia, incorporação e mapa,
  em 1280/390/320 px reais via BiDi, com modo escuro, sem rolagem horizontal e
  sem erro visível.
- Suíte atual no worktree: 55 testes/669 assertions PASS (26 depreciações).
  A revisão visual cobre o dev.3 no candidato `a4f878d55`. Nenhuma escrita no
  OSM e nenhuma atualização do Runtime.

## 0.1.0-dev.2 — fronteira de apresentação via tokens (2026-10-29)

- Catálogo deixou de invocar o componente `aculta420:event_card`; a estrutura
  agora usa render arrays Drupal com tokens semânticos `data-portal-ui`.
- Links do mapa usam os mesmos tokens neutros. As classes visuais equivalentes
  ficam no `PortalUiHooks` do ACULTA420.
- Events: 55 testes / 669 assertions PASS (26 depreciações upstream); gate
  módulo–tema PASS.

## 2026-10-28 — E7 MP3, mapas Leaflet/OSM

- `/mapa` passa a usar o renderer compartilhado `aculta_maps`, tiles OSM e agrupamento de marcadores; a lista continua disponível sem JavaScript.
- O aviso identifica o envio do IP aos tiles OSM e aponta para `/sobre/politica-de-privacidade`; a ativação no Runtime aguarda revisão do conteúdo da política.
- Geocodificação Nominatim acontece ao criar local ou alterar endereço. Sucesso grava coordenadas; falha ao atualizar endereço remove coordenadas antigas para evitar localização desatualizada.

## 2026-10-28 — URLs de página e sitemap por ambiente no F7

- Página de evento e sitemap do purpose usam o host que o ACULTA Deployer mapeia para o ambiente ativo; o ambiente de teste permanece `noindex`.
- Smoke HTTP no teste: sitemap index e filho `eventos` respondem 200; o filho lista URLs no host `eventos` de teste e a página declara canonical para o mesmo host. F7 concluído em `D-44`.

## 2026-10-28 — formulário de nova data com referência segura (E4, D-28)

- Adicionado `/painel-administrativo/eventos/datas/adicionar`; o parâmetro `event_id` só preenche o evento quando o usuário tem acesso de atualização. IDs inválidos, inexistentes ou não editáveis deixam o campo em branco.
- O formulário cria a data com a referência selecionada e campos preenchidos ali; não copia participantes nem clona parágrafos. Implementação reescrita a partir da regra D-28, sem copiar código do OpenCulturas.
- Kernel: editor autorizado recebe a referência; usuário sem acesso não a recebe; ID inexistente ou evento não editável não quebra o formulário.

## 2026-10-28 — campos informativos de preço e compra externa (E1, D-29)

- `aculta_event_date` ganha `field_gratuito`, `field_pricing`, `field_pricing_info` e `field_ticketing`; preço é texto, ticketing aceita link externo e não há cobrança no ACULTA.
- Update hook 10004 instala as definições sem remover dados existentes. Tipos adaptados do OpenCulturas; nenhum código de pagamento foi copiado.

## 2026-10-28 — views administrativas de locais e datas

- Incluídas as views `eventos_locais` e `eventos_datas_relacionadas` em caminhos portugueses, com acesso restrito ao papel administrador e sem telefone, e-mail ou endereço.
- A exportação administrativa de datas foi aprovada para a v1 via provedor do `aculta_calendar` (ADR-031); permanece pendente de uma API de exportação de dados administrativos sobre os provedores de itens, não coberta pela exportação `.ics` CA0/CA2 e não duplicada neste módulo.
- Kernel `EventRelatedViewsAccessKernelTest`: acesso negado para usuário comum e allowlist de campos sem telefone, e-mail ou endereço. O provedor do calendário fornece agenda e `.ics`; exportação administrativa estruturada ainda pendente.

## 2026-10-28 — registrar propósito dos locais nos testes

- Exportado para `config/sync` o vocabulário `aculta_location_type`, já criado pelo update hook 10003, e coberta a negação de `/locais` fora do purpose `eventos` com teste Kernel.
- O update hook já contém os 17 tipos de local traduzidos; este registro fecha a configuração e a evidência de acesso da microfase E1.
