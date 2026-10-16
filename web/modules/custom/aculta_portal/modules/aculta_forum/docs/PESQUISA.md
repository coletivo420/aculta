# Pesquisa — Fórum, comunidade e integração

Consulta: 2026-10-15. Fonte local inicial: `868626d`. Esta pesquisa distingue
capacidade anunciada, código inspecionado e comportamento que ainda exige Runtime.

## Soluções e referências

| Fonte primária | Constatação | Aplicação proposta |
| --- | --- | --- |
| [Forum](https://www.drupal.org/project/forum) e [visão geral](https://www.drupal.org/docs/contributed-modules/forum/overview) | Forum 1.1.3 usa Node, Comment, Taxonomy e History; migrou do Core para contrib no Drupal 11 | Preservar a base instalada |
| [Drupal.org Forums](https://www.drupal.org/forum) | Áreas com descrição, contagens e última atividade | Referência estrutural; interface atual do ACULTA420 |
| [Discourse](https://www.discourse.org/features) | Respostas lineares com contexto, navegação pessoal e moderação integrada | Inspiração de UX; não instalar outra plataforma |
| [Flarum](https://flarum.org/) | Foco em discussões, navegação compacta e interação móvel | Protótipo de listagem e leitura |
| [NodeBB](https://docs.nodebb.org/activitypub/privileges/) | Privilégios de leitura/postagem por categoria e grupo | Tornar as fronteiras de acesso compreensíveis |
| [Open Social](https://www.drupal.org/project/social) | Distribuição Drupal de comunidade e conhecimento | Referência de integração; sobreporia a base própria se adotada integralmente |
| [Growroom arquivado](https://web.archive.org/web/20250719093640/https://growroom.net/board/) | Estrutura histórica e IDs recuperáveis nas amostras baixadas | Fonte da reconstrução; não inferir integralidade |

A recomendação de permanecer no Forum resulta do encaixe no Portal, não de
equivalência de funcionalidades com outras plataformas. Recursos como live chat,
rolagem contínua e reações complexas exigem escopo e validação próprios.

Comunidades temáticas também foram consultadas: [GrowRoom420](https://www.growroom420.com/app.php/rules)
(distinto do Growroom brasileiro) e [Percy's Grow Room](https://percysgrowroom.com/forum/forum-guidelines/moderation-policies/).
Suas regras destacam convivência, privacidade e limites de publicidade. São
referências de organização comunitária, não normas jurídicas aplicadas à ACULTA.

## Código e configuração existentes

| Evidência no repositório | Leitura confirmada | Consequência |
| --- | --- | --- |
| `composer.lock`; `web/modules/contrib/forum` | Forum 1.1.3, Flag 5.1.1, LMS 1.2.3, Group 3.3.5 e Private Message 3.0.7 | Não instalar alternativas sem necessidade |
| `aculta_user/src/Hook/DiscussionHooks.php` | Trechos de comentários do tópico vinculado; checa publicação, não acesso explícito do tópico | Endurecer o contrato antes do LMS privado |
| `aculta_magazine/src/Forum/ForumTopicSync.php` e `ForumSectionResolver.php` | Um tópico por publicação e subpastas sob Revista | Não duplicar discussão nem reorganizar IDs por estética |
| `aculta_user/src/Hook/AccountCreationHooks.php` | Cria perfil participante e atribui avatar ao salvar User novo | Importação exige distinguir conta histórica |
| `aculta_user/src/Social` e `aculta_messenger` | Amizade e mensagens privadas já existem | Reutilizar acesso e APIs; não prometer chat em tempo real |
| `config/sync/search_api.index.aculta_conteudo.yml` | Lista exportada não inclui `forum` nem datasource de Comment | Busca do Fórum ainda requer entrega própria |
| `aculta_messenger/src/Hook/MessageSubscribeEventHooks.php` | Eventos de publicação inicial do tópico | Nova resposta ainda precisa de adaptação |
| `flag/src/Plugin/Flag/CommentFlagType.php` | Tipo `entity:comment` existe | Denúncia depende de configuração/fluxo; a frase contrária em DT-P13 é obsoleta |
| `aculta_gallery/src/Hook/GalleryPurposeAccessHooks.php` | Acesso a galerias em purpose MAGAZINE | Referenciar a galeria; não embuti-la no Fórum para contornar D13 |

O estado de configuração exportada não comprova o estado atual do Runtime.

## Dependências candidatas e limites

- [Message Subscribe](https://www.drupal.org/project/message_subscribe): pilha
  instalada, compatível com Flag 5 na linha 2.1. Candidato preferencial para
  estender assinaturas, sem duplicar entrega de e-mail.
- [Comment Notify](https://www.drupal.org/project/comment_notify): alternativa
  estável para e-mail de comentários. A fonte de verdade antiga o cita, mas ele
  não consta dos pacotes instalados conferidos; resolver ownership antes de adotar.
- [Entity Links do Core 11.3](https://www.drupal.org/node/3524296): autocomplete
  já existe no código instalado. Avaliar antes de adicionar Linkit.
- [Mentions](https://www.drupal.org/project/mentions): sem stable suportada na
  página consultada. [CKEditor5 Mentions](https://www.drupal.org/project/ckeditor5_mentions)
  declara D11, mas não tem cobertura de security advisories. Nenhum está aprovado.
- [Achievements](https://www.drupal.org/project/achievements): investigar no
  `aculta_game`, sem criar concessões paralelas no Fórum.

## Acesso reservado exige comprovação

[Search API: processors](https://www.drupal.org/docs/8/modules/search-api/getting-started/processors)
documenta verificações para conteúdo e comentários e ressalva mecanismos de acesso
de contrib. [Group #3198115](https://www.drupal.org/project/group/issues/3198115)
discute integração com Search API. O código local de ForumManager usa consultas
próprias, que também precisam de verificação com Group/`gnode`.

Não assumir que um grupo ou filtro visual garante privacidade. Render arrays
precisam preservar [cacheability](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays)
das decisões reais, inclusive nos links, datas, contagens e autores.

## Protótipo a avaliar

Home híbrida com áreas e recentes; filtros de acervo/comunidade atual; seguidos,
salvos, sem resposta e participação própria. Tópico cronológico com contexto de
resposta, caixas de autor atuais ou históricas e ações permitidas. No celular,
uma coluna e controles curtos. Light/dark compartilham DOM, layout e componentes.
Não recuperar ranking antigo como funcionalidade contemporânea.
