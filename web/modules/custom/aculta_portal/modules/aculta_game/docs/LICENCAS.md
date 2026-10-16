# Licenças e reaproveitamento — ACULTA Game

Estado em 2026-10-15: **nenhum trecho de código de terceiros incorporado**.
Foram inspecionados os pacotes listados no [manifesto](FONTES-UPSTREAM.json),
incluindo `composer.json`, `LICENSE.txt`, metadados e arquivos de implementação.
O `composer.json` deste repositório declara `GPL-2.0-or-later`.

## Evidência dos pacotes

| Release | Declaração no composer.json | Arquivo de licença | Possibilidade pesquisada |
| --- | --- | --- | --- |
| Achievements 3.0.3 | `GPL-2.0-or-later` | GPL versão 2 | Estudar definição de marco e `achievements_unlocked()`; adaptação delimitada possível sob a licença |
| ECA 3.1.9 | `GPL-2.0-or-later` | GPL versão 2 | Preferir API/plugins via dependência, caso adotado; não copiar o motor |
| Rules 4.0.1 | `GPL-2.0-or-later` | GPL versão 2 | Estudar condições/ações; API como alternativa ao ECA |
| Gamification 2.0.2 | `GPL-2.0+` | GPL versão 2 | Expressão legada de GPL 2 ou posterior; estudar limites por fonte sem trazer pontos |

A [política de licenças do Drupal](https://www.drupal.org/about/licensing) permite
reutilização e modificação de código GPL compatível sob seus termos. A
[política de repositórios](https://www.drupal.org/docs/develop/git/setting-up-git-for-drupal/drupal-git-usage-policies/drupal-git-contributor-agreement-repository-usage-policy)
identifica `GPL-2.0-or-later` para obras derivadas Drupal ali hospedadas.
As declarações específicas acima foram confirmadas no artefato, não inferidas
apenas por o projeto ter uma página Drupal.org.

## Instalar, adaptar e estudar são operações diferentes

- **Instalar por Composer:** opção preferida quando a API resolve a capacidade;
  preservar ownership upstream e atualizações. Não editar contrib diretamente.
- **Adaptar um trecho delimitado:** admissível com licença compatível, proveniência
  e manutenção assumida. Reescrever DI, Entity API e hooks conforme Drupal 11+
  quando necessário; trocar sintaxe não elimina obrigações de atribuição.
- **Estudar um conceito:** esta entrega registra arquitetura e alternativas.
  Não apresentar uma implementação própria como código já licenciado de terceiro
  quando nenhum trecho foi incorporado.

## Registro exigido antes de incorporar código

Para cada arquivo/trecho: projeto, versão ou commit fixo, URL, caminho original,
checksum do pacote, licença, titulares/avisos existentes, destino e modificações.
Manter os avisos de autoria/licença aplicáveis e registrar alterações e data,
conforme os termos da [GNU GPL versão 2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).
Na distribuição, cumprir as obrigações de fonte/licença do componente derivado.

Um ledger de reutilização acompanhará o PR que efetivamente copiar código. Seu
estado inicial é vazio: `FONTES-UPSTREAM.json` é inventário de pesquisa, não prova
de que o projeto usa as quatro dependências.

Arte, fontes, imagens de badges e marcas têm licenças próprias. A licença do
módulo não é permissão automática para copiar qualquer asset ou marca associado.
Histórico e posts do Growroom também não recebem a licença GPL dos módulos.

## O que vale aproveitar tecnicamente

| Origem/caminho | Ideia ou trecho candidato | Limite de adaptação |
| --- | --- | --- |
| `achievements/achievements.module`, `achievements_unlocked()` | Concessão com transação | Não transportar ranking, tabelas ou callbacks globais; projetar para o backend único existente |
| `achievements/src/Entity/AchievementEntity.php` | Definições configuráveis de marcos | Respeitar decisões sem pontos e critérios versionados |
| `gamification/src/Plugin/GamificationWidgetUserpointsBase.php` | Limites de disparo por fonte | Evitar dependência de pontos; avaliar se a própria Queue/Lock API já basta |
| APIs de ECA/Rules | Adaptadores de eventos e condições | Se escolhidos, usar APIs públicas e declarar dependência; não manter motores copiados |

O encaixe técnico, a segurança e a manutenção continuam necessários mesmo
quando a licença permite adaptação. Nenhum desses trechos está aprovado para
cópia indiscriminada ou dispensa revisão do código resultante.
