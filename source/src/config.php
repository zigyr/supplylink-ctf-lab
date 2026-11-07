<?php
declare(strict_types=1);

function config_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function storage_dir(): string
{
    return rtrim(config_value('STORAGE_DIR', '/var/www/supplylink/storage/imports'), '/');
}

function import_rebind_host(): string
{
    return config_value('IMPORT_REBIND_HOST', 'cache.rebind.huanyutech.cn');
}

function jwt_key_file(): string
{
    return config_value('JWT_KEY_FILE', '/opt/supplylink/config/jwt.key');
}
