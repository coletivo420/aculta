# Fixtures do ACULTA Forum no Runtime de teste

Este inventário registra a hierarquia inicial de categorias e os tópicos
temporários criados no Runtime Homelab para homologação visual/funcional. Não é
configuração exportável, conteúdo de produção nem parte do acervo histórico
Growroom. Estado após a limpeza de 2026-10-19: nenhum tópico ou comentário de
teste; a árvore Taxonomy foi preservada como estrutura inicial do fórum.

| Tipo | ID | Título/nome | Pai/categoria | URL/estado |
| --- | ---: | --- | --- | --- |
| Termo | 76 | Cultivo | raiz | Categoria preservada no vocabulário `aculta_forum_categories`. |
| Termo | 77 | Ambiente interno | 76 — Cultivo | Subcategoria preservada. |
| Termo | 78 | Ambiente externo | 76 — Cultivo | Subcategoria preservada. |
| Termo | 79 | Genéticas e sementes | raiz | Categoria preservada. |
| Termo | 80 | Variedades e linhagens | 79 — Genéticas e sementes | Subcategoria preservada. |
| Termo | 81 | Sementes e conservação | 79 — Genéticas e sementes | Subcategoria preservada. |
| Termo | 82 | Cultura e comunidade | raiz | Categoria preservada. |
| Termo | 83 | Cultura e história | 82 — Cultura e comunidade | Subcategoria preservada. |
| Termo | 84 | Convivência e apresentações | 82 — Cultura e comunidade | Subcategoria preservada. |
| Termo | 85 | Direitos e políticas de drogas | raiz | Categoria preservada. |
| Termo | 86 | Política de drogas | 85 — Direitos e políticas de drogas | Subcategoria preservada. |
| Termo | 87 | Direitos humanos | 85 — Direitos e políticas de drogas | Subcategoria preservada. |
| Node | 383 | [TESTE] ambiente interno | 77 | Removido em 2026-10-19. |
| Node | 384 | [TESTE] ambiente externo | 78 | Removido em 2026-10-19. |
| Node | 385 | [TESTE] variedades e linhagens | 80 | Removido em 2026-10-19. |
| Node | 386 | [TESTE] sementes e conservação | 81 | Removido em 2026-10-19. |
| Node | 387 | [TESTE] cultura e história | 83 | Removido em 2026-10-19. |
| Node | 388 | [TESTE] convivência e apresentações | 84 | Removido em 2026-10-19. |
| Node | 389 | [TESTE] política de drogas | 86 | Removido em 2026-10-19. |
| Node | 390 | [TESTE] direitos humanos | 87 | Removido em 2026-10-19. |
| Node | 524 | [TESTE] ACULTA Forum Office 6d9f7c9a | 83 | Fixture pública temporária de renderização, removida em 2026-10-21. |
| ForumPost | 1 | [TESTE] ACULTA Forum Office 6d9f7c9a — preview DOCX | Node 524 | Removido em 2026-10-21. |
| Media | 243 | [TESTE] ACULTA Forum Office 6d9f7c9a | File 586 | Removida em 2026-10-21. |
| File | 586 | TESTE--ACULTA-Forum-Office-6d9f7c9a.docx | `private://forum-preview-6d9f7c9a.docx` | Removido pelo File API em 2026-10-21; diretório temporário removido. |

Runtime confirmado em `toca`, Drupal 11.4.10, banco `aculta_runtime`, URI
`https://growroom420.aculta.toca.net.br`. O vocabulário alvo tinha 0 termos e o
bundle alvo tinha 0 Nodes antes da operação. O Drupal Forum legado manteve seus
30 Nodes e 8 termos no vocabulário `forums`. Há 0 comentários de teste.

Os Nodes receberam a marca `[TESTE]` no título e foram criados por uid 5 no
mesmo instante. Nenhum Comment de teste foi incluído. Antes da remoção, a
identidade dos oito IDs foi revalidada; não tinham Comments, Flags ou vínculos
editoriais nem eram referenciados por conteúdo externo.

## Recuperação/limpeza

Antes de limpar fixtures, confira hostname, checkout e `drush status` conforme
`docs/architecture/environments.md`. Limpeza manual deliberada concluída: os
Nodes 383–390 foram removidos pela Entity API Core
([`EntityStorageInterface::delete()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityStorageInterface.php/function/EntityStorageInterface%3A%3Adelete/11.x));
os 12 termos foram preservados como estrutura inicial; o bundle novo ficou com
zero Nodes e zero Comments.
Os oito aliases Pathauto foram removidos; cada URL anterior responde HTTP 404,
sem redirect.
Não remova Nodes do bundle legado `forum`, termos do vocabulário `forums`,
vínculos da Revista nem conteúdo de Revista, Wiki ou LMS. Não há rotina
automática de expurgo.

## Fixture de preview Office — 2026-10-21

A tentativa de pré-visualização usou somente a hierarquia acima (Node 524,
ForumPost 1, Media 243, File 586) e a categoria preservada 83. A limpeza
revalidou UUID, título, bundle e referências antes de excluir pela Entity API;
File foi removido após Media e a referência de uso ficar vazia. Contagem final:
0 tópicos/posts/Comments dedicados/anexos; galerias Media 11 e 18 mantiveram
63+34 imagens e o `field_gallery_purpose` opcional permaneceu vazio. A URL da
fixture retornou HTTP 404. A primeira resposta privada falhou porque o arquivo
da fixture tinha proprietário diferente do usuário PHP-FPM. Revalidação com
arquivo de propriedade `aculta` confirmou HTTP 200 em query e path de
`system.files`, mas Firefox mostrou o fallback e não confirmou a renderização.
A fixture de revalidação (Node 525, ForumPost 2, Media 244, File 587) foi
removida por Entity API; o arquivo privado e as capturas temporárias também
foram removidos. O Runtime voltou a zero tópicos, posts e anexos; as 97 imagens
das galerias permaneceram.

## Revalidação de DOCX completo — 2026-10-22

Um novo fixture OOXML completo foi criado sob o usuário `aculta` do PHP-FPM:
Node 526, ForumPost 3, Media 245 e File 588. As duas formas da rota Core
retornaram HTTP 200. Firefox ESR confirmou status `done` e o conteúdo visível
dentro do iframe sandboxed. A captura revelou o bug que removia o iframe após
sucesso; a correção ficou em `aculta_document_viewer` dev.12. Após limpar as
entidades pela Entity API e o File sem usos, o Runtime voltou a zero tópicos,
posts, Comments dedicados e anexos; as galerias 11/18 mantiveram 63+34 imagens.
A URL do tópico e o endpoint do arquivo removidos retornaram 404. Capturas e
scripts temporários foram removidos do armazenamento privado e de `/tmp`.
