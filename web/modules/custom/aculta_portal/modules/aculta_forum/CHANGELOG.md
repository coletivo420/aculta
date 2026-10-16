## F9 parcial — fóruns restritos por nível — 2026-10-16

- Papel `associado` versionado (concessão manual). Campo `field_visibilidade` no contêiner: público, e-mail confirmado, associado.
- Visitante não vê contêiner, tópico nem listagem de fórum restrito. Associado vê; e-mail confirmado sem associado não vê o nível associado.
- Pendentes antes de criar restritos no site público: processador `content_access` no índice de busca (ainda desativado) e reindexação; exclusão no sitemap; comentários em tópico restrito; nível de curso (F8); política para `content_editor`.

## F5.1 — denúncias e participação com e-mail confirmado — 2026-10-16

- Sinalização de tópico e comentário pelo Flag (`sinalizar_topico`, `sinalizar_comentario`), uma por conta e por conteúdo.
- Fila de moderação em `/painel-administrativo/forum/denuncias`, restrita a `moderar denuncias do forum`.
- Participação no Fórum (criar, editar o próprio conteúdo, comentar, denunciar) exige `email_confirmed` (F-D13).
- Pendências: sinalização de comentário não testada no teste (não há comentário de fórum); papel `authenticated` com divergência anterior; avisos de resposta e busca ficam para F5.2 e F5.3.

# Changelog — ACULTA Fórum

## F2 estrutura pronta para a importação — 2026-10-16

- Campos `field_nome_historico` e `field_forum_origem_id` no perfil participante, aplicados no Runtime de teste.
- Serviço `aculta_user.legacy_accounts`: cria ou atualiza a autoria pela origem, sem duplicar; conta bloqueada
  com papel `usuarios_legados_forum`; sem e-mail, apelido ou conquista.
- Conta legada não tem página pública individual (404 para anônimo) e não recebe avatar do sorteio.
- Memorial `/u/lendashistoricas` com busca, paginação e estado vazio.
- Pendências que dependem da importação ou de aprovação: lista de autores comprovados e importação das contas;
  arte do avatar especial; texto aprovado do memorial; link de autoria nos tópicos e comentários; verificação
  de colisões de apelido em produção.

## F2 estrutural — memorial e papel legado — 2026-10-15

- Papel `usuarios_legados_forum` criado no Runtime de teste e em `config/sync`.
- Apelido `lendashistoricas` reservado; rota `/u/lendashistoricas` respondendo 200 com placeholder.
- Pendências da F2 (não executadas): lista de autores comprovados e Users bloqueados; arte do
  avatar especial; texto aprovado do memorial; nomes individuais; verificação de colisões de
  apelido em produção.
- Gates do Portal passam. Nada aplicado em produção.

## F1 — inventário e domínio no servidor de testes — 2026-10-15

- Inventário completo do prefixo CDX `growroom.net/board/` até
  `2025-07-19 09:36:40 UTC`: 271.283 capturas e 177.673 URLs distintas. O JSON
  fica no armazenamento privado do Homelab; resumo, caminho e checksum estão em
  `docs/RECONSTRUCAO.md`.
- Cloudflare: criado A direto, sem proxy, para
  `growroom420.aculta.toca.net.br` → `45.191.204.31`; o registro antigo
  `forum.aculta.toca.net.br` foi mantido para responder 404 sem redirect.
- Homelab: VirtualHost e certificado Let's Encrypt do novo host ativos;
  `trusted_host_patterns`, Domain e alias do Runtime atualizados sem trocar IDs
  nem purpose. HTTPS `/forum` respondeu 200, canonical e noindex conferidos.
- Sessão do teste confirmada no container: `.aculta.toca.net.br`, `SameSite=Lax`
  e `Secure`.
- Host antigo documentado como 404 sem redirect. Deployer `domains` PASS (9/9),
  `robots --env=test` PASS e `validate-environment-addresses.php` PASS (1.759
  arquivos).
- O helper externo `/home/piradopirata/scripts/forum-setup.sh`, que ainda
  provisionava o host antigo, foi aposentado por stub seguro; cópia original
  preservada no backup privado da F1.
- Sitemap do Fórum responde 200 e o exclui do índice conforme a política
  instalada. `sitemap --env=test` permanece FAIL por 404 de LINKS, fora desta
  fase.
- Produção não foi alterada: não havia DNS do host antigo, e o TLS direto da
  origem Hostinger para `growroom420.aculta.org` falhou. O novo DNS de produção
  aguarda o cadastro e a validação do hostname na origem.
- `RUNTIME STATUS: PASS (Homelab/teste); produção não alterada.` Nenhum `updb`,
  `cim`, import/export ou conteúdo foi executado.

## 2026-10-15 — ajuste de identidade aprovado

- Marca futura: Growroom420; prefixo público `growroom420.aculta`.
- Purpose interno `forum` e fontes históricas Growroom preservados.
- Decisão documental; nenhum domínio provisionado nesta alteração.

## 2026-10-15 — abertura documental do projeto

- Plano aprovado: Growroom, memória histórica, conversas atuais vinculadas e
  integração com as capacidades existentes do Portal.
- Registradas a mudança futura de prefixo para `growroom`, a autoria individual
  dos legados e a página coletiva `/u/lendashistoricas`.
- Documentados pesquisa, arquitetura, reconstrução, roadmap e gates futuros.
- Entrega exclusivamente documental, sem versão de módulo instalável ou Runtime.
- [Evidência de revisão e validação estática](../../../../../../docs/portal/FORUM-GAME-PESQUISA-VALIDACAO.md).
