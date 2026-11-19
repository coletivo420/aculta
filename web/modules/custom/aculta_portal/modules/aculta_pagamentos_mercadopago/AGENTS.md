# aculta_pagamentos_mercadopago — regras locais

- **O prefixo do token não identifica o ambiente.** Credencial de teste também pode começar com `APP_USR-`. Não decida ambiente por `TEST-` nem por `APP_USR-`.
- **Decida pelo modo configurado e pelo `live_mode` do pagamento.** Esses dois valores determinam se a operação é de teste ou real.
