<?php

/**
 * Design D8 / spec Scenario 5.1.f: none of the four auth-event listeners may
 * introduce an email/Slack/webhook (or any other outbound notification) side
 * effect — this change is log-only.
 *
 * Deliberately framework-free (no Tests\TestCase / no DB): pure file-content
 * assertions, so this guard runs even when the test database is unreachable.
 */
function authListenerPath(string $relative): string
{
    return dirname(__DIR__, 3).'/app/Listeners/Auth/'.$relative;
}

it('no auth-event listener sends mail, notifications, or outbound HTTP', function () {
    $files = [
        authListenerPath('LogSuccessfulLogin.php'),
        authListenerPath('LogFailedLogin.php'),
        authListenerPath('LogSuccessfulLogout.php'),
        authListenerPath('LogPasswordReset.php'),
    ];

    $forbidden = [
        'Mail::',
        'Notification::',
        '->notify(',
        'Http::',
        'SlackMessage',
        'WebhookClient',
        'Pusher::',
        'Broadcast::',
    ];

    foreach ($files as $file) {
        expect($file)->toBeFile();

        $contents = file_get_contents($file);

        foreach ($forbidden as $needle) {
            expect($contents)->not->toContain($needle);
        }
    }
});
