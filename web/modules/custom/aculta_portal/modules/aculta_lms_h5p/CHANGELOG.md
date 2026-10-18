## 0.4.0-dev.144 — instalação privada de bibliotecas H5P — 2026-10-18

- Adiciona formulário administrativo para importar pacotes `.h5p` locais, com validação oficial H5P, limite/extensão fornecidos pelo Core, conteúdo ignorado e Hub remoto desligado.
- Bibliotecas e assets são gravados em armazenamento privado por arquivos Drupal gerenciados; imagens raster/SVG passam por `aculta_image`. Remoção administrativa limpa usos e arquivos por File API; cópias de conteúdo exigem arquivos gerenciados. A importação legada do editor falha fechada, e substituição silenciosa de assets instalados é recusada.
- Unit H5P: 8 testes, 25 assertions; Image Unit: 20 testes, 71 assertions; Kernel descoberta de hooks/serviços: 1 teste, 5 assertions. Teste com pacote real, browser e Runtime: DEFERRED; módulo permanece desabilitado.
- RUNTIME STATUS: DEFERRED.
## Continuação — instalação de bibliotecas H5P

- Corrige o schema de configuração e os nomes usados pelo adapter H5P para compartilhar o prefixo `aculta_lms_h5p_` definido na configuração de instalação.
- Migra a entidade e o field type H5P para PHP Attributes do Drupal 11; troca a descoberta de bibliotecas pelo serviço atual do Core.
- Instalação real via Kernel passou com biblioteca, asset JavaScript privado e SVG gerenciado pelo `aculta_image`; pacote sem biblioteca é rejeitado sem escrita no banco.
- Kernel H5P: 3 testes, 40 assertions; Unit H5P: 11 testes, 46 assertions. Runtime e navegador: DEFERRED.
- Restringe endpoints de progresso aos métodos HTTP usados pelo H5P e marca respostas com dados pessoais como `private, no-store`; rota validada em Kernel.
- Corrige leituras e resets de configuração que ainda usavam nomes do módulo contrib; teste instala em diretório privado customizado e o Unit do upload confere o destino configurado.
- RUNTIME STATUS: DEFERRED.

