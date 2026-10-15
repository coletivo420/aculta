# Roadmap — ACULTA Fórum

Plano aprovado em 2026-10-15. F0 é documentação; F1 em diante não estão implementadas.
Dependências externas só entram na fase que comprovar sua necessidade. A PR #286
recebe esta abertura por autorização para continuar na PR existente; cada fase
funcional posterior terá PR rastreável, sem merge parcial automático ou tags.

| Fase | Entrega | Aceite e dependências |
| --- | --- | --- |
| F0 — projeto e pesquisa | README, decisões, arquitetura, reconstrução e fontes | Documentos coerentes com a sessão e links válidos; sem alteração de Runtime |
| F1 — inventário e domínio | Inventário completo CDX, corte temporal; troca do prefixo por ambiente | Cobertura mensurável; Deployer/Domain, DNS/TLS, sessão, canonical e sitemap verificados no teste; política do host antigo registrada |
| F2 — identidades históricas | Papel, Users bloqueados, memorial coletivo, nomes individuais e avatar especial | Toda autoria aponta ao coletivo; sem perfis individuais ou onboarding; contrato AvatarPool e caixas existentes preservado |
| F3 — migração piloto | Estrutura, autores, tópicos, respostas e arquivos de amostra | IDs/datas/quotes corretos, comentários fechados, reexecução sem duplicação, sem e-mails/conquistas |
| F4 — experiência atual | Home, listagens, tópico, ações sociais e conversa vinculada | Desktop/mobile, teclado, fallback sem JS, dois modos de cor; vínculo novo → histórico sem modificar original |
| F5 — participação e descoberta | Denúncias, moderação, busca e avisos de respostas | Permissões reais, publicação após aprovação, opt-in e deduplicação; limites de participação preservados |
| F6 — reconstrução em lotes | Conteúdo e mídia de todo o inventário aprovado | Cobertura reconciliada, ausências explicadas, origem rastreável; sem overwrite de dados atuais |
| F7 — Portal e loja | Revista/Wiki/projetos/galerias e discussão por produto | Uma discussão por vínculo, Galeria na Revista, pedidos privados, transações em MAIN |
| F8 — LMS reservado | Piloto por curso e atividade/contexto | Dois cursos e usuários de acesso distinto; zero exposição por rota, lista, busca, cache, perfil, arquivo ou e-mail |

Fases extensas podem ser divididas em subfases com PR próprio. A ordem entre UX e
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

Aplicar no Homelab quando o escopo funcional estiver coerente, após confirmar o
destino e os efeitos. Registrar só os testes realmente executados; casos não
executados ficam `RUNTIME STATUS: DEFERRED`. Produção e tags exigem decisão própria.

## Questões a fechar na fase adequada

Política do hostname anterior; corte temporal para captura complementar;
reivindicação futura de autoria; discussão por curso/turma; extensão de D13 de
galerias se for desejada. Nenhuma dessas questões impede F0.
