# ACULTA Game — conquistas e missões

Submódulo opcional `aculta_game`, integrante do `aculta_portal`.
Decisões de produto registradas em **2026-10-15**. G1 fornece a extensão instalável;
G2 corrige os gatilhos; G3 integra conquistas e escolhas de exibição à Minha Conta
e ao perfil público. Homologação funcional e fases seguintes estão registradas
em [EXECUCAO.md](docs/EXECUCAO.md). Ainda não há catálogo de missões habilitado.

## Proposta

Reconhecer contribuições culturais, comunitárias e de aprendizagem por conquistas
e missões. Integrar as fontes reais do Portal e apresentar progresso compreensível,
sem ranking, pontuação competitiva, XP/níveis ou benefícios financeiros.

**Conquistas são públicas por padrão**, com opção de ocultar, escolher quais ficam
em destaque e organizar sua apresentação. A opção de privacidade da pessoa tem
precedência. Evidências privadas de curso, pedido ou perfil não se tornam públicas
porque uma conquista pode ser exibida.

## A base já existente

O `aculta_user` mantém `BadgeCatalog`, `BadgeService` e a entidade
`aculta_badge_award`. O `BadgeEvaluationHooks` agora pertence ao Game.
Há seis conquistas no catálogo: Apoiador, Associado,
Primeiro comentário no Fórum, Primeira edição na Wiki, Primeiro curso realizado
e Perfil básico completo. Sua existência em código não comprova cada gatilho em Runtime.

O novo projeto precisa integrar essa base e corrigir suas lacunas antes de
decidir uma mudança de backend. Não criar outra tabela de concessões para
espelhar o mesmo fato, nem manter duas avaliações concorrentes.

## Recomendação após a pesquisa

1. Preservar inicialmente o backend de concessões existente e planejar sua
   evolução controlada, sem mudança abrupta de provider/ID de entidade.
2. Usar APIs Core, fontes reais, critérios predefinidos e configuração exportável.
3. Avaliar ECA como motor opcional se a autoria visual de automações justificar
   as dependências; o motor sempre chamará a API única de concessão.
4. Usar Achievements como referência técnica ou candidato a prova de conceito,
   não como segunda fonte de concessões. A versão moderna mantém estruturas de ranking.
5. Não adotar User Points/Gamification para um produto que não usa pontos.

Essas são recomendações técnicas, não aprovação para instalar módulos. As
diferenças entre páginas de projeto e pacotes foram verificadas na pesquisa.

## Documentos

- [Decisões e pendências](docs/DECISOES.md).
- [Arquitetura e catálogo inicial de missões](docs/ARQUITETURA.md).
- [Pesquisa de módulos e evidência](docs/PESQUISA.md).
- [Licenças e reaproveitamento de código](docs/LICENCAS.md).
- [Manifesto dos pacotes inspecionados](docs/FONTES-UPSTREAM.json).
- [Roadmap e aceite](docs/ROADMAP.md).
- [Changelog](CHANGELOG.md).
- [ACULTA Fórum/Growroom](../aculta_forum/README.md).

Normas: [Drupal 11+](../../../../../../docs/portal/DRUPAL-11-STANDARDS.md),
[fontes de verdade](../../../../../../docs/portal/SOURCE-OF-TRUTH.md) e
[design system](../../../../../../docs/portal/COMPONENT-DESIGN-SYSTEM.md).

## Estado e versão

G1: `0.1.0-dev.1`, classificação MINOR. Integração em uma única direção:
Game depende de User e Portal; User/Portal não dependem de Game. A ativação é
pontual por `drush en aculta_game`, sem dependência Composer nova. Remover Game
interrompe a avaliação automática; a entidade e suas concessões continuam no
User. Não desinstalar User como forma de desativar gamificação.

G3: `0.1.0-dev.3`, classificação MINOR. Em Minha Conta → Atividade → Conquistas,
a pessoa vê suas conquistas e configura ocultação global/individual, destaques e
ordem. Configurações → Privacidade oferece um link para esses controles. O perfil
público usa a mesma projeção, respeitando privacidade geral, conta ativa e legado.
Preferências pertencem ao User Data de `aculta_user`, para sobreviver à remoção
do Game. Sem rota nova, biblioteca visual nova ou mudança de tema nesta fase.

Evidência por fase: [execução](docs/EXECUCAO.md).

Pesquisa sobre o checkout `868626d`, branch `chore/messenger-pendencias`, PR #286,
com fontes públicas e quatro pacotes de código inspecionados fora da instalação.
Nenhum código upstream foi incorporado. Documentação não avança versão de runtime;
o primeiro código deverá classificar a mudança e anotar a subversão antes de codar.
