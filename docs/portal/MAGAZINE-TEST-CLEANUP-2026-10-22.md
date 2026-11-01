# Limpeza autorizada do Runtime Magazine — 2026-10-22

## Escopo e ambiente

- Seleção expressa pelo responsável: todos os 12 Nodes `revista` atuais no Runtime de teste.
- Host `toca`; branch `docs/aculta-forum-0.1.0-dev.1`; HEAD antes da limpeza `52defd11138a0de1e2c98b21101420b9c486c818`.
- Drush confirmou Drupal root `/home/piradopirata/projetos/aculta/web`, URI `https://revista420.aculta.toca.net.br`, banco `aculta_runtime`, driver MySQL.
- A seleção continha 24 revisões. Quatro Nodes tinham marcadores de embed; nenhum embed apontava a `media_gallery`.

## Prévia, referências e salvaguardas

- Entity Query, Entity Usage, referências estruturadas e campos de texto versionados não encontraram referências externas para os Nodes, 17 Media `image` ou 17 arquivos de imagem.
- Não havia Comments hospedados nos Nodes selecionados. A busca por embeds não deixou tabelas sem inspeção.
- Um Media `remote_video` referenciado pela seleção não contém arquivo físico. Foi preservado porque sua ownership continua sem definição.
- Dump de banco: `/tmp/aculta-revista-cleanup-20261022.sql.gz`, modo `0600`, 5.449.057 bytes; `gzip -t` PASS. SHA-256: `cc1797253523b5acfaa4eb5b6f0d270bd09a2215969d7f418ed58d9a2f0c2c9e`.
- Backup dos 17 arquivos: `/tmp/aculta-revista-cleanup-files-20261022.tar.gz`, modo `0600`; listagem `tar -tzf` PASS. SHA-256: `dfd54ce504f2423ffde8d6144b6d741fec6c1c4e5cd005557375869806049f70`. O manifesto privado contém URI original, FID e checksum por arquivo.
- Os backups ficaram fora do repositório em `/tmp`; são cópias locais do Host de teste, não backup externo de retenção.

## Execução e verificação

- 12 Nodes `revista` removidos pela Entity API, incluindo suas revisões e aliases geridos pelo Drupal.
- 17 Media `image` sem referências removidos pela Entity API.
- 17 entidades File e respectivos arquivos físicos removidos por `aculta_image.file_remover`, que revalidou File Usage e Entity Usage e invalidou derivados.
- Verificação posterior: zero Nodes selecionados, zero Media `image` selecionados, zero File entities/arquivos físicos dos 17 candidatos e zero aliases `/node/{nid}` restantes. O Media `remote_video` permanece.
- `aculta_index` estava desabilitado. Nenhuma configuração, outro bundle, estatística, usuário, conteúdo de outra área ou Runtime de produção foi alterado.

## Limites

A limpeza foi uma operação pontual autorizada. A função compartilhada do Portal
e os adapters dos módulos seguem planejados, conforme
[CONTENT-CLEANUP-POLICY.md](CONTENT-CLEANUP-POLICY.md); esta execução não os
implementa nem os habilita.
