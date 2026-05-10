<?php declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$_SERVER['KERNEL_CLASS'] ??= App\Kernel::class;
$_ENV['KERNEL_CLASS']    ??= App\Kernel::class;
