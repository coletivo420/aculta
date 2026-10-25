# Barreira de horários econômicos — DeepSeek API

Fonte oficial: https://api-docs.deepseek.com/quick_start/pricing/

**Política vigente na consulta de 2026-10-21:** pico de segunda a sexta-feira, **01:00–04:00 e 06:00–10:00 UTC**, excluídos feriados públicos chineses. Fora do pico, tarifas de tokens reduzidas em 50%. As regras e tarifas podem mudar: confira a fonte oficial antes de atualizar este arquivo.

## Uso no servidor Debian

1. Verifique a política e o relógio: `python3 scripts/deepseek-offpeak.py check`. Código 0 = liberado; 75 = bloqueado.
2. Inicie a barreira local em terminal separado: `python3 scripts/deepseek-offpeak.py proxy --port 18763`.
3. Configure **apenas os perfis DeepSeek** do Claude Code ou Codex para usarem o endereço base local `http://127.0.0.1:18763`, ajustando o caminho `/anthropic` no caso da compatibilidade Anthropic. Não modifique perfis Anthropic/OpenAI normais. Preserve chaves apenas no ambiente/gerenciador de segredos.
4. Opcional: bloqueie também a abertura do processo com `python3 scripts/deepseek-offpeak.py run -- COMANDO`. **Isso sozinho não bloqueia chamadas posteriores.**
5. Execute os testes: `python3 -m unittest discover -s tests/agent -p 'test_deepseek_offpeak.py'`.

**Atenção:** a política só vale para solicitações roteadas pelo proxy local. Não há interceptação automática de chamadas diretas à DeepSeek. O proxy não é compatível com todos os recursos de cliente (por exemplo, upgrades de conexão e transferência de requisição em chunks), e respostas já iniciadas podem cruzar a fronteira de pico; o servidor da API determina a tarifa efetiva. O proxy encerra o envio de chunks ao cliente quando detecta pico, mas isso **não garante** impedir faturamento de uma chamada já recebida pelo provedor. Para política estrita de custo, pare as sessões antes da janela de pico e use isolamento de rede para impedir saídas diretas; isso depende de configuração operacional externa ao Git.

O proxy escuta **somente localhost**, não registra cabeçalhos nem corpos e rejeita novas solicitações durante o pico com HTTP 429. Não exponha a porta publicamente.

## Feriados chineses

O programa é conservador: se nenhum calendário for informado, aplica bloqueios de dias úteis mesmo em feriados. Se o calendário oficial for verificado, forneça `DEEPSEEK_CN_HOLIDAYS=AAAA-MM-DD,AAAA-MM-DD`. Não suponha datas nem utilize calendário não verificado. Essa variável permite liberar dias informados; não é necessário configurá-la para impedir cobranças no pico.

## Horário de Brasília (UTC−3)

Pico: 22:00–01:00 e 03:00–07:00, **classificando os dias em UTC**, não pelo dia local. O primeiro intervalo atravessa a meia-noite em Brasília.

## Limitações de implantação

Este commit entrega o código e os testes; **não instala** serviços systemd, não altera credenciais, nem modifica automaticamente os perfis dos agentes no servidor. O operador deve apontar o endpoint DeepSeek para a barreira. Sem isso, a proteção não está ativa.
