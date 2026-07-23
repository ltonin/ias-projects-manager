<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Models\Person;
use App\Repositories\PdoPersonRepository;
use App\Repositories\PdoUserRepository;
use App\Services\PersonService;
use App\Support\ConfigLoader;
use App\Validation\PersonValidator;
use App\Validation\UserValidator;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/bootstrap/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is available only from the command line.\n");
    exit(1);
}

/** @param array<string, mixed> $options */
function personOption(array $options, string $key, string $label, bool $required = false): string
{
    if (isset($options[$key]) && is_string($options[$key])) {
        return trim($options[$key]);
    }
    if (!$required) {
        return '';
    }
    fwrite(STDOUT, $label . ': ');
    $value = fgets(STDIN);
    return $value === false ? '' : trim($value);
}

try {
    $options = getopt('', [
        'first-name:', 'last-name:', 'email::', 'affiliation::', 'position:',
        'external', 'active-from::', 'active-to::', 'inactive', 'username::', 'notes::', 'help',
    ]);
    if (isset($options['help'])) {
        fwrite(STDOUT, "Usage: php bin/create-person.php --first-name=NAME --last-name=NAME --position=TYPE [--email=EMAIL] [--affiliation=TEXT] [--external] [--active-from=YYYY-MM-DD] [--active-to=YYYY-MM-DD] [--inactive] [--username=USERNAME]\n");
        fwrite(STDOUT, 'Position types: ' . implode(', ', array_keys(Person::POSITION_LABELS)) . "\n");
        exit(0);
    }
    $config = (new ConfigLoader(PROJECT_ROOT))->load();
    $connections = new ConnectionFactory($config);
    $repository = new PdoPersonRepository($connections);
    $users = new PdoUserRepository($connections);
    $username = personOption($options, 'username', 'Username');
    $userId = '';
    if ($username !== '') {
        $user = $users->findByUsername(UserValidator::normalizeUsername($username));
        if ($user === null) {
            throw new InvalidArgumentException('The requested linked username does not exist.');
        }
        $userId = (string) $user->id;
    }
    $input = [
        'user_id' => $userId,
        'first_name' => personOption($options, 'first-name', 'First name', true),
        'last_name' => personOption($options, 'last-name', 'Last name', true),
        'institutional_email' => personOption($options, 'email', 'Institutional email'),
        'affiliation' => personOption($options, 'affiliation', 'Affiliation'),
        'position_type' => personOption($options, 'position', 'Position type', true),
        'is_internal' => isset($options['external']) ? '0' : '1',
        'active_from' => personOption($options, 'active-from', 'Active from'),
        'active_to' => personOption($options, 'active-to', 'Active to'),
        'is_active' => isset($options['inactive']) ? '0' : '1',
        'notes' => personOption($options, 'notes', 'Notes'),
    ];
    $service = new PersonService($repository, new PersonValidator());
    $errors = $service->validate($input);
    if ($errors !== []) {
        throw new InvalidArgumentException(implode(' ', array_values($errors)));
    }
    $person = $service->create($input);
    fwrite(STDOUT, sprintf("Person #%d created: %s%s.\n", $person->id, $person->fullName(), $person->linkedUsername === null ? '' : ' linked to ' . $person->linkedUsername));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Person creation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
