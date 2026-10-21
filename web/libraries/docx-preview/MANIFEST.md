# docx-preview — procedência e manifesto

Biblioteca de visualização de DOCX usada pelo ACULTA Document Viewer (planejamento em
`docs/portal/ACULTA-DOCUMENT-VIEWER-MIGRACAO.md`). Distribuída localmente; sem CDN.

## Fontes

| Pacote | Versão | Licença | Origem | Integridade (npm) |
| --- | --- | --- | --- | --- |
| docx-preview (docxjs) | 0.4.1 | Apache-2.0 | https://registry.npmjs.org/docx-preview/-/docx-preview-0.4.1.tgz | `sha512-Dv4g+LeE0swHpPvNC0lAT0YsR5Zq5pAxw7d3vHQ8EChBLgIrqqe9VPL5nVt2HBJap8bEZbXUI0TJob7kBeNU9w==` |
| jszip (dependência) | 3.10.1 | MIT OR GPL-3.0-or-later (usada sob MIT) | https://registry.npmjs.org/jszip/-/jszip-3.10.1.tgz | `sha512-xXDvecyTpGLrqFrvkrUSoxxfJI5AH7U8zxxtVclpsUtMCq4JQ290LY8AW5c7Ggnr/Y/oK+bQMbqK2qmtk3pN4g==` |

Os tarballs foram baixados com `npm pack` em diretório temporário, a integridade foi conferida
contra o registry e o conteúdo foi extraído sem executar scripts. Só `dist/*.min.js` e as licenças foram copiados.
Não há `package.json` nem `node_modules` neste diretório.

## Arquivos

| Arquivo | SHA-256 | Bytes |
| --- | --- | --- |
| `dist/docx-preview.min.js` | c4a133c65a112799e35b143c572dfff9e923a3bf39b5fd3b07a06aa0f8f530c2 | 75527 |
| `dist/jszip.min.js` | acc7e41455a80765b5fd9c7ee1b8078a6d160bbbca455aeae854de65c947d59e | 97630 |
| `LICENSE-docx-preview.txt` | 8668bf4417d161e4eb4d47d6044526e4914d9eb3c748573d9bb7e87708c1253f | 11558 |
| `LICENSE-jszip.md` | 566c953c6090b1218ca6217dd7359d45dde46581968586dc607d59a78af6a9c4 | 33753 |

## Forma de carregamento

- Builds UMD clássicos, sem bundler. Ordem: `jszip.min.js` e depois `docx-preview.min.js`.
- Global exposto: `docx` (função `docx.renderAsync`). JSZip é lido de `window.JSZip`.
- Verificado no ambiente de vendoring com um contexto de script isolado: `JSZip` e `docx.renderAsync` existem após o carregamento.
- Não usar `dist/docx-preview.mjs`: importa `'jszip'` como especificador nu, que o navegador não resolve sem import map.

## Configuração obrigatória na chamada

- `renderAltChunks: false`. **O padrão do código é `true`** (`docx-preview.mjs`, objeto de defaults), o que permite HTML embutido. Deve ser passado explicitamente.
- `experimental: false` (padrão, mantido).
- Usar somente `renderAsync`. O README informa que as APIs internas de parsing podem mudar.

## Atualização

1. Baixar o novo tarball com `npm pack <pacote>@<versão>` em diretório temporário novo.
2. Conferir `dist.integrity` no registry e o hash do tarball.
3. Copiar apenas `dist/docx-preview.min.js` e a licença.
4. Atualizar esta tabela e a versão declarada nos docs do módulo.
5. Repetir o teste de carregamento e a verificação de `renderAltChunks: false` no teste de navegador.
