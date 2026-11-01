# THEME-UX 0.8-B3 — Auditoria de estados e feedback universais

**Data:** 2026-10-22 · **Status:** auditoria estática parcial e plano. Nenhum SDC novo criado nesta fase.

## Bases SDC existentes verificadas
| SDC | Contrato atual | Reuso |
| --- | --- | --- |
| `aculta420:messenger-state` | prop `state`: `empty`, `error`, `loading`; slot `content` | apresentação Messenger; **não expandir enum indiscriminadamente** para sucesso/permissão |
| `aculta420:institutional-notice` | prop `severity`: `low`, `medium`, `high`; slot `message` | adaptador de Site Alert; **não usar como confirmação de formulário** |

Ambos já usam metadados Drupal Core SDC. Falta confirmar consumers, bibliotecas, acessibilidade, CSS e integração UI Suite no Runtime.

## Decisão: não copiar feedback antigo
O tema deverá primeiro analisar se há um SDC neutro passível de composição/variante **sem comprometer contratos especializados**. Só criar `feedback-state` ou `status-message` quando os consumidores reais exigirem um contrato que `messenger-state` e mensagens Drupal Core não atendam. Não substituir `#type: status_messages`, `messenger`, `Site Alert`, H5P ou confirmação de envio no backend.

## Semântica planejada
- **Empty:** explicar ausência de conteúdo com ação opcional verdadeira; não inventar contador ou permissão.
- **Loading:** `role=status` e mensagem curta quando carregamento assíncrono real; não criar aria-live duplicado do Studio.
- **Error:** mensagem e recuperação possível, `role=alert` somente para erros que requerem anúncio imediato.
- **Success:** apenas após confirmação efetiva da operação pelo servidor; sem UI otimista enganosa.
- **Restricted:** acesso decidido pelo backend, não por Twig; evitar vazar existência de recursos.
- Render arrays, cache contexts/tags e traduções pelos providers; CSS tokens claros/escuros e foco do tema.

## Gate obrigatório B3-01 — provar duas necessidades reais
1. Localizar estados existentes do dashboard GanjaLab420 e da Wiki ou GanjaLab no código e Runtime.
2. Inventariar `#type: status_messages`, componentes `messenger-state`, `institutional-notice` e mensagens AJAX.
3. Classificar: SDC conforme reutilizável / SDC parcial a adequar / apresentação legada a migrar / Core sem necessidade de SDC.
4. Identificar pares distintos de consumidores e o estado comum que precisa de apresentação neutra.
5. Só após prova, decidir entre reutilizar/estender SDC ou criar novo, adicionar stories e integrações UI Patterns/UI Styles.
6. Validar acessibilidade, mobile-first, tradução, acesso/cache e shell multidomínio no Runtime.

## Próximas fases
- **B3-01:** completar auditoria de consumidores/estados por arquivo/rota.
- **B3-02:** decidir contrato visual reutilizável, com duas aplicações.
- **B3-03:** implementar componente mínimo, stories e primeiro consumidor.
- **B3-04:** integrar segundo consumidor; validar UX e mecanismos do Drupal.
- **B3-05:** gates Runtime e documentação final.

**Não homologar B2 nem B3 com base só nos arquivos GitHub.**

## B3-01/B3-03 — evidências e implementação (2026-10-22)

A inspeção estática encontrou **dois consumidores comprovados**: `WikiController::search()` possuía mensagem de zero resultados; `LearningDashboardController::student()` possuía estados sem cursos e sem certificados. `StudioController` possui aviso de AJAX ao vivo próprio; `messenger-state` é específico de Messenger; `institutional-notice` apresenta Site Alert. Nenhum deles foi substituído.

**Criado `aculta420:empty-state`** em `components/feedback/empty-state/`: schema Core SDC, Twig neutro, CSS responsivo com tokens, stories para LMS e Wiki. O estado (query/listagem vazia) é decidido pelo provider e a mensagem permanece render array traduzido `#plain_text`; não há lógica de acesso no tema.

**Consumers commitados:** zero resultados em `WikiController::search()` (somente render HTML comum; não altera o retorno especial `preview()`); zero cursos e certificados em `LearningDashboardController::student()`. Acesso, caches e busca/listagens continuam pertencendo aos módulos. Estado de erro de domínio da Wiki não foi migrado; não é um simples estado vazio.

**Gates Runtime pendentes:** descoberta SDC e UI Patterns Library, schema do slot, lint PHP/Twig, Drush CR, vazio/não vazio, domínios, cache, login, dark/light, 320/390/768/1280, contraste, teclado e shell Institution Bar + Domain Header. Status experimental, **não homologado**.

**Próximo passo B3-04:** validar consumidores e inspecionar outro padrão neutro de feedback; não expandir este SDC para erro, carregamento ou sucesso sem novos consumidores reais.
