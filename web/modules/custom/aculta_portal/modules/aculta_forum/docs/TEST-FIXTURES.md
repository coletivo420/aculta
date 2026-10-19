# Fixtures do ACULTA Forum no Runtime de teste

Este inventário registra exclusivamente conteúdo criado no Runtime Homelab
para homologação visual/funcional. Não é configuração exportável, conteúdo de
produção nem parte do acervo histórico Growroom.

| Tipo | ID | Título/nome | Pai/categoria | URL/estado |
| --- | ---: | --- | --- | --- |
| Termo | 76 | Cultivo | raiz | Categoria do vocabulário `aculta_forum_categories`. |
| Termo | 77 | Ambiente interno | 76 — Cultivo | Subcategoria. |
| Termo | 78 | Ambiente externo | 76 — Cultivo | Subcategoria. |
| Termo | 79 | Genéticas e sementes | raiz | Categoria. |
| Termo | 80 | Variedades e linhagens | 79 — Genéticas e sementes | Subcategoria. |
| Termo | 81 | Sementes e conservação | 79 — Genéticas e sementes | Subcategoria. |
| Termo | 82 | Cultura e comunidade | raiz | Categoria. |
| Termo | 83 | Cultura e história | 82 — Cultura e comunidade | Subcategoria. |
| Termo | 84 | Convivência e apresentações | 82 — Cultura e comunidade | Subcategoria. |
| Termo | 85 | Direitos e políticas de drogas | raiz | Categoria. |
| Termo | 86 | Política de drogas | 85 — Direitos e políticas de drogas | Subcategoria. |
| Termo | 87 | Direitos humanos | 85 — Direitos e políticas de drogas | Subcategoria. |
| Node | 383 | [TESTE] ambiente interno | 77 | `/topico/teste-ambiente-interno` — publicado, uid 5. |
| Node | 384 | [TESTE] ambiente externo | 78 | `/topico/teste-ambiente-externo` — publicado, uid 5. |
| Node | 385 | [TESTE] variedades e linhagens | 80 | `/topico/teste-variedades-e-linhagens` — publicado, uid 5. |
| Node | 386 | [TESTE] sementes e conservação | 81 | `/topico/teste-sementes-e-conservacao` — publicado, uid 5. |
| Node | 387 | [TESTE] cultura e história | 83 | `/topico/teste-cultura-e-historia` — publicado, uid 5. |
| Node | 388 | [TESTE] convivência e apresentações | 84 | `/topico/teste-convivencia-e-apresentacoes` — publicado, uid 5. |
| Node | 389 | [TESTE] política de drogas | 86 | `/topico/teste-politica-de-drogas` — publicado, uid 5. |
| Node | 390 | [TESTE] direitos humanos | 87 | `/topico/teste-direitos-humanos` — publicado, uid 5. |

Runtime confirmado em `toca`, Drupal 11.4.10, banco `aculta_runtime`, URI
`https://growroom420.aculta.toca.net.br`. O vocabulário alvo tinha 0 termos e o
bundle alvo tinha 0 Nodes antes da operação. O Drupal Forum legado manteve seus
30 Nodes e 8 termos no vocabulário `forums`. Há 0 comentários de teste.

As entidades recebem a marca `[TESTE]` no nome/título e texto explicativo no
corpo. Nenhum Comment de teste deve ser incluído sem solicitação específica.

## Recuperação/limpeza

Antes de remover fixtures, confira o hostname, o checkout e `drush status` pelo
procedimento de `docs/architecture/environments.md`; confirme `aculta_runtime`
e URI `*.aculta.toca.net.br`. Remova apenas os IDs listados nesta tabela, em
ordem: Nodes de tópico, depois termos filhos e por último os termos-raiz. Não
remova Nodes do bundle legado `forum`, termos do vocabulário `forums`, nem
qualquer comentário ou conteúdo de Revista, Wiki ou LMS. A limpeza é manual e
deliberada; não há rotina automática de expurgo.
