<?php

/**
 * Slice 3 (PII encryption, Requirement 3.1 audit gate) — regression guard.
 *
 * `numero_cuenta_desembolso` is `encrypted` (random IV per write). Filament's
 * default `->searchable()` compiles to a raw `WHERE column LIKE '%term%'`
 * against the DB column, bypassing the Eloquent cast entirely — it would
 * silently return zero results forever once this column holds ciphertext.
 * `->searchable()` was removed from this column's TextColumn as a deliberate,
 * user-confirmed tradeoff (see apply-progress). This guard fails loudly if
 * `->searchable()` is ever reintroduced on this specific column without
 * re-running the Requirement 3.1 audit gate.
 *
 * Deliberately framework-free (no Tests\TestCase / no DB): pure file-content
 * assertion, so this guard runs even when the test database is unreachable.
 */

it('numero_cuenta_desembolso TextColumn in PrestamoResource is not searchable', function () {
    $file = dirname(__DIR__, 3).'/app/Filament/Dashboard/Resources/PrestamoResource.php';

    expect($file)->toBeFile();

    $contents = file_get_contents($file);

    preg_match(
        "/TextColumn::make\('numero_cuenta_desembolso'\)[\s\S]*?(?=::make\()/",
        $contents,
        $matches
    );

    expect($matches)->not->toBeEmpty();

    // Strip full-line `//` comments (this column carries a doc-comment that
    // mentions `->searchable()` in prose, explaining why it was removed —
    // that mention must not itself trip this guard).
    $codeOnly = collect(explode("\n", $matches[0]))
        ->reject(fn ($line) => str_starts_with(trim($line), '//'))
        ->implode("\n");

    expect($codeOnly)->not->toContain('->searchable()');
});
