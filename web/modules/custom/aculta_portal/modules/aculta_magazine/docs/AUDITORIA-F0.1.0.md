# ACULTA Magazine — auditoria F0.1.0

**Data:** 2026-10-21
**Escopo:** auditoria somente leitura do código, Config Sync e Runtime de desenvolvimento.

## Ambiente e limites da auditoria

- Host: `toca`; checkout: `/home/piradopirata/projetos/aculta-site`.
- Branch: `docs/aculta-forum-0.1.0-dev.1`; HEAD ao concluir a sincronização concorrente: `b5e6650bf293f3bd6f308fe752fcdc847f3a381f`.
- Drush confirmou Drupal 11.4.10, banco `aculta_runtime`, conexão válida e URI padrão `http://default`.
- PHP CLI disponível: 8.4.26; abaixo da baseline de projeto PHP 8.5.
- Nenhuma entidade, configuração, arquivo de conteúdo ou banco foi alterado nesta auditoria. Nenhuma exclusão ou backup foi executado.
- No início, a worktree tinha alterações concorrentes em Fórum, User, PDF Viewer e documentação. Elas foram preservadas. Durante a auditoria, o HEAD foi movido para a ref remota da própria branch da PR (`git reflog`: `reset: moving to origin/docs/aculta-forum-0.1.0-dev.1`); não foi uma operação deste trabalho. A worktree continuou com mudanças fora do Magazine.

## Código atual do Magazine

- `RevistaPublications::BUNDLES` declara `article` e `blog` como publicações.
- `PublicationTypeConverter` cria um Node no bundle de destino, copia campos presentes, move Comments de `field_aculta_discussion`, remove alias antigo e exclui o Node original. A operação não preserva nid/UUID nem o histórico completo de revisões e não trata referências externas de forma geral; não deve ser usado para consolidar conteúdo.
- `ArticleAuthorsHooks` prepara autoria e cabeçalho no display full para os dois bundles, consumindo o cartão editorial de `aculta_user`.
- `PublicationDiscussionHooks` fornece status padrão em memória para o Comment field nos dois bundles.
- `PostPresentation` prepara data, autoria e links de compartilhamento em render arrays neutros; é reaproveitável após a consolidação.
- O módulo instala as duas instâncias de Comment e as Views administrativas. A página editorial é `view.aculta_magazine_admin_content.page_magazine`, em `/painel-administrativo/conteudo/revista`; a fila de comentários entra como bloco na página. Não existe rota separada de comentários.

## Configuração versionada

- `node.type.article` e `node.type.blog` existem; ainda não existe `node.type.revista`.
- Ambos possuem form displays e displays default/full/teaser.
- Os campos editoriais incluem corpo, chamada (`field_subtitle`), resumo (`field_summary`), capa (`field_media_image`), autoria (`field_editorial_author` e contrib `co_authors`), categoria (`field_editorial_category`), tags, créditos, referências, projeto relacionado, atividade relacionada, data editorial (`field_published_at`), Metatag e discussão Comment.
- O vocabulário existente `editorial_category` atende ao destino taxonômico aprovado. O armazenamento de campos é global por entidade Node: remover field storage sem mapear os demais bundles consumidores seria inseguro.
- Pathauto `aculta_article`, Metatag `node__article`/`node__blog`, Views `revista_sessoes`, `aculta_magazine_admin_content`, `aculta_magazine_admin_comments`, `aculta_news` e `aculta_related_news`, e o índice Search API `aculta_conteudo` têm referências editoriais a considerar.
- `workflows.workflow.editorial` é a configuração editorial existente; qualquer associação ao novo bundle deve ser conferida contra todos os bundles atualmente atendidos.
- O Runtime já apresentava drift de Config Sync em configurações não relacionadas antes de qualquer importação; nenhum `config:import` foi executado.

## Consumidores fora do Magazine

`article`/`blog` não são exclusivos do Magazine. O inventário de código/config encontrou, entre outros:

- `aculta_forum` reconhece publicações para seu provider contextual; comentários continuam hospedados no Node editorial.
- `aculta_lms_resources` e a configuração em Config Sync de `field_lms_readings` permitem referências a `article`/`blog` como leituras de curso. A tabela de valores `group__field_lms_readings` não existe no Runtime consultado; portanto não foi possível concluir contagem de referências ativas por essa tabela. O consumidor de configuração/código precisa ser atualizado ou preservado.
- O índice Search API, views de notícias/relacionadas e as configurações Pathauto/Metatag cobrem conteúdo `article`/`blog`.
- ACULTA420 possui templates e o SDC `revista-publication` específicos para esses bundles.
- `project` permanece separado; a contagem Runtime consultada foi cinco Nodes `project`.

As referências de exemplos demonstrativos de Views de contrib não são automaticamente consumidoras editoriais e devem ser separadas das Views ativas do site ao limpar configuração.

## Runtime de desenvolvimento — inventário de Nodes

Consulta somente de IDs, bundle, publicação e título; nenhum corpo de conteúdo foi exportado.

| NID | Bundle | Estado | Título/identificação |
| ---: | --- | --- | --- |
| 90 | article | publicado | Novo Exame Toxicológico da CNH e Maconha: Preparação para o Exame e Redução de Danos |
| 92 | blog | publicado | Bloco Sativa é objeto de Pesquisa na FIOCRUZ |
| 97 | blog | publicado | Cuidar não é crime: apoio à SouCannabis |
| 101 | blog | publicado | PodPlant420 #01 – Pode Plantar? |
| 102 | article | publicado | Anvisa avança no cânhamo, mas trava no autocultivo medicinal e uso adulto |
| 104 | article | publicado | STF Derruba Lei Municipal que proíbe Marcha da Maconha |
| 106 | article | publicado | Instagram censura perfis antiproibicionistas no Brasil |
| 109 | article | publicado | Multa por “uso de drogas” em Goiânia |
| 111 | article | publicado | CNJ propõe envio compulsório de usuários após a descriminalização |
| 113 | article | publicado | Portaria 1.342/2025: um novo capítulo na luta pela regulação da maconha no Brasil |
| 115 | article | publicado | Adeus a Pepe Mujica, o Líder que Legalizou a Maconha no Uruguai |
| 123 | blog | publicado | Entrevista com Diego Castro |
| 288 | article | não publicado | `aculta-image-r2-temporary-20261016` |
| 290 | article | não publicado | `aculta-image-r3-temporary-20261016` |
| 375 | article | publicado | `TEMP PV6 article aculta-pv6-pv7-20261017` |
| 376 | blog | publicado | `TEMP PV6 blog aculta-pv6-pv7-20261017` |

Totais consultados: 9 `article` publicados, 2 `article` não publicados e 5 `blog` publicados. Os 12 primeiros são conteúdo editorial identificável, portanto não estão classificados como descartáveis. Os quatro identificados explicitamente como temporários/testes são candidatos à limpeza, ainda sujeitos a backup e verificação de referências conforme o procedimento autorizado.

Outros resultados somente leitura:

- 11 revisões para `article` e 5 para `blog` na tabela de revisões.
- 26 aliases de caminho associados aos Nodes consultados.
- Zero linhas em `comment_field_data` para `field_aculta_discussion` e zero itens persistidos do campo nos bundles consultados.
- Os campos dos Nodes consultados apontam para 12 Media (12 IDs), duas categorias editoriais, dois Domains, um Node `project` e três termos de tags. Esses destinos são compartilhados e ficam fora de qualquer exclusão editorial.
- `entity_usage` está instalada e registra zero referências rastreadas com destino nesses Nodes. Isso não substitui a verificação de referências dos campos Drupal, links embutidos, Views e aliases.
- A tabela `group__field_lms_readings` não existe no Runtime; ausência dessa tabela não comprova ausência de referências configuradas.
- Zero Redirect entities têm como origem os caminhos `/node/{nid}` desses Nodes.

## Riscos e sequência proposta

1. Os 12 Nodes editoriais publicados são conteúdo aparentemente legítimo. Não podem ser apagados pela autorização geral de limpeza de legado. Preservar/exportar e obter decisão específica de migração/exclusão antes de remover seus bundles.
2. Os quatro Nodes com identificação de teste podem ser removidos somente após backup recuperável, inventário de Entity Usage, aliases/redirects, referências, comentários, revisões, Domain, Media e arquivos compartilhados, usando APIs de entidade e sem exclusão indireta de entidades compartilhadas.
3. Antes da aposentadoria dos bundles, revisar `aculta_lms_resources`, provider contextual do Fórum, Search API, Views, Pathauto, Metatag, Workflow, ACULTA420, aliases e referências dos outros módulos. Atualizar os consumidores para `revista` ou para uma regra editorial compatível.
4. Criar `revista` e suas instâncias de campo/displays sem apagar as configurações antigas; validar Forms, Views, workflow, SEO e apresentação antes de limpeza.
5. Inventariar relações e tipos de campos individualmente. Preservar storages globais e conteúdo de Projetos, Galeria, Media, documentos, usuários e comentários de outros hospedeiros.
6. Fazer backup de banco e configuração e confirmar URI/banco imediatamente antes de cada operação persistente. O drift atual impede tratar uma importação integral de Config Sync como etapa segura.

## Arquivos centrais auditados

- `src/Revista/RevistaPublications.php`
- `src/Forum/PublicationTypeConverter.php`
- `src/Hook/ArticleAuthorsHooks.php`
- `src/Hook/PublicationDiscussionHooks.php`
- `src/Presentation/PostPresentation.php`
- `config/install/*` do Magazine e configurações editoriais relevantes em `config/sync/`
- Documentação/contratos consultados: ADR-011, hierarquia de kernels, roadmap Studio/Magazine, README/CHANGELOG do Magazine, arquitetura e ownership de Gallery/Forum, mapa módulo-tema, fronteira Portal–tema e contratos de Drupal 11.
