<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    private function __construct() {}

    /**
     * Retorna a conexão com o banco central da plataforma.
     *
     * IMPORTANTE:
     * Esta conexão é exclusivamente do banco init_saas_platform.
     * Ela não deve ser utilizada futuramente para selecionar
     * bancos de tenants através de dados vindos do navegador.
     */
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configPath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'database.php';

        $config = require $configPath;

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['sslmode']
        );

        try {
            self::$connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    PDO::ATTR_EMULATE_PREPARES => false,

                    PDO::ATTR_PERSISTENT => false,
                ]
            );

            return self::$connection;
        } catch (PDOException $exception) {
            /*
             * Não exibimos detalhes da conexão para o navegador.
             * A exceção original fica encadeada para logging interno.
             */
            throw new RuntimeException(
                'Não foi possível estabelecer conexão com o banco de dados.',
                0,
                $exception
            );
        }
    }
}
