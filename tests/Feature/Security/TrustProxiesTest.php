<?php

use App\Http\Middleware\TrustProxies;

it('TrustProxies does not use wildcard proxies', function () {
    $reflection = new ReflectionClass(TrustProxies::class);

    if ($reflection->hasProperty('proxies')) {
        $property = $reflection->getProperty('proxies');
        $property->setAccessible(true);
        $value = $property->getValue(new TrustProxies());

        expect($value)->not->toBe('*');
    } else {
        $this->markTestIncomplete('TrustProxies::$proxies not found — verify bootstrap/app.php trustProxies is not "*"');
    }
});
