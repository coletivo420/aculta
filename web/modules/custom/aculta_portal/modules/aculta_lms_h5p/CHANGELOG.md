## 0.4.0-dev.144 — instalação privada de bibliotecas H5P — 2026-10-18

- Adiciona formulário administrativo para importar pacotes `.h5p` locais, com validação oficial H5P, limite/extensão fornecidos pelo Core, conteúdo ignorado e Hub remoto desligado.
- Bibliotecas e assets são gravados em armazenamento privado por arquivos Drupal gerenciados; imagens raster/SVG passam por `aculta_image`. Remoção administrativa limpa usos e arquivos por File API; cópias de conteúdo exigem arquivos gerenciados. A importação legada do editor falha fechada, e substituição silenciosa de assets instalados é recusada.
- Unit H5P: 8 testes, 25 assertions; Image Unit: 20 testes, 71 assertions; Kernel descoberta de hooks/serviços: 1 teste, 5 assertions. Teste com pacote real, browser e Runtime: DEFERRED; módulo permanece desabilitado.
- RUNTIME STATUS: DEFERRED.
