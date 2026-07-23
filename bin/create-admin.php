<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Repositories\PdoUserRepository;
use App\Support\ConfigLoader;
use App\Validation\UserValidator;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/bootstrap/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is available only from the command line.\n");
    exit(1);
}

/** @param array<string, mixed> $options */
function optionOrPrompt(array $options, string $key, string $label): string
{
    if (isset($options[$key]) && is_string($options[$key])) {
        return trim($options[$key]);
    }
    fwrite(STDOUT, $label . ': ');
    $value = fgets(STDIN);
    return $value === false ? '' : trim($value);
}

try {
    $options = getopt('', ['username:', 'email:', 'first-name:', 'last-name:', 'help']);
    if (isset($options['help'])) {
        fwrite(STDOUT, "Usage: php bin/create-admin.php --username=USERNAME --email=EMAIL --first-name=NAME --last-name=NAME\n");
        fwrite(STDOUT, "Set the password through the ADMIN_PASSWORD environment variable.\n");
        exit(0);
    }
    $password = getenv('ADMIN_PASSWORD');
    if (!is_string($password) || $password === '') {
        throw new RuntimeException('Set ADMIN_PASSWORD for this command. Avoid placing passwords in command arguments or shell history.');
    }

    $input = [
        'username' => optionOrPrompt($options, 'username', 'Username'),
        'email' => optionOrPrompt($options, 'email', 'Email'),
        'first_name' => optionOrPrompt($options, 'first-name', 'First name'),
        'last_name' => optionOrPrompt($options, 'last-name', 'Last name'),
        'role' => 'admin',
        'is_active' => '1',
        'password' => $password,
        'password_confirmation' => $password,
    ];
    $config = (new ConfigLoader(PROJECT_ROOT))->load();
    $validator = new UserValidator((int) $config->get('app.password_min_length', 12));
    $errors = $validator->validateUser($input, true);
    if ($errors !== []) {
        throw new InvalidArgumentException(implode(' ', array_values($errors)));
    }
    $repository = new PdoUserRepository(new ConnectionFactory($config));
    $username = UserValidator::normalizeUsername($input['username']);
    $email = UserValidator::normalizeEmail($input['email']);
    if ($repository->usernameExists($username)) {
        throw new InvalidArgumentException('A user with that username already exists.');
    }
    if ($repository->emailExists($email)) {
        throw new InvalidArgumentException('A user with that email address already exists.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) {
        throw new RuntimeException('Password could not be secured.');
    }
    $user = $repository->create([
        'username' => $username,
        'email' => $email,
        'password_hash' => $hash,
        'first_name' => trim($input['first_name']),
        'last_name' => trim($input['last_name']),
        'role' => 'admin',
        'is_active' => true,
    ]);
    fwrite(STDOUT, sprintf("Administrator #%d created as %s.\n", $user->id, $user->username));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Administrator creation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
