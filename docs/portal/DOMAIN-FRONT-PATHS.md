# Caminhos de raiz dos subdomínios (comportamento padrão)

Regra uniforme, implementada em `src/Domain/FrontPathPolicy.php` e aplicada por `DomainPurposeRequestSubscriber`:

1. A raiz de cada subdomínio serve o conteúdo da sua finalidade (`PurposeFrontPathProcessor::FRONT_PATHS`).
2. O caminho interno da raiz (ex.: `/apoio`, `/wiki`, `/cursos`, `/loja-da-aculta`, `/forum`, `/redes-sociais`) **não é público**. Requisição direta responde **404**, em qualquer método, sem redirecionamento 301.
3. Caminho de outra finalidade responde 404 (wrong-purpose público, ver `ADMIN-DOMAIN-POLICY.md`).
4. Redirecionamentos 301 ficam só para caminhos legados listados abaixo. Não se cria 301 para slug novo.

## Matriz esperada (Runtime de testes)

| Host | `/` | Caminho interno (`/apoio`, `/wiki`, ...) | Observação |
| --- | --- | --- | --- |
| `apoio.aculta.toca.net.br` | 200 (formulário de apoio) | 404 | Escolha de valor no apoio; finalização no domínio principal |
| `cursos.aculta.toca.net.br` | 200 (cursos) | 404 | |
| `wiki420.aculta.toca.net.br` | 200 (wiki) | 404 | |
| `revista420.aculta.toca.net.br` | 200 | 404 | |
| `loja.aculta.toca.net.br` | **404: pendência de conteúdo** | 404 | A vitrine (nó 69, alias `/loja-da-aculta`) não existe no Runtime de testes. Decisão do responsável: criar o conteúdo |
| `coletivo420.aculta.toca.net.br` | não resolve no servidor de testes | — | Pendência de ambiente |
| `aculta.toca.net.br` (principal) | 200 | 404 para `/apoio` (não é finalidade do principal) | Carrinho e checkout pertencem ao principal |
| `conta.aculta.toca.net.br` | 302 para `/entrar` (anônimo) ou visão geral (logado) | `/dashboard` direto: 404 | |

## Redirecionamentos 301 permitidos (legado)
- `/minha-conta` e subcaminhos → conta, sem prefixo (`DomainPurposeRequestSubscriber`).
- Caminhos antigos do checkout `/checkout/{pedido}/{etapa}` → `/finalizar-compra/...` (`DomainPurposeRequestSubscriber`).
- `/pagamentos` → `/pedidos` (`DomainRouteSubscriber`).

Qualquer outro 301 novo é regressão, a menos que esta lista seja atualizada com justificativa.

## Gate anti-regressão
`php scripts/validate-domain-front-paths.php` (estático, sem rede). Com `--live`, também consulta os hosts do Runtime e compara com a matriz acima.
