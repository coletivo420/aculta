# ACULTA Forum Legacy

Addon opcional do `aculta_forum` para a integração do arquivo histórico
Growroom. Projetos que não adotam esse arquivo não precisam instalar este
módulo; o Fórum principal não depende dele.

## Fase atual — Dev.2

O addon fornece o provider tagged que reconhece `ARCHIVE` em tópico Growroom,
Comment ligado a esse tópico ou ForumPost originado nele quando a referência
de autoria está presente. O contrato genérico, os resultados de classificação
e os consumidores de escrita, citação, reação, participação e avisos pertencem
aos módulos principais. Posts correntes exigem autoria Drupal positiva; origem
ausente ou conflitante falha fechada.

A extração de autoria e campos, memorial coletivo, apresentação histórica,
ferramentas de importação/auditoria, moderação e integrações ainda não foi
concluída. O código principal ainda contém acoplamentos a remover em etapas.
A importação Growroom não foi executada no Runtime alvo. O pipeline futuro deve
importar o corpus aprovado sem converter Comments históricos para ForumPosts.

O addon declara dependência somente do `aculta_forum` para publicar seu provider.
`aculta_forum`, Portal, User, CGU, Index, Statistics, Messenger, Game, Wiki,
Revista e LMS não dependem deste addon.
