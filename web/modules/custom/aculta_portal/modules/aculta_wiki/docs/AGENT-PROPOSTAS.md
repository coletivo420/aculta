# Wiki — propostas editoriais (regra de agente)

Leia só quando a tarefa tocar propostas, revisões ou fila editorial. Regras gerais: `../AGENTS.md`.

## Propostas editoriais

- Proposta guarda campos tipados e revisão-base. A aceitação aplica um subconjunto, com modo `replace` ou `append` em cada lista, depois de revalidar revisão-base, Entity/Field Access e transições oficiais.
- Gravação da proposta e do Node na mesma transação. Nunca aplique proposta obsoleta.
- Texto sugerido é texto simples. O formulário avisa que ele não altera o verbete sozinho.
- Envio exige `submit wiki proposals`, `_entity_access: node.view` e e-mail confirmado. Limite: Flood Core, 10 propostas por conta em 3.600 segundos; exceder lança 429 antes de salvar.
- Idempotência: token por formulário, indexado por hash de conta/token em KeyValue expirável por 24 horas; lock e gravação transacional. Replay devolve a mesma proposta e não consome nova cota.
- Concorrência: decisão de proposta usa lock de banco para evitar duas decisões sobre o mesmo estado e exige VID esperado.
- Retirada: só autoria autenticada com acesso de leitura pode retirar `submitted` ou `needs_changes`. Revalide o VID sob lock, grave revisão `withdrawn` e não apague a proposta.
- Status privado exige Entity Access real no parâmetro `{proposal}`. Notificação assíncrona usa mailer opcional e só para e-mail confirmado. Não inclua texto sugerido nem dados pessoais no aviso.
- Após persistir, redirecione para o ID retornado pelo serviço de submissão. Não adivinhe identificadores.
- Histórico de revisões: página limitada por cursor, revalidando `view revision` em cada item. Não exiba nome de moderador. Cache varia por conta, permissões e cursor.
- Fila editorial: candidatos por requisição limitados, avanço por cursor pulando itens sem acesso ou obsoletos. Cada Node é revalidado no Domain Wiki. A query de VIDs de Content Moderation não tem access check porque o Core nega acesso à entidade de estado; a revalidação por Node continua obrigatória.

