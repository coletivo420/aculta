# Reconstrução do Growroom pelo Wayback

Fonte indicada pelo proprietário:
[snapshot de 2025-07-19](https://web.archive.org/web/20250719093640/https://growroom.net/board/).
Não há backup de banco/anexos disponível. A meta é recuperar tudo que estiver
publicamente arquivado, sem tratar o arquivo da web como dump integral do banco.

## Evidência desta pesquisa

Downloads HTTP 200 e inspeção estática, sem executar scripts das páginas:

| Amostra | Resultado observado | SHA-256 do download |
| --- | --- | --- |
| Página inicial indicada, HTML original | 105.764 bytes; 27 IDs de fóruns/links, 17 tópicos e 24 perfis distintos referenciados | `81740206888732139fea59d9c5e2725011e2142fb520b22b7b43e785427e8b52` |
| Tópico de amostra `9860` | Primeira página, 422.704 bytes; anuncia seis páginas; IDs de respostas, autores e datas ISO | `5fee05d7af621a3051e304341a31c6b5af0df5d96c6892ccc0ca58b10a10ee37` |
| Perfil histórico `1` | 146.413 bytes; nome, registro e referências a conteúdo/álbuns | `a3cfd632eca11d070e05381b9ef466613c0b0333441b5bf0a6d351e33cdc8953` |
| Consulta CDX limitada a 20 linhas | Capturas de várias épocas; exige seleção de rotas e temporalidade | `70600b0ee88b858cd302ed1b177eadb27255e7162daad0e1907546596e7adc09` |

Os números acima descrevem somente as amostras. Contagens antigas exibidas pelo
site não provam quantos itens o Wayback preservou. Replay pode escolher a captura
mais próxima: o manifesto definitivo deve registrar timestamp e URL efetivos,
sem atribuir automaticamente a todas as amostras a data da página inicial.

## Inventário CDX da F1 — corte em 2025-07-19

Foi consultado o prefixo `growroom.net/board/` com `matchType=prefix`, incluindo
capturas HTTP/HTTPS e hosts raiz/`www`, todos os status e tipos MIME, até
`2025-07-19 09:36:40 UTC` (timestamp da captura de referência). A consulta foi
retomada pelo token CDX: 150.000 registros na primeira página e 121.283 na
segunda, sem token restante.

| Medida | Resultado |
| --- | ---: |
| Capturas CDX | 271.283 |
| URLs originais distintas | 177.673 |
| URLs com ao menos uma captura HTTP 200 | 141.851 |
| URLs sem captura HTTP 200 | 35.822 |
| Capturas `text/html` | 256.864 |
| URLs com query string | 103.210 |
| Capturas HTTP 200 | 209.605 |
| Capturas nos hosts `www.growroom.net` / `growroom.net` | 202.659 / 68.624 |
| Intervalo observado | `2002-06-30 23:47:03` a `2025-07-19 09:36:40 UTC` |

Classificação indicativa e mutuamente exclusiva das 177.673 URLs distintas,
pela primeira regra de caminho correspondente:

| Grupo de caminhos | URLs |
| --- | ---: |
| Tópicos e respostas (`topic`, `showtopic.php`, `showthread.php`) | 68.245 |
| Perfis (`profile`, `user`, `member.php`) | 23.869 |
| Galerias e mídia (`gallery`, `album`, `uploads`, visualizadores) | 27.423 |
| Fóruns e índices (`forum`, `forums`, `showforum.php`, `index.php`) | 20.374 |
| Ações, autenticação, busca e moderação | 23.470 |
| Outros caminhos e assets | 14.292 |

O JSON completo, com 44.493.503 bytes, está apenas no armazenamento privado do
Homelab: `/home/aculta/private/aculta/forum/cdx/board-prefix-cdx-to-20250719.json`
(modo `0640`, `aculta:aculta`), SHA-256
`346355b77bd9fee58ba9e33c75acf1c137da69e952522d07c36a960718cbed26`. O índice
CDX não foi adicionado ao Git. Este inventário cobre o prefixo documentado
`/board/`; não afirma cobertura de outros domínios nem de caminhos fora dele.

O corte usa a captura de referência aprovada no documento de origem. Capturas
posteriores não entram no piloto até revisão própria; capturas anteriores podem
ser usadas para preencher lacunas, com seu timestamp efetivo preservado.

## Inventário e aquisição

1. Enumerar o índice CDX de capturas e variantes históricas HTTP/HTTPS, `www`,
   caminhos antigos, query strings de paginação e identificadores Invision.
2. Classificar fóruns, tópicos, páginas de respostas, perfis, álbuns e arquivos.
   Endpoints de login, administração e ações não são conteúdo a reconstruir.
3. Decidir o corte temporal e selecionar capturas por item, usando anteriores
   para preencher lacunas; não supor um único snapshot completo e sincronizado.
4. Baixar com limites de concorrência, retomada, retry com espera e respeito a
   recusas do serviço. Não iniciar milhares de requisições em cada execução.
5. Conservar HTML/arquivos originais como fonte imutável e dataset derivado separado.
   Manifesto: origem, captura, HTTP, tipo, checksum, tamanho e resultado do parser.
6. Tratar assets de domínios distintos somente quando vinculados ao conteúdo
   arquivado e com origem identificada. Arquivo ausente não será reconstruído por IA.

Raw HTML, dados extraídos e mídias de usuários não entram automaticamente no Git.
Definir armazenamento privado, volume, backup e recuperação antes do lote integral;
não usar `estados/` como pasta de trabalho nem modificar backups imutáveis.

## Extração e normalização

- Identidade por ID original comprovado; nome exibido preserva grafia histórica.
- Extrair o primeiro post como abertura do Node e os demais como Comments,
  evitando duplicar a abertura como resposta. Validar esse mapeamento na amostra.
- Deduplicar por ID de tópico/resposta/autor; variação de URL, quote ou captura
  não cria um segundo item. Identificador citado não comprova um post recuperado.
- Preservar texto, datas disponíveis, ordem e contexto das citações. Remover
  scripts/tracking e converter HTML para formatos de texto filtrados do Drupal.
- Diferenciar horário autoral, edição, captura e importação. Não converter
  rótulos relativos como "February 18" sem fonte temporal suficiente.
- Arquivos usam File/Media API, nome sanitizado e uso registrado. Álbuns usam
  `aculta_gallery`; conteúdo audiovisual usa os tipos adequados existentes.
- Origem e lacunas ficam visíveis. Um autor sem identidade recuperável recebe
  atribuição explícita de origem desconhecida, sem biografia ou conta inventada.

## Importação via Drupal

Usar Migrate API e mapas source ID → destination ID. Sequência prevista:

1. Estrutura Taxonomy e papel legado sem permissões adicionais.
2. User bloqueado e metadados históricos com destino público coletivo.
3. Tópicos Forum e abertura.
4. Comments com autor, relação e data.
5. Arquivos, Media, álbuns e referências internas recuperáveis.
6. Reconciliação de links, fechamento das discussões históricas e cobertura.

Migrate Tools é candidato para execução/status dos lotes; Migrate Plus pode
fornecer leitores de arquivos normalizados. A decisão de dependência precisa
demonstrar a necessidade e passar pelo Composer na fase correspondente.

Importação é contexto próprio: impedir concessões do Game, notificações,
amizade, criação indevida de perfil comum/avatar aleatório e reabertura de login.
Não importar funções históricas de moderação como permissões atuais.

O importador deve ser repetível e retomar sem duplicar registros. Não mesclar
Users existentes por apelido nem sobrescrever conteúdo atual. Recuperação/rollback
de lote exige inventário dos itens criados e efeitos sobre vínculos atuais.

## Aceite da reconstrução

Relatório por tipo e lote: capturas encontradas, itens baixados, extraídos,
importados, repetidos, rejeitados e indisponíveis. Documentar divergências com
os contadores históricos. Conferir autoria, datas, citações, paginação, anexos,
links, ausência de execução de scripts e exclusão das ações de Runtime indevidas.

Critério de completude: todos os itens do inventário aprovado têm destino ou
motivo de impossibilidade documentado. Nenhuma porcentagem será divulgada sem
denominador verificável.

Fontes: [CDX do Internet Archive](https://github.com/internetarchive/wayback/tree/master/wayback-cdx-server),
[Migrate API](https://www.drupal.org/docs/drupal-apis/migrate-api/migrate-api-overview),
[Migrate Tools](https://www.drupal.org/project/migrate_tools) e
[Migrate Plus](https://www.drupal.org/project/migrate_plus).
