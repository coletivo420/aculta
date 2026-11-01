# Podplant420 — arquivos estáticos de branding

Este diretório contém **10 arquivos WebP** de runtime em `web/` (stacked 128/256/512 e horizontal 240/480). Originais, masters e os WebP 720/960 px estão no pacote de handoff `handoff/podplant420/`, fora do tema, porque não têm consumidor.

- `on-dark`: letras claras, para superfícies escuras.
- `on-light`: letras escuras, para superfícies claras.
- `stacked`: composição quadrada com áreas transparentes internas intencionais.
- `horizontal`: composição recortada, preservando proporção e sem remodelar a identidade.

`handoff/podplant420/asset-inventory.json` registra hashes SHA-256, dimensões, alfa e peso dos 22 arquivos (caminhos relativos à raiz). Os PNGs em `handoff/podplant420/source/originais/` foram copiados sem edição; os de `source/masters/` são **derivados de trabalho, não os originais**.

Use `web/` apenas onde existir consumidor real. Não introduzir assets em Domain Header, Institution Bar, nem filtrar/recolorir as marcas. Não usar estas logomarcas como favicon global. A escolha claro/escuro pertence à camada de apresentação consumidora, mantendo mesmo tamanho/layout.

Leia `../../../docs/branding-podplant420.md` (documentação do tema, em `web/themes/custom/aculta420/docs/`).
