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
| F4 — experiência atual | Home, listagens, tópico, ações sociais e conversa vinculada | Desktop/mobile, teclado, fallback sem JS, dois modos de cor; vínculo novo → histórico sem modificar original |
| F5 — participação e descoberta | Denúncias, moderação, busca e avisos de respostas | Permissões reais, publicação após aprovação, opt-in e deduplicação; limites de participação preservados |
| F6 — reconstrução em lotes | Conteúdo e mídia de todo o inventário aprovado | Cobertura reconciliada, ausências explicadas, origem rastreável; sem overwrite de dados atuais |
| F7 — Portal e loja | Revista/Wiki/projetos/galerias e discussão por produto | Uma discussão por vínculo, Galeria na Revista, pedidos privados, transações em MAIN |
| F8 — LMS reservado | Piloto por curso e atividade/contexto | Dois cursos e usuários de acesso distinto; zero exposição por rota, lista, busca, cache, perfil, arquivo ou e-mail |
| F9 — Fóruns restritos (associados e cursos) | Visibilidade por fórum e por tópico: público, e-mail confirmado, associado ou contexto de curso; aplicada em acesso, listagens, busca, feeds, sitemap, Revista, contagens, último autor, notificações e cache. Antes do piloto F8. | **Proposta, não iniciada** (2026-10-16). Pré-requisitos: critério de associado definido e versionado (papel `associado` existe no Runtime sem permissões e sem regra de concessão, e não está em `config/sync`); modelo de visibilidade decidido (ver `DECISOES.md`, F-D14). Aceite: zero vazamento de título, trecho, contagem, autor, URL ou índice para visitante e para conta sem o papel; teste com contas de cada papel nos dois modos de cor e em mobile; `/forum` público continua igual para conteúdo público |

A F9 (fóruns restritos) deve entrar antes do piloto F8: os fóruns de curso dependem do mesmo controle de acesso. Fases extensas podem ser divididas em subfases com PR próprio. A ordem entre UX e
lotes pode ser intercalada, mantendo o Runtime testável. O acervo público não
depende da conclusão da integração LMS. Gamificação depende do contrato do Game,
sem conceder conquistas durante a migração histórica.

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
