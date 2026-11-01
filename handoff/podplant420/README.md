# Podplant420 — pacote de handoff

Arquivos de identidade Podplant420 que não são consumidos pelo runtime do tema `aculta420`. Ficam fora de `web/themes/custom/aculta420/` para que o tema não carregue binários sem consumidor.

- `source/originais/`: 4 PNG recebidos, intocados.
- `source/masters/`: 4 PNG derivados de trabalho (fonte dos WebP).
- `web/horizontal/`: WebP 720/960 px, on-dark e on-light, sem template consumidor.
- `asset-inventory.json`: SHA-256, dimensões, alfa e peso dos 22 arquivos, com caminhos relativos à raiz do repositório.

Runtime (10 WebP: stacked 128/256/512 e horizontal 240/480): `web/themes/custom/aculta420/assets/branding/podplant420/web/`.

Documentação: `web/themes/custom/aculta420/docs/branding-podplant420.md`. Verificação: `python3 scripts/verify-podplant420-assets.py`.
