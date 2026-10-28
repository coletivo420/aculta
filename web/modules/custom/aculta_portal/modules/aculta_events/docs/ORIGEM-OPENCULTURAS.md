# Origem e licenciamento — OpenCulturas

Fonte: OpenCulturas 3.1.4, distribuição Drupal (`openculturas/openculturas-distribution`).
Referência local: `/home/piradopirata/projetos/aculta-openculturas/` (somente leitura).

| Item | Valor |
| --- | --- |
| Arquivo recebido | `openculturas-3.1.4.tar.gz` |
| SHA-256 | `d2e1c5ecdf7c3aa104b55fea9687d901b14730f7ded0a0dbfb51477722de051d` |
| Licença do projeto | GPL-2.0-or-later (`LICENSE.txt`; `composer.json`) |
| Ícones | Phosphor Icons (MIT), `profile/themes/opcult/sprite/svg/LICENSE_phosphor-icons.md` |
| Fontes | Montserrat e OpenSans no pacote; não serão usadas (o ACULTA usa Oswald e Inter) |

## Regras de cópia

1. **Copiar e adaptar.** Nunca copiar um arquivo inteiro do tema `opcult` para o `aculta420`.
2. **Preservar avisos.** Se o arquivo de origem tiver cabeçalho de copyright ou licença, ele
   permanece. Arquivos novos derivados recebem a linha: `Adaptado de OpenCulturas 3.1.4
   (GPL-2.0-or-later), profile/<caminho>`.
3. **Registrar cada cópia** na tabela abaixo: caminho de origem, destino, alterações e data.
4. **Não copiar ativos de terceiros** sem a licença do próprio ativo (fontes, imagens de exemplo).
5. **Compatibilidade:** GPL-2.0-or-later é compatível com o `LICENSE` (GPLv3) do repositório. A
   classificação não substitui parecer jurídico; a revisão está prevista na fase F9.

## Mapa de cópia (preenchido à medida que as fases avançam)

| Origem (`profile/…`) | Destino ACULTA | Tipo | Alterações | Data |
| --- | --- | --- | --- | --- |
| — | — | — | Nenhuma cópia feita até a fase F0 | — |
| `profile/modules/custom/openculturas_custom/src/Plugin/DateAugmenter/AddToCal.php` (só regra, nenhum trecho copiado) | `aculta_portal/modules/aculta_calendar/src/Ics/IcsEventBuilder.php` | Reescrita do zero (E6, C1) | Gerador `.ics` próprio: escape RFC 5545, CR/LF viram espaço em SUMMARY e LOCATION (S2), UID estável, dobra em 75 octetos, DTSTART/DTEND no horário de Brasília (TZID com VTIMEZONE), DTSTAMP em UTC. Sem `addtocal_augment` (T10). Sem atribuição, pois nenhum trecho foi copiado | 2026-10-28 |
| `profile/modules/custom/openculturas_calendar_widget/src/Controller/OpenculturasCalendarWidgetController.php` (só regra do S3, nenhum trecho copiado) | `aculta_portal/modules/aculta_calendar/src/Embed/EmbedSourceAllowlist.php` | Reescrita do zero (E5, C2) | Lógica de allowlist do `source_uri`: caminho interno ou https de host da lista; recusa javascript:, data:, http, protocolo relativo, credencial e porta fora do padrão (S3). Sem rota, controller ou bloco nesta fase. Sem atribuição, pois nenhum trecho foi copiado | 2026-10-28 |
| `profile/modules/custom/openculturas_map/src/Plugin/Field/FieldFormatter/SmartDateUpcomingFormatter.php` | `aculta_portal/modules/aculta_events/src/Plugin/Field/FieldFormatter/DateRangeUpcomingFormatter.php` | Adaptado (GPL-2.0-or-later; cabeçalho no arquivo) | Formatter de datas futuras sobre `daterange` do core (sem smart_date). Sem cron de invalidação: o cache expira no fim da próxima data (T5). Formato dd/mm/aaaa HH:mm em Brasília. Teste Kernel: DateRangeUpcomingFormatterKernelTest, 3 ok. | 2026-10-28 |

## Diferença de licença com o AGPL

O `aculta_links` decidiu não copiar código do LinkStack por ser AGPL-3.0. O OpenCulturas não é AGPL:
o código dele é GPL-2.0-or-later, e a regra do `aculta_links` não se aplica a ele. Ainda assim,
cada cópia precisa do registro acima.

## Documentação copiada

`upstream-openculturas/` contém cópia de `README.md`, `DEVELOPMENT.md`, `CONTRIBUTING.md`,
`SECURITY.md`, `CHANGELOG.md` e `LICENSE.txt`, sem alteração de conteúdo.
