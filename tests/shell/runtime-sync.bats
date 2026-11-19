#!/usr/bin/env bats
# Testes de isolamento do scripts/runtime-sync.sh (skill aculta-shell-qualidade).
# Regras: fixtures em diretório temporário; nenhum Drush, banco ou Runtime real;
# nenhum drush cr, migração ou config import. Não substitui scripts/test-runtime-sync.sh.

setup() {
	REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
	SCRIPT="$REPO_ROOT/scripts/runtime-sync.sh"
}

@test "sintaxe do entrypoint é válida" {
	run bash -n "$SCRIPT"
	[ "$status" -eq 0 ]
}

@test "help imprime uso e sai com 0" {
	run bash "$SCRIPT" help
	[ "$status" -eq 0 ]
	[[ "$output" == *"Uso: bash scripts/runtime-sync.sh"* ]]
}

@test "--help equivale a help" {
	run bash "$SCRIPT" --help
	[ "$status" -eq 0 ]
	[[ "$output" == *"Comandos disponiveis"* ]]
}

@test "comando desconhecido sai com 2 e mostra uso na saída de erro" {
	run bash "$SCRIPT" comando-inexistente
	[ "$status" -eq 2 ]
	[[ "$output" == *"Uso:"* ]]
}

@test "plan recusa checkout fora da branch dev sem alterar nada" {
	work="$BATS_TEST_TMPDIR/repo"
	mkdir -p "$work/scripts"
	cp -r "$REPO_ROOT/scripts/runtime-sync" "$REPO_ROOT/scripts/runtime-sync.sh" "$work/scripts/"
	git -C "$work" init -q
	git -C "$work" checkout -q -b feature/teste
	git -C "$work" -c user.email=t@t -c user.name=t commit -q --allow-empty -m init
	before="$(git -C "$work" rev-parse HEAD)"
	run bash -c "cd '$work' && bash scripts/runtime-sync.sh plan"
	[ "$status" -ne 0 ]
	[[ "$output" == *"branch dev"* ]]
	[ "$(git -C "$work" rev-parse HEAD)" = "$before" ]
}
