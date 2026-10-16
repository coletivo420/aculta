# Changelog — aculta_image

## 0.1.0-dev.5 — recálculo de referências corrigido; paridade de binários e PHP com a Hostinger — 2026-10-17

- Recálculo de referências (`entity-usage:recreate`): causa — no nó 92 (blog "Bloco Sativa é objeto de Pesquisa na FIOCRUZ") a citação bibliográfica foi digitada no campo de URL de `field_references`. O link ficou inválido e o `entity_usage` lançava `InvalidArgumentException`.
- Correção no banco (`node__field_references` e `node_revision__field_references`, delta 0): a citação foi movida para o título do link e a URL ficou vazia. Nenhum conteúdo foi apagado. O recálculo passa a rodar sem exceção (538 entidades).
- Homelab: ImageMagick 7.1.1, Ghostscript, optipng, jpegoptim, gifsicle, cwebp e SOAP instalados. Limites de upload 128M via `90-aculta.ini`. PHP-FPM reiniciado.
- Hostinger: ver `docs/PLANO.md`, seção "Hostinger: o que está confirmado".
- Pendentes: política de PDF do ImageMagick (segurança) e processador da compressão (arquitetura). Ver o plano.

## 0.1.0-dev.4 — auditoria, duplicatas, quarentena e comparação visual (M4 a M7 e M9) — 2026-10-17

- Relatório `/painel-administrativo/imagens` (somente leitura): resumo do acervo, grupos de duplicatas exatas, pares visualmente parecidos e candidatas à revisão com motivos.
- Duplicatas exatas com `media_duplicates` 2.0.4 (checksum). No Runtime: 2 grupos, com 3 e 2 mídias.
- Candidatas com `entity_usage` 5.0.1: as sem referência em `file_usage` e sem entidade registrada. No Runtime: 13 candidatas, quase todas cópias de galerias.
- Quarentena (`ImageQuarantine`): envio move para `private://aculta-image-quarentena`; restauração; exclusão só após 30 dias e nova verificação de uso. Atualização `aculta_image_update_10002`. Testada no Runtime com arquivo de teste.
- Comparação visual (`DHash`): dHash de 64 bits com GD, sem dependência nova. Impressões calculadas no cron, 200 por execução (`aculta_image_update_10003`). Imagens que o GD não lê e imagens lisas são excluídas dos pares. Pares de cópias reais têm distância 0; fotos sequenciais muito parecidas ficam entre 4 e 6 bits. Sugestão para revisão, nada é alterado.
- Pendente (M8): compressão com `imageapi_optimize` depende de binários (`optipng`, `jpegoptim` etc.), que não estão no sistema, ou de serviço externo com chave. Decisão do responsável.
- Testes unitários: `DHashTest`, `QuarantineRulesTest` e `ImageIngesterTest` (11 no total).

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
