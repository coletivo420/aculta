# ACULTA Superfícies

Submódulo opt-in do `aculta_portal`, para controlar cada superfície pública do site (Principal, Conta, Apoio, Revista, Wiki, Loja, Cursos, Fórum, Redes sociais, Eventos):

- ativar ou desativar a superfície;
- escolher o modo: `subdominio`, `diretorio` ou `desativado`;
- definir o slug em português, usado no modo `diretorio`.

**Status:** Dev `0.1.0-dev.22`. Formulário, tabela, modal AJAX, validação de endereços e status DNS foram validados no Runtime com ACULTA420. A resolução de rotas continua atrás do interruptor `leitura_ativa`; `/slug` no MAIN segue DEFERRED.

## O que este submódulo faz

- Formulário administrativo em `/painel-administrativo/aculta/caminhos`, exclusivo do purpose `main`, com tabela somente leitura dos caminhos públicos.
- Validações: slug segue `FRIENDLY-PORTUGUESE-SLUGS`; dois purposes não podem ocupar o mesmo par host + caminho; superfícies protegidas não podem ser desativadas.
- Sincronização dos registros do módulo Domain a partir da configuração.
- Aviso de impacto antes de salvar.

## O que este submódulo NÃO faz

- **Não resolve rotas.** O resolvedor de superfície fica no núcleo (`aculta_portal`), para que o site funcione com este submódulo desligado, usando os padrões atuais.
- **Não guarda hosts.** Os hosts vêm do módulo Domain e de variável de ambiente via Drupal Key.
- **Não decide permissões de acesso.** Essas regras continuam em `DomainRoutePolicy` e no subscriber do núcleo.

## Superfícies protegidas

Estas superfícies não podem ser desativadas nem ter o modo alterado para `desativado`:

- `main` (principal, painel administrativo, carrinho, checkout e pagamento);

## Riscos que o formulário precisa mostrar

- **Sessão compartilhada.** Todos os subdomínios da mesma raiz compartilham o cookie de sessão. Mudar o modo de uma superfície altera o limite de confiança entre elas.
- **Slug antes da versão estável.** Trocar o slug faz o caminho antigo responder 404, sem redirect, como decidido pelo responsável.
- **Conteúdo existente.** Desativar uma superfície deixa seu conteúdo fora do ar. Nenhum dado é apagado.

## Dependências

- `aculta_portal` (núcleo: resolvedor, configuração padrão e mapas de purpose).
- `domain` (hosts, aliases de ambiente e registros de domínio).

### Compatibilidade com Domain 4

Revisado com Domain 4.0.0-alpha2: `DomainInterface::getHostname()` e
`setHostname()` e a API de entidades usadas pelo formulário continuam
disponíveis. O Domain 4 também permite vários registros no mesmo host usando
`path_prefix`; `aculta_paths` não usa essa resolução. O modo Diretório continua
sendo responsabilidade da camada do `aculta_portal`, conforme D3. A verificação
de host repetido no formulário é intencional para superfícies em modo
Subdomínio, cujo endereço deve identificar um único Domain neste modelo.

## Ordem de instalação (quando houver implementação)

1. Núcleo com o resolvedor lendo a configuração e os padrões atuais (Fase 3.1 do plano).
2. Este submódulo, com o formulário e a sincronização.

Sem o passo 1, este submódulo não tem o que controlar.

## Documentos de referência

- `docs/PLANO-FINAL.md` (nesta pasta): plano consolidado, decisões e etapas.
- ADR de superfícies: `docs/decisions/ADR-043-superficies-dominio.md` e anexo `docs/decisions/ADR-043-anexo-decisoes-fixas.md`.
- `CHANGELOG.md` deste submódulo.

## Estado das fases (2026-11-03)

| Fase | Estado | Observação |
| --- | --- | --- |
| 3.0 Congelar comportamento | DEFERRED | Roteiro pronto; exige Runtime. |
| 3.1 Mapa único | Código | `DomainSurfaceMap`; teste de paridade escrito. |
| 3.2 Flag autoritativa | Código | Subscriber do portal preserva o purpose; teste de rota DEFERRED. |
| 3.3 Constantes de purpose | Código | `DomainSurfaceMap::MAIN` e `ACCOUNT` no subscriber. |
| 3.4 Atribuição pela configuração | Parcial | Configuração, schema, leitor e interruptor `leitura_ativa` (desligado). `SurfaceRequestSubscriber` (núcleo) já existe e só age com o interruptor ligado. Atribuição de purpose por configuração não implementada. |
| 3.5 Modo diretório | Parcial | Regra de diretório na validação; caminho em vigor só com `leitura_ativa` ligado. Resolução de `/slug` no MAIN não implementada (DEFERRED). |
| 3.6 Formulário | Código | `/painel-administrativo/aculta/caminhos`, só MAIN. Lista com Módulo ACULTA, Nome do Site, Status do DNS e Editar (modal). Modo troca o campo de caminho por AJAX: diretório pede o slug; subdomínio pede o endereço, gravado no Domain, com confirmação. |
| 3.7 Exportação | Parcial | YAML em `config/sync`; conferência de importação DEFERRED. |

## Validação e DNS (0.1.0-dev.20)

- `Validation\EnderecoValidator`: recusa caracteres inválidos e diz quais são (ex.: «:», «/», «espaço»); recusa formato de host e de slug. Maiúsculas são convertidas para minúsculas antes da validação.
- `Service\DnsClassificador`: classifica o DNS do subdomínio em relação ao principal (CNAME para o principal ou IP em comum = OK). Pura, sem rede.
- `Service\DnsStatusChecker` (serviço `aculta_paths.dns_status`): consulta o DNS e usa cache de 5 minutos. Diretório mostra "Modo Diretório, Não Aplicável".

Todos os testes escritos ainda não foram executados. Testes novos: `EnderecoValidatorTest` e `DnsClassificadorTest`, sem vendor/ nesta sessão.
