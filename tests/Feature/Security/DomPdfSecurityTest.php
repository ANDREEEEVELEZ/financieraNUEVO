<?php

it('DomPDF PHP execution is disabled', function () {
    $enablePhp = config('dompdf.options.enable_php', config('dompdf.enable_php', null));
    expect((bool) $enablePhp)->toBeFalse();
});

it('DomPDF remote access is disabled', function () {
    $enableRemote = config('dompdf.options.enable_remote', config('dompdf.enable_remote', null));
    expect((bool) $enableRemote)->toBeFalse();
});
