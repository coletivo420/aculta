# Arquitetura alvo — ACULTA Forum

**Situação:** arquitetura normativa revisada em 2026-10-24; deve ser lida
junto com `README.md`, `ROADMAP.md`, `OWNERSHIP.md` e as evidências do
`CHANGELOG.md`. O módulo já possui motor executável `0.1.0-dev.273`, com
ForumPosts revisionáveis, Comments legados/contextuais, pesquisa, anexos e
fóruns restritos implementados tecnicamente, mas desativados por padrão.
Não tratar este documento como prova de homologação do Runtime.

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
- `aculta_messenger` é concentrador de avisos, canais e mensageria privada entre membros; o Fórum publica eventos/contextos mínimos para notificações e não cria filas, inbox, armazenamento ou transporte de e-mail próprios. Assinar, seguir ou comentar conteúdo não cria conversa privada.
- `aculta_image` é a passagem obrigatória para qualquer ingestão, processamento, renderização ou remoção de imagens.
- `aculta_lms`, Group, Magazine, Wiki/Node e Game permanecem autoridades de seus próprios dados.
- ACULTA420 detém markup, CSS, SDC e tokens; recebe apenas render arrays e dados escalares preparados, acessíveis e cacheáveis. Não faz consultas, decisões de acesso ou lógica de domínio.

## Motor e armazenamento

O motor combina Node para tópicos independentes, `aculta_forum_post` revisionável
para novas respostas públicas do Growroom, Comment Core para respostas legadas
em transição e discussões nos contextos Revista/LMS/Wiki, Taxonomy para
categorias, Views para listagens, Search API para pesquisa, History para leitura,
Flag para seguir/salvar/denunciar e Entity/Access/Render/Cache APIs para
persistência e exposição. Form API e workflow preservam criação, edição e
moderação adequadas a cada contexto.

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
| `aculta_messenger` | Eventos mínimos de respostas e revisões aprovadas, assinatura `seguir_topico`, agregação `aculta_aviso` por destinatário/tipo/tópico, avisos na central e resumo diário opt-in; revalidar acesso/publicação na produção, exibição e entrega. Conversas privadas são um serviço independente acessível por ações de perfil, nunca por seguimento de conteúdo. |
| `aculta_image` | Toda imagem passa por catálogo, UploadPolicy, ingestão, render e remoção aprovados. |
| `aculta_game` | Avaliar os hooks de Comment publicados no tópico público atual para o trigger existente `forum_comment`; preservar a concessão única do User. Importação/autoria histórica não concede conquistas. O Game não cria dependência inversa no Fórum. |
| Gallery/PDF | Referenciar Media/File e viewers existentes; privacidade de anexos acompanha a entidade/contexto. |
| Wiki420 | Adapter futuro; uma discussão por Node Wiki e sem tópico público do verbete. |

## Contrato de comunicação e notificações — F-D41 (2026-10-24)

Existem **três experiências distintas**:

1. **Discussão contextual:** tópico Growroom com ForumPosts/Comments ou Comment
   associado diretamente a publicação, curso/turma ou verbete. É conteúdo
   público/privado conforme entidade proprietária, com moderação e acesso
   específicos. Não equivale a chat.
2. **Acompanhamento e avisos:** a Flag `seguir_topico` e eventos reais de
   publicação são consumidos pelo `aculta_messenger`. Avisos do Growroom
   permanecem em `aculta_aviso` com watermarks independentes para Comment e
   revisão publicada do ForumPost, e resumo diário por e-mail opt-in. Nunca
   publicar aviso de rascunho/pendência; revalidar acesso e Domain.
3. **Conversa entre membros:** Private Message integrado ao Messenger,
   iniciada voluntariamente entre contas elegíveis com respeito a
   amizade, preferências e bloqueios. Nenhuma thread ou grupo é criado
   automaticamente por seguir/comentar tópico, fazer matrícula ou editar Wiki.

**A API compartilhada de eventos da ADR-XK-02 está planejada, não
implementada.** Seu futuro adapter do Fórum deverá reutilizar as fontes
existentes, manter a regra F-D21 e testar equivalência/idempotência antes de
substituir hooks diretos. Nenhum evento duplicado pode chegar à central ou ao
digest na transição. O Fórum não deve importar classes internas de Messenger:
preferir interfaces estáveis/adapters opcionais e evitar ciclo de dependências.

A decisão F-D41 e o contrato detalhado estão em [`DECISOES.md`](DECISOES.md),
[`ROADMAP.md`](ROADMAP.md) e
[`docs/portal/AVISOS-E-NOTIFICACOES.md`](../../../../../../../docs/portal/AVISOS-E-NOTIFICACOES.md).

## Legado histórico

Autoria legada permanece na entidade própria existente `aculta_legado_autor`,
sem User ativo, login, amizade ou mensagem. O vínculo fica em `field_autor_legado`
e a identificação pública aponta ao memorial coletivo `/u/lendashistoricas`.
Reutilizar `LegacyAuthor`, `LegacyAuthorManager` e `LegacyMemorialController` de
`aculta_user`; não copiar nem transformar autores históricos em contas.

Tópicos e comentários importados do acervo Growroom são somente leitura para
todos os papéis. A discussão chega fechada e não pode ser reaberta; update,
delete, approve e ações de moderação em lote são negadas. O marcador usado é
`field_autor_legado`, anexado ao novo Node e ao tipo Comment compartilhado.
`drupal/forum` foi retirado do Runtime de teste e do Composer em dev.50. A
restauração futura do acervo usa os bundles ACULTA sem reintroduzir o contrib.

## Código instalado versus alvo

O Runtime de teste não contém o Drupal Forum contrib, seus bundles nem o campo
`field_discussao`; Config Sync remove as respectivas definições. Os efeitos estão
registrados no inventário em
[`MIGRATION-REMOVAL.md`](MIGRATION-REMOVAL.md) e decisão de supersessão em
[`DECISOES.md`](DECISOES.md).
