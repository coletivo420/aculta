# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status: sem status YAML.

## Próximo passo

F4 (UI Styles) implementada; accent por entidade na Revista depende de suporte do UI Styles. F5 geral implementada; listagem pública de Eventos existe na raiz, faltam View e validação visual. O preprocess do Studio agora mapeia `context-tabs` para o SDC `tabs` e `empty-state` para o SDC homônimo; o teste focal e renderizações Drupal passaram. A suíte visual ampla segue parcial: 34/48 checks OK, 22 skipped e 14 falhas, incluindo composição e verificação legada de modo escuro. Loja requer autorização documentada; `price` permanece fora do escopo.
Próximo passo: corrigir/atualizar os checks de composição e modo escuro do Studio, capturar um consumidor real de `empty-state`, avaliar a View de Eventos e validar cada adoção com captura antes/depois e gates.

## Dívidas

- Componente `price` (ADR-046): aguarda definição do contrato e autorização explícita da frente de Commerce/Loja.
- Faixa de acento (`card-cultural`): em modo DRAFT até entrega da variável neutra `domain_presentation.accent` (D1) pelo Portal.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/themes/custom/aculta420_v2/docs/roadmap.md`. Leia só a seção pelo título: `grep -n '^## ' web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta420_v2.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
