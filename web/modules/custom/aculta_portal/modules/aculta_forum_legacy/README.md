# ACULTA Forum Legacy

Addon opcional do `aculta_forum` para a integração do arquivo histórico
Growroom. Projetos que não adotam esse arquivo não precisam instalar este
módulo; o Fórum principal não depende dele.

## Fase atual — Dev.3

O addon fornece o provider tagged que reconhece `ARCHIVE` em tópico Growroom e
Comment ligado a tópico marcado com autoria histórica. ForumPosts são conteúdo
corrente e não representam a origem do arquivo. O contrato genérico, os
resultados de classificação e os consumidores de escrita, citação, reação,
participação e avisos pertencem aos módulos principais. Posts correntes exigem
autoria Drupal positiva; origem ausente ou conflitante falha fechada.

O addon também passou a registrar a exclusão do arquivo Growroom das filas
ordinárias de moderação. A política genérica de denúncias do Fórum permanece no
motor e usa o resolver de origem; o motor não lê campos ou entidades de autoria
legada diretamente.

A extração da entidade de autoria, fields, memorial coletivo, apresentação
histórica, ferramentas de importação/auditoria e integrações ainda não foi
concluída. O código principal ainda contém acoplamentos a remover em etapas.
A importação Growroom não foi executada no Runtime alvo. O pipeline futuro deve
importar o corpus aprovado sem converter Comments históricos para ForumPosts.

O addon declara dependência somente do `aculta_forum` para publicar seu provider.
`aculta_forum`, Portal, User, CGU, Index, Statistics, Messenger, Game, Wiki,
Revista e LMS não dependem deste addon.
