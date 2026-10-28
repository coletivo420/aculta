# Política de documentação

Atualizada em 2026-10-28 conforme a política de changelog e histórico.

## O que fica na documentação

Só guardrails e referências vigentes:

- **Guardrails:** regra que bloqueia regressão. Fronteiras, políticas de domínio, segredos e
  dados de teste, gates, release e versionamento, fontes de verdade, decisões vigentes (ADRs).
- **Referências:** procedimento de operação que ainda vale (deploy, testes, dados de teste)
  e inventário de módulos e integrações.
- **Roadmap e dívidas:** um roadmap por projeto e um único registro de dívidas com evidência.

## O que sai

- relatórios de auditoria, QA ou revisão de uma fase concluída;
- handoffs e guias de passagem entre agentes;
- planos de fase já executados, matrizes datadas e checklists de execução;
- especificações de produto ainda não iniciadas (vão para o roadmap, e a especificação volta
  ao Git quando a feature começar);
- listagens de features e estados atuais que duplicam código, changelog ou roadmap;
- blocos de resultado de execução dentro de documentos normativos.

Antes de remover, extrair as regras do documento para um guardrail. Depois, remover e registrar o
último commit em que o arquivo existia.

## Onde fica o histórico

No Git. Cada remoção registra o último commit em que o arquivo existia, em `DEBT-REGISTER.md` ou
na seção de limpeza do changelog. Recuperar: `git show <commit>:<caminho>`.

Cada `CHANGELOG.md` mantém no máximo 30 entradas recentes. As entradas anteriores são copiadas
sem reescrita para `docs/history/changelog/<modulo>.md`. Decisões ainda vigentes saem do histórico
e são registradas em ADR, para regras transversais, ou na arquitetura do módulo. Consulte
[CHANGELOG-POLITICA.md](operations/CHANGELOG-POLITICA.md) para o procedimento completo.

## Onde fica o estado atual

- versão e release: `aculta420.info.yml`, `CHANGELOG.md` do tema e tags;
- Portal: `CHANGELOG.md` do módulo e tags `portal-v*`;
- pendências: `docs/operations/DEBT-REGISTER.md`;
- planejamento: `docs/portal/ROADMAP.md` e `web/themes/custom/aculta420/docs/roadmap.md`.

Documento não deve conter "status atual", "próxima etapa" ou número de PR como fonte de verdade.
Esses dados mudam; ficam no changelog, no roadmap ou no registro de dívidas.

## Como criar um documento novo

1. Confirmar que ele é guardrail ou referência. Se não for, o lugar é o roadmap ou o changelog.
2. Indicar o dono e a data de revisão no topo.
3. Não citar fase, PR ou branch como estado vigente.
4. Adicioná-lo ao índice da pasta (`docs/README.md`, `docs/portal/README.md` ou o do tema).
5. Links relativos devem resolver. A checagem de links é feita antes de cada PR de documentação.

## Revisão

- A cada release, revisar os índices e remover o que não for guardrail ou referência.
- Documento sem uso há duas releases vai para o histórico.

## Limpeza de 2026-10-09

Removidos (último commit com os arquivos: `9c95420`):

- `docs/portal/P10-R-FINAL-AUDIT.md`, `RELEASE-P10.md`, `MODERNIZACAO-DRUPAL-11-HANDOFF.md`,
  `HARDENING-P9.md`, `DEPRECATION-MATRIX-P8.md`;
- `docs/portal/ACCOUNT-PRESENTATION-MODEL.md`, `ACCOUNT-SDC-AJAX.md`, `FORUM.md`, `MAGAZINE.md`,
  `SHOP.md`, `WIKI.md` (regras consolidadas em `docs/portal/GUARDRAILS.md`);
- `web/themes/custom/aculta420/docs/design-b-qa.md` e `features.md`.

Blocos de histórico de execução removidos: seções de implementação e resultados de Runtime em
`docs/portal/DOMAIN-PRESENTATION-CONTRACT.md` e seção de plano de versão em
`docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`.
