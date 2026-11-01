# Tabulator — procedência e manifesto

Grid usado como interface das planilhas (modo Planilha, módulo Spreadsheet) e das tabelas de dados.
Distribuído localmente, sem CDN.

## Fonte

| Item | Valor |
| --- | --- |
| Pacote | `tabulator-tables` (npm) |
| Versão | **6.5.3** (última da linha 6.5; a última geral é 6.7.0) |
| Licença | MIT (`LICENSE.txt`) |
| Origem | https://registry.npmjs.org/tabulator-tables/-/tabulator-tables-6.5.3.tgz |
| Integridade (npm) | `sha512-nJ2QaqZzQvd9B4rf4LBnRxfb6sSqA/BX2OsXnSJ2d8k21oXDSTtFtOUrDap04a/6BDGivC/MByCxy43ugadJIg==` |
| Dependências | Nenhuma |

## Arquivos

| Arquivo | SHA-256 | Bytes |
| --- | --- | --- |
| `web/libraries/tabulator/dist/tabulator_esm.min.mjs` | 6c45ce054c8266f05a61e746acc91ec67e043de378b2f6d8e08ac1c5d0032b1a | 449719 |
| `web/libraries/tabulator/dist/tabulator.min.css` | 405ae24218357d80df2a5f3addf138666d035f970dac4ebdb0265d0b5373efcb | 28480 |
| `web/libraries/tabulator/LICENSE.txt` | 191a2ee554684e1064c897b432f0e1bc6dfa714ca045d3f6ea2cf692cbd398b7 | 1082 |

## Forma de carregamento

- `tabulator_esm.min.mjs` é ESM sem imports nus (verificado). Contém o módulo Spreadsheet.
- Carregar somente nas páginas de planilha, via biblioteca Drupal dedicada.
- CSS: `tabulator.min.css` (estrutura base). A aparência é dada pelo ACULTA420 com tokens; não usar tema CSS de terceiros.

## Restrições do bundle (verificadas)

- O bundle inclui os módulos AJAX (`fetch`, `XMLHttpRequest`) e de importação. O viewer **não** usa
  carregamento remoto: dados entram como arrays já lidos pelo SheetJS, por API explícita.
- Não usar `setData()` convencional no modo Planilha (regra do plano).
- Editores de célula não são habilitados.
