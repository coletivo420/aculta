# ACULTA Forum Legacy

Addon opcional do `aculta_forum` para a integração do arquivo histórico
Growroom. Projetos que não adotam esse arquivo não precisam instalar este
módulo; o Fórum principal não depende dele.

## Entidades próprias do acervo — Dev.6

O acervo Growroom tem entidades próprias, separadas das entidades correntes do
Fórum (`node`, `comment`, `aculta_forum_post`):

- `aculta_forum_legacy_user`: autoria histórica.
- `aculta_forum_legacy_topics`: tópicos históricos.
- `aculta_forum_legacy_comments`: respostas históricas.
- `aculta_forum_legacy_attachments`: anexos históricos, com URI própria.

Comportamento previsto, sem exceção: o acervo é somente leitura. Não aceita
comentários, reações, moderação nem criação pela interface. O handler de acesso
permite apenas `view` a quem tem `administer aculta legado`. A importação deve
usar a API de entidade e não a interface.

`LegacyDiscussionOriginClassifier` classifica essas entidades como `ARCHIVE`
sem depender de marcador. A remoção de reações foi decisão explícita: o acervo
não as recebe e o motor corrente não as lê.

O update `aculta_forum_legacy_update_8001` cria as quatro tabelas. Ele não foi
aplicado no Runtime: depende de autorização e snapshot. A migração das linhas
marcadas com `field_autor_legado` em `node` e `comment` ainda não começou.

## Fase anterior — Dev.5

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

A entidade `aculta_forum_legacy_user`, seu handler, manager/serviço, fields, memorial coletivo e apresentação inline da autoria já pertencem ao addon. Os defaults do motor não instalam os fields históricos. A configuração versionada de instalação limpa e sync reflete o novo owner; nenhuma operação estrutural foi executada no Runtime. A reserva do slug e as ferramentas de importação/auditoria ainda não foram extraídas. A rota pública do memorial mantém `/u/lendashistoricas` no domínio MAIN; não cria perfis individuais. O código principal ainda contém acoplamentos a remover em etapas.
A importação Growroom não foi executada no Runtime alvo. O pipeline futuro deve
importar o corpus aprovado sem converter Comments históricos para ForumPosts.

O addon depende de `aculta_forum` para classificar a origem e de `aculta_user` para reutilizar o avatar institucional e o contrato genérico de cartão. Essas dependências são unidirecionais: `aculta_forum`, Portal, User, CGU, Index, Statistics, Messenger, Game, Wiki, Revista e LMS não dependem deste addon.
