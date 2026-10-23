# Minutas dos Termos de Uso e da Política de Privacidade

Cópias para leitura e revisão no GitHub. **Minutas, não publicadas.** A fonte única do texto é o HTML em
`scripts/institution/` (o instalador `scripts/install-institution.php` publica a Política a partir dele). As cópias
em Markdown são geradas por `python3 -I scripts/compliance/render-minutas.py`; não edite os `.md` à mão.

| Documento | Fonte | Cópia | Versão |
|---|---|---|---|
| Termos de Uso | `scripts/institution/termos-de-uso.html` | [TERMOS-DE-USO.md](TERMOS-DE-USO.md) | 0.3 (minuta, 22/10/2026) |
| Política de Privacidade | `scripts/institution/politica-de-privacidade.html` | [POLITICA-DE-PRIVACIDADE.md](POLITICA-DE-PRIVACIDADE.md) | 0.4 (minuta, 22/10/2026) |

## O que mudou nesta revisão

- **Termos 0.3:** nova seção sobre apoios, doações, assinaturas, cursos e compras, com a regra de que doações, apoios,
  assinaturas e cursos **não têm estorno** (aplicada pelo gateway; ver `docs/portal/PAYMENT-DOMAIN-POLICY.md`).
- **Política 0.4:** estado das assinaturas e regra de não estorno nos dados de pagamento; cláusulas do
  [rascunho de 2026-10-21](../CLAUSULAS-POLITICA-ACEITE-UNICO.md) sobre progresso nos cursos, acesso a arquivos de
  mídia e exclusão da conta; novo prazo de retenção de 90 dias para esses registros.

## Pendências antes de publicar

1. **Revisão jurídica.** Em especial a regra de não estorno para **cursos**: o art. 49 do Código de Defesa do
   Consumidor prevê direito de arrependimento em compras feitas pela internet, e a exclusão pode não ser aceita.
2. **Exceções ao não estorno** (cobrança em duplicidade, valor diferente do escolhido, uso não autorizado do meio de
   pagamento, falha de acesso ao curso): decidir se entram no texto.
3. **Canal de cancelamento da assinatura** e **regras de devolução da loja** (prazo, procedimento, frete).
4. **Encarregado pelo tratamento de dados** (razão social) e **país de tratamento** de cada prestador (itens entre
   colchetes já existentes na Política).
5. **Publicar a Política** liga as funções que dependem dela (ver `docs/portal/ACEITE-UNICO.md`). Publicar novos
   textos pode exigir novo aceite dos usuários; confirmar o efeito no ledger antes de publicar em produção.
6. **Cookies de visitante** (banner do `aculta_statistics`): a Política afirma que não há analytics; conferir a
   coerência antes de publicar.
