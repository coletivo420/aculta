# SheetJS Community Edition — procedência e manifesto

Biblioteca que interpreta XLSX, XLS, ODS e CSV para o ACULTA Document Viewer. Usada somente para
**ler** arquivos; o viewer não grava planilhas. Distribuída localmente, sem CDN em produção.

## Fonte

| Item | Valor |
| --- | --- |
| Pacote | SheetJS Community Edition (`xlsx`) |
| Versão | **0.20.3** |
| Licença | Apache-2.0 (`LICENSE.txt`) |
| Origem | https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js |
| Conferência | Hash idêntico ao `dist/xlsx.full.min.js` dentro de https://cdn.sheetjs.com/xlsx-0.20.3/xlsx-0.20.3.tgz (SHA-256 `cc015130aa8521e7f088f88898eba949ccdcbfb38df0bd129b44b7273c3a6f41`) |
| Dependências | Nenhuma (o build `full` é autocontido) |

**Por que 0.20.3 e não o `xlsx` do npm:** o npm publica até a 0.18.5. As linhas mais novas são
distribuídas pelo CDN da SheetJS. A 0.18.5 foi a versão avaliada e não é a escolhida.
Os avisos de segurança das versões antigas são uma razão para a escolha, mas não foram
confirmados nesta sessão contra a base de advisories.

## Arquivos

| Arquivo | SHA-256 | Bytes |
| --- | --- | --- |
| `web/libraries/sheetjs/dist/xlsx.full.min.js` | cc015130aa8521e7f088f88898eba949ccdcbfb38df0bd129b44b7273c3a6f41 | 951904 |
| `web/libraries/sheetjs/LICENSE.txt` | 4d2a38ac35cda06a555c84074a819d413339cd3691b822cae50f8f322fe01f64 | 11355 |

## Uso e limites

- Chamadas de rede: nenhuma no bundle.
- O viewer deve usar somente a leitura de buffers (`XLSX.read`) e nunca `XLSX.writeFile` ou similares.
- Fórmulas: a leitura usa os valores armazenados; não há recálculo.
- Atualização: baixar o novo `xlsx-<versão>.tgz`, conferir o hash do `dist` com o do CDN, copiar só o `full.min.js` e a licença.
