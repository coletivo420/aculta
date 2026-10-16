# Roadmap — ACULTA Fórum

Plano aprovado em 2026-10-15. F0 abriu a documentação; F1 foi executada no
servidor de testes em 2026-10-15. F2 em diante não estão implementadas.
Dependências externas só entram na fase que comprovar sua necessidade. A PR #286
recebe esta abertura por autorização para continuar na PR existente; cada fase
funcional posterior terá PR rastreável, sem merge parcial automático ou tags.

| Fase | Entrega | Aceite e dependências |
| --- | --- | --- |
| F0 — projeto e pesquisa | README, decisões, arquitetura, reconstrução e fontes | Documentos coerentes com a sessão e links válidos; sem alteração de Runtime |
| F1 — inventário e domínio | Inventário CDX completo do prefixo histórico `/board/`, corte temporal; troca do prefixo por ambiente | **Executada no teste** (2026-10-15): inventário mensurável; Deployer/Domain, DNS/TLS, sessão, canonical e sitemap do Fórum verificados; política do host antigo registrada. Ver [Reconstrução](RECONSTRUCAO.md) e [Decisões](DECISOES.md). |
| F2 — identidades históricas | Papel, Users bloqueados, memorial coletivo, nomes individuais e avatar especial | **Estrutura pronta no teste** (2026-10-16): campos de identidade no perfil, serviço idempotente de contas legadas, acesso negado a página individual, memorial com busca e avatar especial aguardando arte. **Pendente, depende de dados ou aprovação**: contas legadas criadas pela importação, link de autoria em tópicos e comentários, arte aprovada do avatar. Texto do memorial aprovado em 2026-10-16 (2002 a 2025, sem canal de contato). Toda autoria aponta ao coletivo; sem perfis individuais ou onboarding |
| F3 — migração piloto | Estrutura, autores, tópicos, respostas e arquivos de amostra | IDs/datas/quotes corretos, comentários fechados, reexecução sem duplicação, sem e-mails/conquistas |
| F4 — experiência atual (próxima fase, 2026-10-17) | Home, listagens, tópico, ações sociais e conversa vinculada | Desktop/mobile, teclado, fallback sem JS, dois modos de cor; vínculo novo → histórico sem modificar original |
| F5 — participação e descoberta (F5.1 e F5.2 implementadas no teste) | Denúncias, moderação, busca e avisos de respostas | Permissões reais, publicação após aprovação, opt-in e deduplicação; limites de participação preservados |
| F5.2 — avisos de resposta | Aviso obrigatório no site para quem segue o tópico; e-mail opt-in pela fila existente, agrupado por tópico; contador por tópico; avisos gerais semelhantes agrupados | Política em `docs/portal/AVISOS-E-NOTIFICACOES.md` e F-D21. Aceite: uma entrada por tópico com contador; um e-mail por tópico por janela; deduplicação por comentário; nada para conteúdo restrito ou histórico; desligado por padrão |
| F6 — reconstrução em lotes | Conteúdo e mídia de todo o inventário aprovado | Cobertura reconciliada, ausências explicadas, origem rastreável; sem overwrite de dados atuais |
| F7 — Portal e loja | Revista/Wiki/projetos/galerias e discussão por produto | Uma discussão por vínculo, Galeria na Revista, pedidos privados, transações em MAIN |
| F8 — LMS reservado | Piloto por curso e atividade/contexto | Dois cursos e usuários de acesso distinto; zero exposição por rota, lista, busca, cache, perfil, arquivo ou e-mail |
| F9 — Fóruns restritos (associados e cursos) | Visibilidade por fórum: público, e-mail confirmado, marcadores de associado (F-D20) ou contexto de curso. Fórum de curso visível por turma e por atividade. Restritos não aparecem para visitante, não entram na busca pública, na indexação nem no sitemap, e não são acessíveis a bots. Aplicado em acesso, listagens, busca, feeds, Revista, contagens, último autor, notificações e cache. Antes do piloto F8. | **Verificado no Runtime de teste (2026-10-16)**: papel associado versionado; campo de visibilidade no contêiner; grants por realm; negação de contêiner e tópico por papel; listagem filtrada; comentário de tópico restrito invisível para anônimo; restrito fora do sitemap; processador `content_access` ativo e tópicos de fórum fora do índice de busca (tipo `forum` não selecionado); bots recebem 403. **Pendente**: nível de curso (depende da F8); moderação implementada (ocultar, despublicar, excluir; exceto histórico), F-D16; confirmação manual de e-mail pelo administrador não existe (`PAPEIS-PARTICIPACAO.md`, seção 5); dúvidas em `PAPEIS-PARTICIPACAO.md`, seção 6; exclusão no sitemap precisa ser mantida se o tipo `forum` entrar no sitemap. Bots e visitantes recebem 404 nos restritos (F-D15). **Decisões aprovadas** (F-D14). Associado é marcador puro (F-D19): o nível "associado" dos fóruns foi removido; a visibilidade usa só e-mail confirmado. Aceite: zero vazamento de título, trecho, contagem, autor, URL ou índice para visitante e para conta sem o papel; bots recebem 404 nos restritos e não os encontram em sitemap, feed ou busca; teste com contas de cada papel nos dois modos de cor e em mobile; `/forum` público continua igual para conteúdo público |

A F9 (fóruns restritos) deve entrar antes do piloto F8: os fóruns de curso dependem do mesmo controle de acesso. Fases extensas podem ser divididas em subfases com PR próprio. A ordem entre UX e
lotes pode ser intercalada, mantendo o Runtime testável. O acervo público não
depende da conclusão da integração LMS. Gamificação depende do contrato do Game,
sem conceder conquistas durante a migração histórica.

## Estado em 2026-10-17

- F1: executada no teste. F2: estrutura pronta; pendente importação e arte do avatar.
- F5.1: denúncias e fila de moderação. F5.2: avisos de resposta e de atualização, contador por tópico,
  rótulo Lido/Não lido, alertas do site com rótulo, retenção de 6 meses dos avisos do Fórum. Documentado em
  `aculta_messenger/docs/AVISOS-E-MENSAGENS.md`.
- F9: parcial. Pendentes: nível de curso (depende da F8) e a exclusão no sitemap se o tipo `forum` entrar nele.
- F3: aguarda a lista de autores da importação.
- F4: em andamento. Primeira parte no teste (2026-10-17): índice do fórum em cartões (`forum-list.html.twig` do tema),
  botão de publicar no estilo do site. Pendentes: tópico (ações sociais), conversa vinculada ao histórico (depende da F3),
  verificação em modo escuro e com conta logada.

## Antes da primeira alteração funcional

- Anotar classificação MINOR/PATCH e subversão alvo conforme a linha corrente;
  não reservar uma versão de merge sem conferir `main`.
- Confirmar hostname, diretório, branch, estado Git e `drush status`, URI e banco.
- Inspecionar config drift, efeitos persistentes e recuperação; não fazer `cim`,
  `updb` ou exportação integral por conveniência.
- Declarar os serviços e metadados do Status conforme o contrato do Portal.

## Verificação por fase

Gates: Drupal 11+, fronteira módulo/tema, endereços de ambiente, slugs públicos e
os gates de identidade/avatar quando tocados. Fundação do tema se houver design.
Casos de conteúdo e importação: autores homônimos, origem sem autor, paginação
ausente, anexos indisponíveis, replay repetido, resposta aprovada depois do insert,
referências e datas originais.

## Resultado da F1 — 2026-10-15

- Mapeamento do Deployer: `forum_aculta_org` → `growroom420.aculta.toca.net.br`
  no teste e `growroom420.aculta.org` em produção; `domains` reportou 9/9 linhas
  alinhadas.
- Runtime de teste: Domain e alias preservaram seus IDs e purpose `forum`, com
  hostname atualizado; HTTPS respondeu 200 em `/forum`, com certificado válido,
  canonical no novo host de teste e `X-Robots-Tag: noindex, nofollow, noarchive`.
- Sessão: o container carregado no teste usa `cookie_domain: .aculta.toca.net.br`,
  `cookie_samesite: Lax` e `cookie_secure: true`; o novo host está coberto pelo
  domínio compartilhado.
- Sitemap: `/sitemap.xml` no host do Fórum respondeu 200. O índice não inclui o
  Fórum, conforme a política instalada que exclui FORUM e ACCOUNT da indexação.
  A sonda global `sitemap --env=test` continua com falha independente: o host
  LINKS respondeu 404. Nenhuma configuração de LINKS foi alterada.
- O hostname anterior de teste responde 404 em HTTP e HTTPS, sem redirecionamento.
  Seu registro DNS e certificado foram mantidos para que a resposta seja controlada.
- O DNS de `growroom420.aculta.org` não foi publicado: o teste TLS direto na
  origem Hostinger falhou antes da configuração do hostname. O destino de
  produção continua declarado no Deployer e requer validação da origem antes de
  receber tráfego. Nenhuma configuração de produção foi alterada.
- Nenhum `updb`, importação/exportação de configuração ou migração de conteúdo
  foi executado.

Aplicar no Homelab quando o escopo funcional estiver coerente, após confirmar o
destino e os efeitos. Registrar só os testes realmente executados; casos não
executados ficam `RUNTIME STATUS: DEFERRED`. Produção e tags exigem decisão própria.

## Questões a fechar na fase adequada

Política do hostname anterior; corte temporal para captura complementar;
reivindicação futura de autoria; discussão por curso/turma; extensão de D13 de
galerias se for desejada. Nenhuma dessas questões impede F0.
