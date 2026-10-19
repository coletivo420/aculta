# Arquitetura alvo — ACULTA Forum

Status: contrato documental da reconstrução `0.1.0-dev.1`; não descreve motor
executável. Base de auditoria: `origin/main` em `d4a0d66e` (2026-10-19).

## Fronteiras e responsabilidades

```text
Drupal Core (Node, Comment, Taxonomy, Views, History, Search API, Flag, Group)
                         ↓
aculta_forum (contextos, política, navegação e integração)
       ↙                 ↓                   ↘
aculta_user       aculta_messenger           aculta_image
cartões/identidade avisos/entrega             ingestão/renderização
                         ↓
              contrato neutro de apresentação
                         ↓
                    ACULTA420
```

- Core e contrib mantêm as entidades e capacidades genéricas; não criar entidades de respostas, categorias ou mensagens que as dupliquem.
- `aculta_forum` será dono da API compartilhada de contexto, das regras de discussão específicas do ACULTA e da integração entre contextos.
- `aculta_user` é o único dono dos dados de identidade e dos view-models/cartões de perfil, autor e participante. Forum consome a API, sem consultar campos privados ou montar cartão próprio.
- `aculta_messenger` é o único dono da entrega de avisos e preferências/canais já existentes; Forum publica eventos/contexto mínimos e não cria filas ou tabelas de notificação.
- `aculta_image` é a passagem obrigatória para qualquer ingestão, processamento, renderização ou remoção de imagens.
- `aculta_lms`, Group, Magazine, Wiki/Node e Game permanecem autoridades de seus próprios dados.
- ACULTA420 detém markup, CSS, SDC e tokens; recebe apenas render arrays e dados escalares preparados, acessíveis e cacheáveis. Não faz consultas, decisões de acesso ou lógica de domínio.

## Motor e armazenamento

O motor combina Node para tópicos independentes, Comment para respostas, Taxonomy
para categorias, Views para listagens e descoberta, Search API para pesquisa,
History/serviços revisados para leitura, Flag para seguir/salvar/denunciar,
Entity/Access/Render/Cache APIs para persistência e exposição. Form API preserva
criação, edição e moderação nativas onde adequadas.

Bundles, campos, permissões e machine names propostos estão em
[`CONTEXTS.md`](CONTEXTS.md). O uso de um único tipo de Comment para diferentes
bundles Node precisa ser provado por instalação e testes Kernel no código do Core
instalado antes de congelar a configuração. A API Comment associa um campo a uma
entidade hospedeira; o armazenamento compartilhado é possível entre bundles do
mesmo entity type se a configuração e os access handlers forem compatíveis. Não
presumir um field storage que atravesse entity types diferentes. Se for necessário
mais de um tipo/campo Comment, manter uma única API funcional e visual.

Os tópicos públicos independentes usarão o propósito interno `forum`, que continua
existente. O host público e redirects seguem DomainPurposeManager e o mapa de
rotas; nenhuma URL absoluta ou hostname de ambiente é fixada em código/configuração.

## Quatro contextos

1. **Growroom420 público:** Nodes de tópico independente em categorias/subcategorias públicas. Só este conteúdo entra na experiência/listagens públicas do Growroom. Discussões gerais de cursos podem ser criadas aqui; assuntos internos de curso, turma e atividade não.
2. **Publicações/Revista:** Comment associado à própria publicação. Nenhum tópico público é criado automaticamente. O comentário aparece na página editorial, mantém a rota/âncora canônica e reutiliza ações, ferramentas e cartão `author_comment`.
3. **LMS:** Node de tópico interno associado a curso e, quando necessário, turma, atividade ou contexto pedagógico. Group membership e Entity Access atuais são revalidados em cada rota, formulário, View, busca, render, arquivo e entrega de aviso. Papel global `estudante` não concede acesso transversal.
4. **Wiki420:** contrato e provider planejados para uma discussão por verbete, via Comment no Node Wiki, dentro da página do verbete. A revisão editorial do verbete continua separada. Esta fase não reimplementa a Wiki.

`DiscussionContextResolver` (nome conceitual) resolve um provider para uma
entidade/thread e retorna identificador estável, tipo, host, regras de leitura e
escrita, moderação, URL canônica, visibilidade, política de busca/notificação e
cacheability. Provider público, publicação e LMS são contratos da implementação;
provider Wiki permanece planejado até a reimplementação da Wiki. A API não pode
confiar em identificadores de contexto vindos do cliente sem revalidar a entidade.

## Acesso, busca, cache e indexação

- Revalidar Entity Access e Group membership atual em todos os pontos que leem ou escrevem.
- Listagens e índices Search API segregam contextos. Tópicos, respostas, trechos, contagens, autores, facet, sitemap, feed e notificação restritos não podem aparecer a quem não tem acesso.
- Filtros de acesso e domínio ficam fixos nas Views. CTools não pode expor controles que alterem acesso, filtros, campos ou ordenação.
- Render arrays carregam cache tags, contexts e max-age de entidade, resultado de access, usuário/permissões, Group/membership e conteúdo relacionado. Não cachear uma resposta privada como pública.
- Contagens e estatísticas são serviços cacheáveis ou materializadas por mecanismos suportados; não executar agregações caras a cada render.
- Comentário, arquivos anexos e imagem usam APIs e políticas dos respectivos módulos donos.

## Integrações

| Sistema | Contrato previsto |
| --- | --- |
| `aculta_user` | Preparar identidade e variantes de cartão; ocultar campos não públicos; resolver autoria legada coletiva. |
| `aculta_magazine` | Substituir sincronização de tópicos pela renderização de comentários na entidade editorial; preservar autoria editorial. |
| `aculta_lms` / Group | Adapter opcional sem ciclo de dependências; verificar membership, papéis de grupo e autorização real. |
| `aculta_messenger` | Evento mínimo com referência/contexto; deduplicar pela infraestrutura existente e revalidar acesso/publicação no despacho. |
| `aculta_image` | Toda imagem passa por catálogo, UploadPolicy, ingestão, render e remoção aprovados. |
| `aculta_game` | Consumir eventos atuais elegíveis; importação histórica não concede conquistas e o cartão não revela conquistas privadas. |
| Gallery/PDF | Referenciar Media/File e viewers existentes; privacidade de anexos acompanha a entidade/contexto. |
| Wiki420 | Adapter futuro; uma discussão por Node Wiki e sem tópico público do verbete. |

## Legado histórico

Autoria legada permanece na entidade própria existente `aculta_legado_autor`,
sem User ativo, login, amizade ou mensagem. O vínculo fica em `field_autor_legado`
e a identificação pública aponta ao memorial coletivo `/u/lendashistoricas`.
Reutilizar `LegacyAuthor`, `LegacyAuthorManager` e `LegacyMemorialController` de
`aculta_user`; não copiar nem transformar autores históricos em contas.

## Código instalado versus alvo

O código atual ainda usa `drupal/forum` 1.1.3, Node bundle `forum`, vocabulary
`forums`, `comment_forum`, `taxonomy_forums`, uma View de recentes, flags e
integrações em `aculta_user`/`aculta_magazine`. Este documento descreve o alvo
aprovado pelo pedido de 2026-10-19. Nenhum módulo foi desinstalado e nenhum dado
foi removido na fase documental. Ver inventário em
[`MIGRATION-REMOVAL.md`](MIGRATION-REMOVAL.md) e decisão de supersessão em
[`DECISOES.md`](DECISOES.md).
