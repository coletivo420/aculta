# Changelog — aculta_image

## 0.1.0-dev.1 — barreira de uso obrigatório e catálogo de finalidades (M0.5) — 2026-10-17

- Catálogo de finalidades (`ImageCatalog`): 18 estilos existentes mapeados; única fonte de nome de estilo.
- Política única de upload (`UploadPolicy`): png, jpg, jpeg, webp, 10 MB. Sem dimensão máxima; o limite de 2 MB e 4096 px da conquista fica em `aculta_game`.
- Helpers registrados no Status do Portal (`aculta_portal.helper`).
- Gate `scripts/validate-aculta-image.php` com tolerância zero (regras R1 a R8). Reprova enquanto o código existente não for migrado; a lista de ocorrências é o roteiro das fases M1 a M3.
- Regra permanente em `AGENTS.md` ("Imagens e arquivos de imagem").
- Testes: `ImageCatalogTest` (3 testes).
