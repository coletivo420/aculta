# Importação do Growroom: alterações, caminhos e estado

> **Atualização de passagem (2026-10-17):** os números de progresso abaixo são da última medição registrada, às 14:05.
> Não foram remedidos na passagem: a pasta é `root` com acesso por `sudo`. Na passagem não havia processo de download ativo
> (`ps`). Antes de qualquer decisão, confirmar o estado com `sudo` e somente leitura. Ver também [HANDOFF.md](HANDOFF.md), seção 5.

Revisão: 2026-10-17. Escopo: o que já foi preparado no sistema, onde estão os dados recuperados, o estado observado
e o que falta. Nenhum conteúdo foi importado para o Drupal.

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

## Caminhos dos dados recuperados (armazenamento privado do Homelab)

Estes caminhos ficam fora do Git (`estados/` e armazenamento privado). Pasta de origem: `/home/aculta/private/aculta/forum/`
(dono `root`, grupo `aculta`, modo `0750`).

| Caminho | Conteúdo | Estado observado (2026-10-17, 14:05) |
| --- | --- | --- |
| `cdx/board-prefix-cdx-to-20250719.json` | Inventário CDX do prefixo `growroom.net/board/`, corte em 2025-07-19 (271.283 capturas, 177.673 URLs) | completo; 44,5 MB |
| `restauro/assets/` | Recursos não HTML (imagens, arquivos) baixados do Wayback, por host e caminho | em andamento |
| `restauro/html/` | Páginas HTML (tópicos, perfis, galerias) baixadas do Wayback | em andamento, parcial |
| `restauro/manifest-assets.jsonl` | Um registro por URL de recurso: captura, HTTP, tipo, SHA-256 e caminho local | 9.238 registros |
| `restauro/manifest-html.jsonl` | Um registro por URL de página HTML | 5.800 registros |
| `restauro/progress-assets.log` | Progresso dos recursos | 4.851 de 5.023 na última linha |
| `restauro/progress-html.log` | Progresso das páginas | 5.800 de 72.124 na última linha |
| `f1-20261015/` | Evidências da F1 (domínio e inventário) | — |

Estado do armazenamento: `restauro/assets` e `restauro/html` estão com donos mistos (`piradopirata` e `root`), e os
manifestos são de `root`. Há um job de download em execução (`wayback_fetch.py assets`, depois `html`), iniciado como
root numa sessão anterior. Ele não foi interrompido nesta revisão.

Os scripts de download (`wayback_fetch.py`, `wayback_verify.py`) estão no diretório temporário da sessão de
desenvolvimento, não no repositório. Antes da importação, eles precisam ser versionados ou documentados, porque
são a única descrição de como os manifestos foram gerados.

## Regras de importação (contrato)

- Imagens: somente por `aculta_image.ingester`. Origem recuperada (URL e data de captura) é o `$urlOrigem` e o
  `$capturadoEm`.
- Páginas e registros de conteúdo: pelo Migrate API, com mapas origem → destino, repetível e sem duplicar.
- Usuários históricos: sem mesclar contas existentes por apelido, sem sobrescrever conteúdo atual, sem conceder
  permissões do Game, sem notificações, sem perfil comum com avatar aleatório, sem reabertura de login
  (`docs/RECONSTRUCAO.md` do `aculta_forum`).
- Lacunas: itens não recuperados ficam com motivo registrado; nenhuma porcentagem é divulgada sem denominador.

## Bloqueio atual

A F3 (migração piloto) depende da lista de autores extraída do HTML. A F6 (reconstrução em lotes) depende da F3.
Portanto a importação não começa até que:

1. os downloads de `restauro/assets` e `restauro/html` estejam concluídos ou com lacunas documentadas;
2. os scripts de download estejam versionados ou documentados;
3. a lista de autores esteja extraída e aprovada.

Quando essas condições forem atendidas, a importação segue a sequência do roteiro do Fórum (estrutura, usuários
legados, tópicos, comentários, arquivos e reconciliação), usando somente os caminhos acima.
