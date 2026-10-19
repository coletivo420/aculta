# Importação do Growroom: alterações, caminhos e estado

Revisão: 2026-10-19. Escopo: o que já foi preparado no sistema, onde estão os dados recuperados, o estado verificado
e o que falta. Nenhum conteúdo foi importado para o Drupal.

Os números de download abaixo foram medidos em 2026-10-19, após a conclusão do reprocessamento. Para conferir de novo,
usar somente leitura com `sudo`.

## Pré-requisito atendido: `aculta_image` pronto

- Ingestão obrigatória: `aculta_image.ingester->ingest($caminho, $urlOrigem, $capturadoEm)`. Resultado: `created`,
  `reused` ou `rejected` (com motivo).
- Destino das imagens aceitas: `public://importacao/<2 primeiros caracteres do SHA-256>/<SHA-256>.<ext>`. Nunca
  sobrescreve; o mesmo conteúdo reaproveita o arquivo existente.
- Origem: tabela `aculta_image_origin`, uma linha por arquivo e URL de origem. Repetir a importação não duplica.
- Política única: png, gif, jpg, jpeg e webp, até 10 MB. Conteúdo que não é PNG, JPEG, GIF ou WebP é recusado.
- Gate `scripts/validate-aculta-image.php` em PASS: nenhum código do site cria estilo ou upload de imagem fora do
  sistema, então a importação não pode criar exceções.

## Alterações feitas no sistema para a importação

| Alteração | Onde | Commit |
| --- | --- | --- |
| Submódulo `aculta_image` (catálogo, política, renderização, inspeção, remoção, ingestão, auditoria, quarentena, comparação visual) | `web/modules/custom/aculta_portal/modules/aculta_image/` | 9ebd135d, cd3c053a, a5ce559f |
| Tabelas `aculta_image_origin`, `aculta_image_quarantine`, `aculta_image_fingerprint` | atualizações 10001, 10002 e 10003 | 927b47c8, 9ebd135d |
| Diretório de destino das importações `public://importacao` (criado sob demanda) | Runtime; sem arquivos de importação até agora | — |
| Quarentena `private://aculta-image-quarentena` (área privada) | Runtime; vazia | — |
| Módulos Composer `entity_usage` 5.0.1, `media_duplicates` 2.0.4, `imageapi_optimize` 4.2.0 e `imageapi_optimize_binaries` 1.2.0-beta1 | `composer.json`, `composer.lock` | 9ebd135d, cd3c053a |
| Política de PDF e Ghostscript no ImageMagick | `/etc/ImageMagick-7/policy.xml` (backup em `policy.xml.aculta-bak`) | — (sistema) |
| Limites de upload do PHP | `/etc/php/8.4/{cli,fpm}/conf.d/90-aculta.ini` | — (sistema) |

## Scripts de download e mapeamento (versionados)

Os scripts estão em `scripts/growroom/` e são a descrição de como os manifestos foram gerados:

- `wayback_fetch.py {assets|html}`: baixa do Wayback a captura exata de cada URL do CDX. Nunca sobrescreve arquivo;
  captura diferente da mesma URL ganha sufixo `__c<timestamp>`. Um registro é válido quando o arquivo existe e o
  SHA-256 confere. Registros de falha são tentados de novo na rodada seguinte. Perfis entram na fila só se estiverem
  em `perfis-permitidos.json`.
- `build_mapping.py [--aplicar]`: mapeia tópico → fórum e autor → perfil. Sem `--aplicar`, só imprime os totais.
  Com `--aplicar`, grava `perfis-permitidos.json` e `mapeamento/`.

Uso (como root, pois o armazenamento é `root`): `sudo -n python3 -B scripts/growroom/wayback_fetch.py html`.
Os caminhos de origem e de destino estão fixos no topo de cada script.

## Caminhos dos dados recuperados (armazenamento privado do Homelab)

Estes caminhos ficam fora do Git (`estados/` e armazenamento privado). Pasta de origem: `/home/aculta/private/aculta/forum/`
(dono `root`, grupo `aculta`, modo `0750`).

| Caminho | Conteúdo | Estado (2026-10-19) |
| --- | --- | --- |
| `cdx/board-prefix-cdx-to-20250719.json` | Inventário CDX do prefixo `growroom.net/board/`, corte em 2025-07-19 | completo; 44,5 MB |
| `restauro/assets/` | Recursos não HTML baixados do Wayback, por host e caminho | concluído |
| `restauro/html/` | Páginas HTML (tópicos, galerias e perfis permitidos) baixadas do Wayback | concluído |
| `restauro/manifest-html.jsonl` | Registros por URL de página (acréscimo; o último registro de cada URL vale) | 58.411 registros; 57.954 URLs exatas |
| `restauro/manifest-assets.jsonl` | Registros por URL de recurso (acréscimo) | 9.485 registros; 9.385 URLs exatas |
| `restauro/perfis-permitidos.json` | URLs de perfil permitidas pela regra de usuários | 2.173 URLs |
| `restauro/mapeamento/` | `topico-forum.json` e `autor-topicos.json`, gerados por `build_mapping.py --aplicar` | gerado |
| `restauro/progress-*.log` | Progresso das rodadas | — |

## Verificação do download (2026-10-19)

- **Integridade:** em todos os arquivos referenciados pelo último registro de cada URL, o SHA-256 confere e o arquivo
  existe. Não há arquivo em disco fora do manifesto.
- **Divergências antigas:** 4 registros de HTML e 14 de assets apontavam para um arquivo que foi depois ocupado por outra
  captura da mesma URL (variações com e sem `:80`). Esses registros foram superados por recapturas posteriores; não
  são o estado atual. O manifesto não foi editado.
- **Lacunas de HTML:** 8 URLs sem arquivo, de 57.954 (denominador: URLs exatas). 6 com falha de rede (`None`) e 2 com 404.
- **Lacunas de assets:** 17 URLs, de 9.385 (denominador: URLs exatas). Todas respondem HTTP 200 sem corpo; o Wayback
  não entregou o conteúdo. Ficam registradas como lacuna, sem arquivo.
- **Perfis permitidos:** 2.171 de 2.173 URLs baixadas. 2 sem arquivo (ver lacunas de HTML).

## Mapeamento tópico → fórum → usuário (2026-10-19)

- **Templates:** o acervo mistura o IPB (template antigo) e o IPS 4 (template atual). O mapeamento lê os dois.
- **Fórum do tópico:** IPB: `<link rel='up' href='.../forum/<id>-...'>`. IPS 4: último `forum/<id>-` dentro das
  `nav.ipsBreadcrumb`. Antes da correção, o IPS 4 ficava sem fórum (7.550 tópicos).
- **Autores:** IPB: `<span class="author vcard">`, sem link de perfil (casamento pelo nome). IPS 4: bloco
  `cAuthorPane_author`, com link de perfil que dá o ID exato.
- **Totais:** 24.696 tópicos baixados; 24.675 com fórum; 21 sem fórum (páginas sem navegação, de índice). Em fóruns
  importados: 18.377; excluídos: 6.298.
- **Conferência:** para uma amostra de 3.000 tópicos, o nome do fórum no título da página é consistente com o ID
  atribuído (ex.: 1 = Informações Básicas, 2 = Avisos, 24 = Notícias, 76 = Cannabis Medicinal).
- **Autores:** 6.096 nos tópicos importados; 737 com ID exato (IPS 4); 1.850 casados com perfil; 6 ambíguos; 4.240 sem
  perfil no índice CDX.

## Regra de usuários (decisão do responsável, 2026-10-16)

- Serão importados **somente** os usuários que comentaram nos fóruns e tópicos que estão na importação.
- Não serão importados usuários que aparecem apenas em fóruns excluídos (Avisos, Multimídia Cannábica, Cannabis Medicinal e Notícias) ou em tópicos desses fóruns.
- Usuário que comentou em um tópico importado e em um excluído entra, pelo primeiro caso.
- Perfis fora desse conjunto não entram na importação e não foram baixados.
- **Pendente de decisão:** 6 nomes ambíguos (IDs de perfil diferentes para o mesmo nome, por exemplo `Pot Head`, `Pcgrow`,
  `Prof. Pardal`, `Delta-9-thc`). Nenhum deles entrou em `perfis-permitidos.json`. O responsável decide se entram
  e como (qual ID).

## Fóruns fora da importação (decisão do responsável, 2026-10-16)

| Fórum (nome no Growroom) | Identificador (`forum/<id>`) | Observação |
| --- | --- | --- |
| Avisos | 2 e 52 | dois fóruns com o mesmo nome |
| Multimidia Cannabica | 48 | |
| Cannabis Medicinal (interpretado como "Cultivo Medicinal") | 76 | o nome "Cultivo Medicinal" não existe no Growroom; confirmar com o responsável |
| Notícias | 24 | incluído em 2026-10-16 (pedido do responsável) |

Escopo: a decisão vale para a **importação**. O download não exclui esses fóruns; a exclusão é aplicada no mapeamento.

## Bloqueio atual

A F3 (migração piloto) depende da lista de autores aprovada. A F6 (reconstrução em lotes) depende da F3.
Estado das condições:

1. Downloads de `restauro/assets` e `restauro/html`: **concluídos**, com lacunas documentadas acima.
2. Scripts de download e mapeamento: **versionados** em `scripts/growroom/`.
3. Lista de autores: **extraída**; **aprovação pendente** (ambíguos e confirmação da regra pelo responsável).

Quando a aprovação for dada, a importação segue a sequência do roteiro do Fórum (estrutura, usuários
legados, tópicos, comentários, arquivos e reconciliação), usando somente os caminhos acima.
