# Pesquisa — gamificação no Drupal 11+

Data: 2026-10-15. Pacotes externos baixados apenas para leitura, sem Composer
require, instalação, ativação ou incorporação de código. Checksums e versões em
[FONTES-UPSTREAM.json](FONTES-UPSTREAM.json). Compatibilidade declarada não é
teste do Drupal/PHP instalado nem resolução de toda a árvore Composer.

## Comparação de soluções

| Solução / fonte primária | Estado observado | Encaixe e recomendação |
| --- | --- | --- |
| Base própria `aculta_user` | Catálogo, serviço, hooks e `aculta_badge_award` já presentes | Melhor ponto inicial: preservar fatos e corrigir lacunas, sem segundo backend |
| [Achievements](https://www.drupal.org/project/achievements), [releases](https://www.drupal.org/project/achievements/releases) | 3.0.3 estável, declara D11/12; pacote GPL-2.0-or-later | Candidato para spike de substituição, ou referência de marcos/concessão atômica; não instalar junto de outra fonte de awards |
| [ECA](https://www.drupal.org/project/eca) | 3.1.9 estável; Core ^11.3 ou ^12.0; PHP >=8.3 no pacote; GPL-2.0-or-later | Motor opcional de eventos/condições/ações se modelos administráveis justificarem dependências; não guarda a conquista |
| [Rules](https://www.drupal.org/project/rules) | 4.0.1 estável, ^10.3 ou ^11; GPL-2.0-or-later | Alternativa ao ECA; não adotar dois motores para a mesma responsabilidade |
| [Gamification](https://www.drupal.org/project/gamification) | 2.0.2 estável na página; foco em pontos e dependência User Points | Sem necessidade no escopo aprovado; ainda há divergência de metadados no pacote |
| [User Points](https://www.drupal.org/project/userpoints), [beta2](https://www.drupal.org/project/userpoints/releases/2.0.0-beta2) | Página sem stable suportada; beta2 consultada declara ^9 | Não selecionar para Drupal 11 sem comprovação adicional; pontos estão fora do escopo |
| [User Badges](https://www.drupal.org/project/user_badges) | Página informa manutenção mínima e ausência de stable suportada | Não recomendar com base em guias antigos de Drupal 7 |
| [Open Badges](https://www.1edtech.org/standards/open-badges) | Padrão de credenciais interoperáveis, com emissor, critérios e evidências | Tema futuro se houver emissão de credenciais; não é requisito de badges internas |

## Achievements: a página não representa integralmente a release moderna

A página geral descreve os antigos submódulos Pointless e Opt-out. O changelog
da primeira versão Drupal 8 informa sua remoção; no tarball **3.0.3** há apenas
`achievements.info.yml`, sem esses submódulos. Não recomendar "instalar Pointless"
para atender à decisão de ausência de ranking.

Inspeção de código do pacote: `achievements.install` cria `achievement_totals`
e armazenamento de unlocks; `achievements.module` contém cálculo de ranking,
funções procedurais e service locators; `src/Entity/AchievementEntity.php` define
configuração dos marcos. `achievements_unlocked()` usa transação. Dependências
declaradas incluem `jquery_ui_effects` e `jquery_ui_dialog`.

Esses fatos tornam a instalação integral menos adequada que evoluir a base
atual. Para substituir o backend seria preciso migrar todos os fatos e eliminar
exposição/cálculo competitivo e UI sobreposta. Copiar o pacote não o moderniza.

## Gamification: discrepância comprovada

O `.info.yml` do tarball **2.0.2** declara `^8 || ^9 || ^10 || ^11`; seu
`composer.json` ainda exige `drupal/core: ^8.0||^9.0||^10.0` e `drupal/userpoints: ^2.0`.
Isso é uma divergência observada nos arquivos, não resultado de um dry-run
Composer. A release User Points encontrada é beta sem suporte estável anunciado.

O código oferece widgets/eventos/limites de atribuição e integração com pontos.
O conceito de limitar eventos por fonte pode inspirar uma adaptação pequena,
mas não justifica adotar a pilha para conquistas sem pontuação.

## ECA versus regras específicas

No pacote 3.1.9 do ECA, `composer.json` exige `drupal/modeler_api: ^1.1` e
`dragonmantank/cron-expression: ^3.1`. Uma UI de modelagem é escolha separada,
não capacidade presumida só por instalar ECA. A documentação de
[ações](https://ecaguide.org/eca/concepts/actions/) inclui operações sobre dados,
usuários e notificações: modelos precisam de governança e acesso explícitos.

Começar com poucos critérios predefinidos reduz superfície operacional. Avaliar
ECA se o responsável precisar criar fluxos além desses critérios. A ação final
chamará uma API única de concessão; modelos não escrevem direto em tabelas.
Rules é alternativa real, mas adiciona Typed Data e outro modelo de automação.

## Evidência local e prioridades

`aculta_user/src/Badge/BadgeCatalog.php`, `BadgeService.php`,
`src/Entity/BadgeAward.php` e `src/Hook/BadgeEvaluationHooks.php` foram lidos.
Lacunas de publicação tardia, revisão Wiki, idempotência concorrente, critérios de
pedido e privacidade estão em [ARQUITETURA.md](ARQUITETURA.md).

Antes de adotar outro módulo, comparar custos de corrigir a fonte atual contra
migrar seu estado. ECA e Rules são motores; Achievements é candidato de backend;
esses papéis não são equivalentes. A ausência de um contrib perfeito não autoriza
um novo framework genérico nem storage duplicado.

## Pesquisa sobre desenho de incentivos

[Anderson et al., Steering User Behavior with Badges](https://ai.stanford.edu/~jure/pubs/badges-www13.pdf)
estuda como badges orientam comportamento em uma comunidade. A aplicação ao
Portal é uma inferência de projeto: avaliar se cada critério estimula contribuição
útil ou somente quantidade de posts. Não atribuir ao estudo uma garantia de resultado.

[Sailer e Homner, meta-análise de gamificação na aprendizagem](https://link.springer.com/article/10.1007/s10648-019-09498-w)
analisa contextos e resultados educacionais distintos. Não extrapolar um efeito
uniforme para a ACULTA; medir critérios e participação no próprio contexto.

Proposta: progresso compreensível, critérios visíveis e reconhecimento cultural;
avaliar qualidade, acessibilidade, privacidade e esforço de moderação. Publicidade
de conquistas não torna obrigatório publicar suas evidências.
