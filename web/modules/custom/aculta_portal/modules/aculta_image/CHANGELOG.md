# Changelog — aculta_image

## 0.1.0-dev.2 — migração dos consumidores e ingestão de imagem externa (pronto para importação) — 2026-10-17

- Migração (gate `validate-aculta-image.php` em PASS): estilos e limites de upload saem dos consumidores. Portal (painel da conta, instalação), aculta_user (foto de perfil, recorte, perfil público, limpeza de avatar, troca de foto), aculta_magazine (autoria, abertura do Fórum), aculta_gallery (grade e formulário de envio) e aculta_pdf_viewer (miniatura de PDF).
- Foto de perfil: limite de 2 MB passa a 10 MB, pela política única (decisão do responsável).
- Tema: a lógica de srcset do card sai de `ThemeHooks` e vai para `Hook/ImageStyleHooks`.
- Campos de imagem (`node.project.field_image`, `media.image.field_media_image`, `user.user.user_picture`) declaram a política em `third_party_settings`, com schema.
- `ImageIngester` (`aculta_image.ingester`): porta única para imagem externa. Valida extensão e tamanho, confirma que o conteúdo é imagem (PNG, JPEG, GIF ou WebP), calcula SHA-256, reaproveita arquivo existente com o mesmo conteúdo, grava em `public://importacao` sem sobrescrever e registra a origem em `aculta_image_origin` (uma linha por arquivo e URL).
- Atualização `aculta_image_update_10001`: cria a tabela `aculta_image_origin`.
- Correção: `ForumOpeningPost` recebe o renderizador no serviço (regressão encontrada no Runtime e corrigida).
- Testes: 5 unitários. Verificação no Runtime: páginas principais 200 (anônimo e logado); ingestão com criação, reaproveitamento, recusa de conteúdo falso, extensão fora da política e arquivo ausente.

## 0.1.0-dev.1 — barreira de uso obrigatório e catálogo de finalidades (M0.5) — 2026-10-17

- Catálogo de finalidades (`ImageCatalog`): 18 estilos existentes mapeados; única fonte de nome de estilo.
- Política única de upload (`UploadPolicy`): png, jpg, jpeg, webp, 10 MB. Sem dimensão máxima; o limite de 2 MB e 4096 px da conquista fica em `aculta_game`.
- Helpers registrados no Status do Portal (`aculta_portal.helper`).
- Gate `scripts/validate-aculta-image.php` com tolerância zero (regras R1 a R8). Reprova enquanto o código existente não for migrado; a lista de ocorrências é o roteiro das fases M1 a M3.
- Regra permanente em `AGENTS.md` ("Imagens e arquivos de imagem").
- Testes: `ImageCatalogTest` (3 testes).
