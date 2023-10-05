#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use PounceTech\CommandProcess;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

/**
 * @return string[]
 */
function getBuildInfo(): array
{
    return [
        'name' => '@app_name@',
        'description' => '@app_description@',
        'version' => '@git_version@'
    ];
}

$metadata = getBuildInfo();
try {
    $retCode = (new SingleCommandApplication())
        ->setName($metadata['name'])
        ->setDescription($metadata['description'])
        ->setVersion($metadata['version'])
        ->setCode(
            fn(InputInterface $input, OutputInterface $output) => (new CommandProcess($input, $output))->process()
        )
        ->run();
} catch (\Exception) {
    return 1;
}

return $retCode;
