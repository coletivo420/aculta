# Auditoria do acervo Growroom (2026-10-19)

Relatório da análise dos dados baixados do Wayback. Escopo: acervo em `/home/aculta/private/aculta/forum/restauro/`
(fora do Git). Nenhum conteúdo foi importado ao Drupal, e nenhum dado pessoal foi copiado para este documento.
Os valores são contagens. Estado do processo e decisões: [IMPORTACAO-GROWROOM.md](IMPORTACAO-GROWROOM.md).

A importação segue **adiada** até a remodelagem do fórum na PR #297 (decisão do responsável, 2026-10-19).

## Resumo

| Item | Situação |
| --- | --- |
| Integridade dos arquivos referenciados | Íntegros: 67.314 de 67.314 (último registro por URL), SHA-256 confere |
| Cobertura da fila de HTML | 56.928 de 56.935 itens com arquivo (99,99%) |
| Cobertura dos assets | 9.368 de 9.385 URLs com arquivo (99,8%) |
| Capturas de erro gravadas como HTTP 200 | 1.254 páginas (2,2% do HTML), das quais 1.217 são galerias |
| Mapeamento de tópicos | Cobre apenas o template `/topic/`; **14.560 tópicos `showtopic` não são mapeados** |
| Imagens referenciadas por tópicos | 26.848 URLs distintas; 21.988 fora do inventário CDX e 3.142 não baixadas |
| Dados pessoais em perfis | 191 perfis com e-mail visível (371 ocorrências) |
| Codificação | Sem declaração de charset em 16.098 páginas; 2.322 dessas não são UTF-8 válido |

**Conclusão:** o download é confiável e completo para a fila definida. O que bloqueia a importação não é a coleta, e
sim (1) o mapeamento incompleto dos tópicos no formato antigo, (2) a presença de páginas de erro entre as capturas
e (3) as imagens fora do inventário. Cada item está detalhado abaixo.

## 1. Inventário CDX

- Linhas: 271.283. Status: 200 em 209.605; 302 em 21.566; 404 em 14.080; 301 em 12.824; 403 em 10.891; outros em menor número.
- URLs HTML com status 200: 132.467, classificadas assim: tópicos 52.565; perfis 24.080; galerias 22.995; fóruns 680;
  demais 32.147.
- Capturas por ano: concentração em 2010–2011 (67.029 linhas) e em 2019–2022 (81.286 linhas).
- O inventário foi filtrado ao prefixo `growroom.net/board/`. Imagens fora desse prefixo (ex.: `uploads`) não entram
  no inventário. Isso explica boa parte das referências sem registro (seção 5).

## 2. Fila e manifestos

- Fila de HTML selecionada: 56.935 itens (tópicos 39.380; galerias 15.382; perfis permitidos 2.173).
- Manifesto HTML: 58.411 registros (acréscimo); 57.954 URLs exatas; 457 registros extras por recaptura.
- Último registro por URL: 57.946 com arquivo; 6 com falha de rede (`None`); 2 com 404.
- Manifesto de assets: 9.485 registros; 9.385 URLs exatas; 100 registros extras por recaptura. Último registro: 9.368
  com arquivo; 17 com HTTP 200 e corpo vazio (o Wayback não entregou o conteúdo).
- Duplicidade por recaptura: 2.933 arquivos carregam sufixo `__c<timestamp>`. São capturas diferentes da mesma URL,
  preservadas por design. Os registros mais antigos que apontavam para o nome sem sufixo estão superados pelo último
  registro (ver IMPORTACAO-GROWROOM.md).
- Armazenamento: HTML 57.945 arquivos (7,0 GB), incluindo `__conflitos` (5.367 arquivos; 0,75 GB); assets 9.314 arquivos
  (0,71 GB). Total aproximado: 7,7 GB.

## 3. Conteúdo: capturas de erro gravadas como HTTP 200

Foram contadas por marcações no texto da página, sem tags. As páginas são gravadas no manifesto como sucesso, por isso
**não devem entrar na importação** sem filtro.

| Marcação | Páginas |
| --- | --- |
| Aviso PHP do servidor de origem (`Warning: main(...)`) | 1.118 |
| Galeria sem arquivo ("O caminho dessa…") | 99 |
| Falha de conexão com o MySQL | 21 |
| Aviso de manutenção ("Desculpem-nos…") | 14 |
| "IPS Driver Error" | 2 |
| **Páginas com alguma marca (sem contagem dupla)** | **1.254** |

Por tipo de URL, as 1.254 páginas são 1.217 galerias e 37 de outros caminhos. Nenhuma é tópico `/topic/`.

Os tópicos `/topic/` não apresentaram essas marcas. Nos `showtopic`, há ainda 198 páginas "Thread inexistente", 140
"Acesso negado" e 154 páginas de mensagem/login do fórum, todas gravadas como 200 (seção 4).

Efeito na fila: 1.217 de 15.382 galerias (7,9%) são erro. Isso reduz a utilidade da coleta de galerias.

## 4. Estrutura: fóruns, tópicos e autores

- Tópicos no formato `/topic/`: 24.817 URLs baixadas. Dessas, 24.675 têm fórum identificado e 21 não (páginas de índice).
  Templates: IPS 4 em 7.652; IPB em 15.298 (o IPB é o template com `class="author vcard"`); 1.867 sem nenhum dos dois marcadores.
- Excluídos da importação por fórum: 6.339 tópicos (Avisos 2 e 52; Multimídia 48; Cannabis Medicinal 76; Notícias 24).
- Nomes de autores distintos: 6.644 em todos os tópicos `/topic/` (incluindo fóruns excluídos); 6.096 nos fóruns importados.
- Perfis permitidos: 2.173 URLs; 2.171 com arquivo. Os 2 sem arquivo estão na lacuna de HTML (seção 2).

### 4.1 Lacuna crítica: tópicos no formato `showtopic`

O mapeamento (`topicos_baixados()` em `build_mapping.py`) lê apenas URLs com `/topic/`. Os tópicos antigos
(`index.php?showtopic=N`) foram baixados, mas não foram analisados.

| Grupo (14.560 URLs `showtopic` com arquivo) | Quantidade |
| --- | --- |
| Com marcação de fórum e autor já lida pelo parser | 2.141 |
| Template IPB 2 sem marcação lida pelo parser (breadcrumb em texto, links `act=SF&f=` não presentes) | 11.927 |
| "Thread inexistente" (captura de erro) | 198 |
| "Acesso negado" (captura de erro) | 140 |
| Mensagem do fórum / login (captura de erro) | 154 |

- IDs de tópico: 955 distintos nessa forma; 308 também existem no formato `/topic/`; **647 existem só em `showtopic`**.
- Consequência: fórum e autores desses tópicos não entram no mapeamento. Não é possível dizer, hoje, quantos deles
  pertencem aos fóruns excluídos. Por isso a regra de usuários (decisão de 2026-10-16) não está aplicada a esse grupo.

### 4.2 Autores e perfis

- Autores nos tópicos mapeados: 6.096 (737 com ID exato pelo template IPS 4; 1.850 casados por nome; 6 ambíguos;
  4.240 sem perfil no CDX).
- Os 6 ambíguos continuam pendentes de decisão do responsável (ver IMPORTACAO-GROWROOM.md).

## 5. Imagens e assets

- Referências a imagens em tópicos: 26.848 URLs distintas.
  - Hosts: `www.growroom.net` 18.578; `growroom.net` 7.036; `secure.gravatar.com` 422; outros hosts externos 26
    (ex.: `misturaurbana.com`, `www.treta.com.br`, `sandiegodiscountcaregivers.com`; a contagem completa não foi feita).
  - Status: 1.718 baixadas; 3.142 estão no CDX mas **não foram baixadas** (lacuna a investigar); 21.988 estão fora do
    inventário CDX (prefixo `/board/`).
- Avatares externos (gravatar) e imagens de hosts terceiros estão fora do escopo do acervo e não devem ser importados.
- Tipos de imagem: 6.766 JPEG; 725 PNG; 695 GIF; 37 SVG; 17 sem tipo (vazias). Conferência entre tipo declarado e
  assinatura: 51 arquivos declarados como PNG são JPEG e 2 declarados como JPEG são PNG. Os 37 SVG não são aceitos pela
  política de imagens do `aculta_image` (png, gif, jpg, jpeg, webp).

## 6. Codificação

- Charset no cabeçalho HTTP: UTF-8 em 21.322 páginas; ISO-8859-1 em 20.526; ausente em 16.098.
- Entre as 16.098 sem charset no cabeçalho: meta declarada como ISO-8859-1 em 9.614; sem meta em 6.477; UTF-8 em 7.
- Dessas, 4.155 trechos são UTF-8 válido e **2.322 não são** (provável ISO-8859-1 sem declaração).
- Risco: decodificar tudo como UTF-8 corrompe ~2.300 páginas; decodificar tudo como latin-1 corrompe ~4.100 páginas
  em UTF-8. A importação deve decodificar por ordem: cabeçalho HTTP, meta, validação UTF-8 e, por fim, latin-1/cp1252.

## 7. Dados pessoais

- Perfis permitidos com arquivo: 2.171. Páginas com e-mail visível: 191 (371 ocorrências).
- Esses dados não devem ser importados para o Drupal. A regra de usuários do responsável pede apenas quem comentou,
  e não inclui e-mail, avatar ou perfil completo. A importação deve gravar só o necessário (apelido e vínculo com
  conteúdo), e a decisão final sobre campos cabe ao responsável.
- A contagem de telefones foi descartada: o padrão casou com números que não são telefones.

## 8. Recomendações antes da importação

1. Estender `topicos_baixados()` para aceitar `showtopic` e tratar o template IPB 2 (forum e autores) antes de aplicar
   a regra de usuários. Fazer isso como commit na PR #297, sem nova PR ou branch.
2. Filtrar as capturas de erro (seção 3) e as de acesso negado/inexistente (seção 4.1) no ingestor. Não importar essas
   páginas e registrar cada uma como lacuna com motivo.
3. Investigar as 3.142 imagens presentes no CDX e não baixadas; decidir se as 21.988 fora do inventário entram no
   escopo (exige nova consulta ao CDX, sem o filtro `/board/`).
4. Aplicar a ordem de decodificação da seção 6 no ingestor, e validar com amostra antes da carga.
5. Não importar e-mail nem campos pessoais além do necessário (seção 7). Decisão do responsável.
6. Confirmar com o responsável se o acervo `__conflitos` (5.367 arquivos) deve ser importado. Ele é consultado pelo
   manifesto, mas não é ligado às URLs de origem sem consultar o manifesto.

## 9. Método e limites

- Todas as análises foram somente leitura, com `sudo -n` e `python3 -B -I`. Os scripts de checagem estão no
  diretório de trabalho da sessão e não estão versionados; os scripts de download e mapeamento estão em
  `scripts/growroom/`.
- Contagens por marcação de texto (seções 3 e 4.1) são aproximações fiéis ao HTML capturado. Não há classificação
  manual de amostra. Antes da importação, recomenda-se revisar uma amostra de 200 páginas por grupo.
- O inventário CDX e os manifestos usam a data de corte de 2025-07-19 (CDX). Capturas posteriores não estão incluídas.
- Nenhum valor pessoal foi copiado para este documento.
