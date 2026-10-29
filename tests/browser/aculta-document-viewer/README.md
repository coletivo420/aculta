# Revisão visual do ACULTA Document Viewer

Firefox ESR visível no servidor de desenvolvimento. `ACULTA_TEST_URL` deve
apontar para uma página pública descartável que incorpora uma Media `document`
de teste pelo filtro `media_embed`; não use PDF confidencial. O script confirma
o viewer e controles básicos, percorre controles por Tab e verifica o reflow
em 1280/768/641/640/390/320 CSS px. As métricas distinguem valores compactos
até 640px e amplos acima desse limite. `PDF_VIEWER_CSS_OVERRIDE=1` injeta a
folha do worktree configurado em `ACULTA_PROJECT_ROOT` após carregar a página
do servidor, para comparar o candidato sem rebuild de cache. Capturas e
`summary.json` ficam fora do repositório, em `SUITE_OUT`.

```sh
DISPLAY=:0 \
SUITE_OUT=/caminho/fora-do-repositorio/aculta-document-viewer \
ACULTA_TEST_URL=https://aculta.toca.net.br/node/ID \
ACULTA_EXPECTED_TITLE='Título do conteúdo de teste' \
ACULTA_PROJECT_ROOT=/home/piradopirata/worktrees/opencultures-f0-adm8-e8 \
PDF_VIEWER_CSS_OVERRIDE=1 \
python3 -I tests/browser/aculta-document-viewer/visual.py
```

Requer Firefox ESR, geckodriver e `websockets` Python. Não altera configuração
ou conteúdo. Não valida privacidade de documentos privados, leitor de tela, CSP,
PDF de grande porte nem o resultado após cache rebuild/deploy. No modo candidato,
somente esta folha é injetada; as demais libraries vêm da página servida.

## Opções administrativas DOCX e planilhas (D-48)

`office-options.py` sobe um servidor HTTP local efêmero (`127.0.0.1`) servindo
o `web/` do checkout candidato (`ACULTA_PROJECT_ROOT`) e fixtures OOXML/CSV em
memória para validar no Firefox ESR visível (`DISPLAY=:0`):

- subabas horizontais do caminho único `/painel-administrativo/aculta/leitor-documentos`
  (**Geral** `aculta_document_viewer_general`, **PDF** `aculta_document_viewer_pdf`,
  **Documentos** `aculta_document_viewer_doc` e **Planilhas**
  `aculta_document_viewer_sheet`),
  além da estrutura e rotulagem acessível das seções administrativas `Apresentação de
  documentos DOCX` (`docx_render_headers`, `docx_render_footers`,
  `docx_render_footnotes`, `docx_render_endnotes`, `docx_respect_page_breaks`) e
  `Apresentação de planilhas` (`spreadsheet_default_mode`,
  `spreadsheet_page_size`);
- propagação real das opções ao leitor DOCX em iframe sandboxed (`allow-scripts`
  sem `allow-same-origin`), confirmando exibição/omissão de cabeçalho, rodapé,
  notas de rodapé/fim e quebra de página;
- propagação real de `data-aculta-sheet-default-mode="table"` e
  `data-aculta-sheet-page-size="25"` ao leitor de planilhas Tabulator/SheetJS,
  confirmando modo inicial Tabela, seletor de aba visível, paginação de 25
  linhas e células somente leitura;
- navegação por `Tab` com `:focus-visible` e ausência de rolagem horizontal em
  `1280`, `390` e `320` CSS px nos temas **ACULTA420** e **Olivero**.

```sh
DISPLAY=:0 \
SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-document-viewer-office \
ACULTA_PROJECT_ROOT=/home/piradopirata/worktrees/opencultures-f0-adm8-e8 \
python3 -I tests/browser/aculta-document-viewer/office-options.py
```

Não toca no banco `aculta_runtime`, não importa Config Sync e remove o perfil
temporário do Firefox ao encerrar.
