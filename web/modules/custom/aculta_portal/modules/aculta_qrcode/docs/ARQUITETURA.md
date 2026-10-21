# Arquitetura proposta — ACULTA QR Code

Estado em 2026-10-20 (0.1.0-dev.1). **Proposta.** Nada aqui está implementado. Os nomes de serviços, entidades e rotas podem mudar quando o código for escrito. A auditoria que motiva estas decisões está em [AUDITORIA.md](AUDITORIA.md); a licença, em [LICENSING.md](LICENSING.md).

## Princípios

1. **Código no Portal, apresentação no ACULTA420.** O módulo gera dados, URLs, acesso e cache. Os componentes visuais ficam no tema (ver `docs/portal/FRONTEIRA-MODULO-TEMA.md`).
2. **Um motor de codificação.** `endroid/qr-code` (já no `composer.lock`). Nenhum segundo motor é adicionado.
3. **Sem armazenamento redundante.** SVG e PNG são gerados a partir do conteúdo e do preset. Não se grava arquivo por mudança visual.
4. **Estático sem rastreio.** QR estático codifica o conteúdo diretamente. Não há registro de leitura, porque a leitura não passa pelo servidor.
5. **Dinâmico com URL estável.** QR dinâmico codifica uma URL do ACULTA, resolvida no servidor. Mudar o destino não exige reimprimir o código.
6. **Sem host fixo.** Toda URL absoluta vem de `DomainPurposeManager` e do mapeamento do `aculta_deployer` (`docs/ENDERECOS-AMBIENTE.md`). Nenhum hostname entra no código.
7. **Sem dependência de serviço externo.** Nenhum conteúdo de QR sai do servidor.
8. **Sem autenticação paralela.** Usuários, arquivos e permissões são os do Drupal. O código dinâmico não é mecanismo de autorização.

## Serviços

Nomes em `aculta_qrcode.services.yml`, com injeção de dependência e sem `\Drupal::` em `src/`.

| Serviço | Responsabilidade | Dependências previstas |
| --- | --- | --- |
| `aculta_qrcode.generator` | Codificação e renderização de SVG e PNG a partir de payload e preset. Não grava. | Endroid. |
| `aculta_qrcode.preset` | Presets visuais (cores, margem, correção de erro, moldura, legenda) com validação de contraste e legibilidade. | Config. |
| `aculta_qrcode.resolver` | Monta URLs canônicas de entidades e de códigos dinâmicos. | `DomainPurposeManager`, `entity_type.manager`. |
| `aculta_qrcode.manager` | Criação, recuperação, atualização e desativação de QR dinâmicos. | Entity API, `resolver`. |
| `aculta_qrcode.redirect` | Resolve o código dinâmico e responde com redirecionamento. | `manager`, `TrustedRedirectResponse`. |
| `aculta_qrcode.export` | Exportação SVG, PNG, WebP e PDF (PDF pela decisão 1). | `generator`, decisão de arquivo (decisão 3). |
| `aculta_qrcode.analytics` | Contagem agregada de acessos ao redirecionamento. | Decisão 4. Não existe em 0.1.0. |

## Modelo de dados (proposta)

- Entidade `aculta_qr_code` (machine name com até 32 caracteres, conforme a regra do Portal). Campos: rótulo, modo (`dynamic`), destino (`link` ou URL interna), código opaco, preset, estado (publicado/desativado), validade (início e fim), proprietário, timestamps.
- Código opaco: identificador aleatório não sequencial e único. Não é reutilizado. A aparência não entra no identificador.
- Campos com dado pessoal: nenhum na fundação. O destino pode conter dado pessoal digitado por quem administra; a orientação ao editor precisa existir, e qualquer campo novo entra no `docs/compliance/LGPD-INVENTARIO.md` antes da criação.
- Histórico administrativo: revisões da entidade (`revisionable`), com o mesmo padrão de `aculta_links`.
- Estatísticas: tabela agregada por código e dia, sem IP, user agent completo ou identificador de pessoa. Só entra depois da decisão 4.

## Acesso

- Permissões próprias, por operação: uso (gerar QR estático), administração (criar e alterar dinâmicos), exclusão e estatísticas. Nenhum papel recebe permissão por nome; a concessão segue `docs/portal/PAPEIS-PARTICIPACAO.md`.
- Entity Access para cada código dinâmico, aplicado no painel e no redirecionamento. Um consumidor que pode gerar QR não ganha permissão para alterar outro código.
- Operações de alteração usam CSRF e formulários da Form API. Nenhuma alteração ocorre por GET.
- Rotas administrativas no purpose `main` (`docs/portal/ADMIN-DOMAIN-POLICY.md`). Escrita em purpose errado falha fechada; não há redirecionamento de escrita entre hosts.

## Domínio e URLs

- O QR dinâmico codifica uma URL do purpose público definido pelo responsável. A URL é gerada por `DomainPurposeManager`, nunca concatenada.
- O redirecionamento usa `TrustedRedirectResponse` (ver a documentação do Core para `TrustedRedirectResponse`), via `DomainPurposeManager`. Não se usa `RedirectResponse` cru entre hosts.
- Ambientes de teste e produção têm hosts diferentes; o endereço do purpose é declarado em `aculta_deployer` (`environments` e `environment_bound`).
- Caminho público do código dinâmico: ver decisão 2.
- Respostas do redirecionamento usam `Cache-Control` restritivo e cache tags do código, do destino e do Domain. Alterar destino, desativar ou expirar invalida o cache.

## Cache

- Imagem gerada por rota pública: cache tags do código, do preset e do Domain; contexto `url.path` e `user.permissions` só quando a resposta depender deles.
- Preset: cache tag da configuração do preset.
- Entidade de origem (QR de entidade): cache tag da entidade. Ao alterar a URL canônica, a tag da entidade invalida o QR.
- Nenhuma resposta com dado privado recebe cache público.

## Segurança

- Só `http` e `https` são aceitos em destinos. Protocolos `javascript:`, `data:` e `file:` são rejeitados na validação.
- Redirecionamento aponta apenas para URL validada no momento da gravação e na resolução.
- Logo: somente arquivo enviado e validado pelo `aculta_image` (regra de imagens do `AGENTS.md`). Não se aceita URL remota (ver achado de SSRF em `AUDITORIA.md`).
- SVG é gerado pela biblioteca a partir de dados sem marcação. Legendas são texto escapado; não há HTML livre.
- Rate limit em geração em massa e exportação; anti-enumeração de códigos privados (resposta 404 uniforme).
- Dados de acesso ao redirecionamento: retenção definida na decisão 4.

## Integração com os consumidores

- `aculta_links`: mantém as rotas `/pagina/{slug}/codigo-qr.svg` e `/redirecionar/{codigo}/codigo-qr.svg` até a 0.7.0. A migração para o serviço central ocorre só com liberação do escopo, sem duplicar a geração.
- `aculta_lms` (F11): a verificação pública do certificado usa o QR do serviço central. Enquanto esse serviço não existir, a integração permanece pendente naquele módulo.
- `aculta_image`: arquivos exportados e logos passam por ele.
- Consumidores dependem da API pública do serviço, não de classes internas.

## Decisões pendentes do responsável

1. **Motor de PDF.** Opções: (a) `setasign/fpdf` pelo writer do Endroid, nova dependência; (b) Entity Print/Dompdf, já presente no `composer.json` do trabalho em andamento do LMS. Recomendação: uma única solução PDF no ACULTA, decidida antes de 0.4.0, e não duas.
2. **Caminho público do código dinâmico.** A proposta usa `/q/{codigo}`, que não segue a regra de slugs em português (`docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`). Sugestão: `/qr/{codigo}` ou `/codigo-qr/{codigo}`. A escolha precisa constar no mapa de rotas (`docs/architecture/ROUTES-SITEMAP.md`) antes da 0.3.0.
3. **Entrega de arquivos exportados.** PNG, WebP e PDF exportados são arquivos de imagem: por regra, passam pelo `aculta_image`. Alternativa: stream direto, sem gravação. Recomendação: stream, sem gravar.
4. **Analytics.** Quais métricas são aceitáveis (sugestão: contagem diária por código, sem IP nem user agent) e prazo de retenção. Entra no inventário LGPD antes da 0.6.0.
5. **Escopo de lote.** Se a geração em lote usa Batch API (recomendado para coleções) ou Queue API.

## Validação prevista

- Testes Unit do `generator` e dos `presets` (contraste, margens, rejeição de protocolos).
- Kernel do `manager`, `resolver` e `redirect`: destino, validade, desativação, Entity Access e cache.
- Gate: `php scripts/validate-aculta-portal-drupal11.php` e `php scripts/validate-module-theme-boundary.php` quando houver código.
