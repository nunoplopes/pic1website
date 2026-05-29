<?php

namespace Github;

/*
 * Loaded through auto_prepend_file inside the PHP web-server process. This
 * must define Github\Client before Composer loads the real client; regular
 * test mocks only replace globals in the PHPUnit process.
 */
if (!class_exists(Client::class, false)) {
    class FakeUserApi
    {
        public function show(string $username): array
        {
            return [
                'login' => $username,
                'id' => 1000,
                'name' => ucwords(str_replace(['-', '_'], ' ', $username)),
                'email' => $username . '@example.com',
                'company' => 'Open Source Lab',
                'location' => 'Lisbon, Portugal',
            ];
        }
    }

    class Client
    {
        public function __construct(...$args)
        {
        }

        public function authenticate(...$args): void
        {
        }

        public function api(string $endpoint): object
        {
            if ($endpoint === 'user') {
                return new FakeUserApi();
            }

            return new class {
            };
        }
    }
}
