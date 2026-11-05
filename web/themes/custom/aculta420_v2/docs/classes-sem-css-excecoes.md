# Exceções de classes mapeadas sem CSS

`scripts/validate-aculta420-mapped-classes.php` exige que toda classe de
`PortalUiHooks::TOKENS` tenha uma regra CSS no tema ou uma exceção aqui.

Formato: uma linha por classe, começando com hífen, o nome entre crases e um
motivo após um travessão. O gate só aceita linhas nesse formato.

Use exceção apenas para classe que existe como gancho de comportamento, nunca
para esconder falta de estilo. Estado em 2026-11-05: nenhuma exceção.
