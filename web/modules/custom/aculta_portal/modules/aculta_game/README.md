# ACULTA Game — conquistas e missões

Versão atual: `0.1.0-dev.7` — G5.2, com apresentação, avisos e e-mail de conquistas.

Submódulo opcional `aculta_game`, integrante do `aculta_portal`.
Decisões de produto registradas em **2026-10-15**. G1 fornece a extensão instalável;
G2 corrige os gatilhos; G3 integra conquistas e escolhas de exibição à Minha Conta
e ao perfil público. Homologação funcional e fases seguintes estão registradas
em [EXECUCAO.md](docs/EXECUCAO.md). G4 habilita três missões unitárias com progresso privado.

## Proposta

Reconhecer contribuições culturais, comunitárias e de aprendizagem por conquistas
e missões. Integrar as fontes reais do Portal e apresentar progresso compreensível,
sem ranking, pontuação competitiva, XP/níveis ou benefícios financeiros.

**Conquistas são públicas por padrão**, com opção de ocultar, escolher quais ficam
em destaque e organizar sua apresentação. A opção de privacidade da pessoa tem
precedência. Evidências privadas de curso, pedido ou perfil não se tornam públicas
porque uma conquista pode ser exibida.

## A base já existente

O `aculta_user` mantém `BadgeCatalog` (IDs/defaults), `AchievementDefinitions`
(Config API), `BadgeService` e a entidade
`aculta_badge_award`. O `BadgeEvaluationHooks` agora pertence ao Game.
O catálogo inicial tem sete conquistas: Fundadore, Apoiadore,
Associado/a Antiproibicionista,
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

G4: `0.1.0-dev.4`. A Visão geral oferece a entrada **Conquistas e missões**.
Em `/atividade/conquistas`, acompanhe contribuição no Growroom420, revisão
publicada da Wiki e primeiro curso aprovado. Progresso consulta conquistas reais,
sem XP, ranking ou novo storage. Controle administrativo **Game**, abaixo de
**Avisos**, em `/painel-administrativo/game` no MAIN: ativa/desativa missões;
concessão manual e auditoria disponíveis desde G5.1.

Evidência por fase: [execução](docs/EXECUCAO.md).

Pesquisa no checkout atual, branch `chore/messenger-pendencias`, PR #286,
com fontes públicas e quatro pacotes de código inspecionados fora da instalação.
Nenhum código upstream foi incorporado.

## Administração e apresentação de conquistas — G5.1–G5.2

Em **Game → Conquistas**, crie e edite nome, descrição, disponibilidade,
imagem do catálogo e formas de concessão. Apoiadore aceita pedido concluído,
apoio concluído no sistema de doações e administrador. Também há gatilhos para
resposta publicada, revisão Wiki publicada, curso aprovado e perfil básico.

**Conceder ou revogar** exige motivo e guarda ator/data. **Histórico** consulta
as entidades reais; concessões automáticas permanecem históricas, e as manuais
podem ser revogadas. Não são concedidos papéis, descontos, moeda ou privilégios.

As definições pertencem à Config API de User, não ao tema. Remover Game preserva
concessões, definições e privacidade do User. O editor organiza identificação,
imagem e condições em grupos; remoção da imagem exige confirmação e exclui o
arquivo enviado quando não há outro uso. Conquistas novas geram aviso dispensável
no Messenger e e-mail enfileirado, com preferência individual de recebimento.
Entrega de e-mail depende do cron e da configuração do ambiente.

Para arte de conquista, recomendamos imagem quadrada de **512 × 512 px**. O
formulário aceita PNG, JPG ou WebP até 2 MB, com dimensão máxima de 4096 × 4096
px. No perfil público, definições sem imagem própria usam o símbolo ilustrativo
`assets/conquistas/conquista-padrao.svg`.
