<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Password;

require dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'vendor'
    . DIRECTORY_SEPARATOR
    . 'autoload.php';

/*
|--------------------------------------------------------------------------
| Segurança
|--------------------------------------------------------------------------
|
| Script exclusivo para execução em CLI.
|
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);

$pdo = null;

try {
    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    */

    Env::load($basePath);

    /*
    |--------------------------------------------------------------------------
    | Nome e e-mail
    |--------------------------------------------------------------------------
    */

    $name = isset($argv[1])
        ? trim((string) $argv[1])
        : '';

    $email = isset($argv[2])
        ? mb_strtolower(
            trim((string) $argv[2]),
            'UTF-8'
        )
        : '';

    if ($name === '') {
        throw new \RuntimeException(
            'Nome obrigatório.'
        );
    }

    if (mb_strlen($name, 'UTF-8') > 150) {
        throw new \RuntimeException(
            'Nome excede o tamanho permitido.'
        );
    }

    if (
        $email === ''
        || filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        ) === false
    ) {
        throw new \RuntimeException(
            'E-mail inválido.'
        );
    }

    if (mb_strlen($email, 'UTF-8') > 255) {
        throw new \RuntimeException(
            'E-mail excede o tamanho permitido.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Senha recebida via STDIN
    |--------------------------------------------------------------------------
    */

    $password = stream_get_contents(STDIN);

    if (!is_string($password)) {
        throw new \RuntimeException(
            'Não foi possível receber a senha.'
        );
    }

    $password = rtrim(
        $password,
        "\r\n"
    );

    if ($password === '') {
        throw new \RuntimeException(
            'Senha obrigatória.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Hash Argon2id
    |--------------------------------------------------------------------------
    */

    $passwordHash = Password::hash(
        $password
    );

    /*
     * Descarta a senha em texto puro assim que possível.
     */
    $password = '';

    /*
    |--------------------------------------------------------------------------
    | Banco
    |--------------------------------------------------------------------------
    */

    $pdo = Database::connection();

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | SUPER_ADMIN
    |--------------------------------------------------------------------------
    */

    $roleStatement = $pdo->prepare(
        '
        SELECT
            id
        FROM public.roles
        WHERE codigo = :codigo
          AND ativo = TRUE
        LIMIT 1
        '
    );

    $roleStatement->execute([
        'codigo' => 'SUPER_ADMIN',
    ]);

    $role = $roleStatement->fetch();

    if (!is_array($role)) {
        throw new \RuntimeException(
            'Role SUPER_ADMIN não encontrada.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Evita e-mail duplicado
    |--------------------------------------------------------------------------
    */

    $existingStatement = $pdo->prepare(
        '
        SELECT
            id
        FROM public.usuarios
        WHERE LOWER(email) = LOWER(:email)
        LIMIT 1
        '
    );

    $existingStatement->execute([
        'email' => $email,
    ]);

    if ($existingStatement->fetch() !== false) {
        throw new \RuntimeException(
            'Já existe um usuário com este e-mail.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cria usuário
    |--------------------------------------------------------------------------
    */

    $userStatement = $pdo->prepare(
        '
        INSERT INTO public.usuarios (
            nome,
            email,
            senha_hash,
            status
        )
        VALUES (
            :nome,
            :email,
            :senha_hash,
            :status
        )
        RETURNING id
        '
    );

    $userStatement->execute([
        'nome' => $name,
        'email' => $email,
        'senha_hash' => $passwordHash,
        'status' => 'ATIVO',
    ]);

    $userId = $userStatement->fetchColumn();

    if ($userId === false) {
        throw new \RuntimeException(
            'Não foi possível obter o ID do usuário criado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Vincula role
    |--------------------------------------------------------------------------
    */

    $userRoleStatement = $pdo->prepare(
        '
        INSERT INTO public.usuario_roles (
            usuario_id,
            role_id
        )
        VALUES (
            :usuario_id,
            :role_id
        )
        '
    );

    $userRoleStatement->execute([
        'usuario_id' => (int) $userId,
        'role_id' => (int) $role['id'],
    ]);

    $pdo->commit();

    /*
     * Não exibimos hash nem outras informações sensíveis.
     */
    echo PHP_EOL;
    echo 'SUPER_ADMIN criado com sucesso.';
    echo PHP_EOL;
} catch (\Throwable $exception) {

    if (
        $pdo instanceof \PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    /*
     * Referência aleatória para diagnóstico interno.
     */
    $errorReference = bin2hex(
        random_bytes(8)
    );

    Logger::exception(
        $exception,
        $errorReference
    );

    fwrite(
        STDERR,
        PHP_EOL
            . 'Não foi possível criar o SUPER_ADMIN.'
            . PHP_EOL
            . 'Referência: '
            . $errorReference
            . PHP_EOL
    );

    exit(1);
}
