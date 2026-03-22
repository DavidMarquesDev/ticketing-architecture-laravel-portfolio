<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__, 6) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use App\Modules\Ticketing\Interface\Http\Policies\TicketPolicy;

$tests = [
    'ticket_policy_assign_allows_user_with_admin_role_from_get_attribute' => static function (): void {
        $policy = new TicketPolicy();
        $user = new class {
            /**
             * @return array<int, string>
             */
            public function getAttribute(string $key): array
            {
                return $key === 'roles' ? ['admin'] : [];
            }
        };

        assertTrue($policy->assign($user), 'Policy deve autorizar assign com role admin obtida por getAttribute.');
        assertTrue($policy->close($user), 'Policy deve autorizar close com role admin obtida por getAttribute.');
    },
    'ticket_policy_reply_denies_user_without_identifier' => static function (): void {
        $policy = new TicketPolicy();
        $anonymous = ['roles' => ['customer']];

        assertTrue(!$policy->reply($anonymous), 'Policy deve negar reply sem identificador de usuário.');
    },
];

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

$failures = [];

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}" . PHP_EOL;
    } catch (Throwable $throwable) {
        $failures[] = sprintf('FAIL %s: %s', $name, $throwable->getMessage());
    }
}

foreach ($failures as $failure) {
    echo $failure . PHP_EOL;
}

exit(count($failures) === 0 ? 0 : 1);
