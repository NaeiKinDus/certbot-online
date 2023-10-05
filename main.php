#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use PounceTech\CommandProcess;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

try {
    $selfPhar = new Phar(Phar::running(false));
    $metadata = $selfPhar->getMetadata();
    if (empty($metadata['version'])) {
        $metadata['version'] = '@git@';
    }
} catch (\Exception) {
    // Most likely not in a phar archive
    $metadata = [
        'name' => 'certbot-online',
    ];
}
if (empty($metadata['version']) || $metadata['version'] === '@git@') {
    $metadata['version'] = 'unknown';
}

try {
    $retCode = (new SingleCommandApplication())
        ->setName($metadata['name'] ?? 'certbot-online')
        ->setDescription('A certbot hook generating TXT challenge records using Online.net API')
        ->setVersion($metadata['version'])
        ->setCode(
            fn(InputInterface $input, OutputInterface $output) => (new CommandProcess($input, $output))->process()
        )
        ->run();
} catch (\Exception) {
    return 1;
}

return $retCode;
