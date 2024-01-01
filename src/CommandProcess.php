<?php
declare(strict_types=1);

namespace PounceTech;

use PounceTech\Models\ResourceRecord;
use PounceTech\Providers\OnlineClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\PathException;
use Symfony\Contracts\HttpClient\Exception\{
    ClientExceptionInterface,
    DecodingExceptionInterface,
    RedirectionExceptionInterface,
    ServerExceptionInterface,
    TransportExceptionInterface
};

class CommandProcess
{
    public const API_ENV_NAME = 'ONLINE_API_TOKEN';
    public const DEFAULT_TTL = 300; // 300 seconds
    public const CERTBOT_ENV = [
        'CERTBOT_DOMAIN',
        'CERTBOT_VALIDATION',
    ];
    public const ADDITIONAL_ENV = [
        'CERTBOT_AUTH_OUTPUT',
        'CHALLENGE_TTL',
        'GET_TRACES'
    ];
    public const CHALLENGE_RECORD_NAME = '_acme-challenge';
    protected array $loadedEnv = [];

    protected SymfonyStyle $ioStyle;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \RuntimeException when required environment variables are not provided
     */
    public function __construct(
        protected InputInterface $input,
        protected OutputInterface $output
    ) {
        $this->ioStyle = new SymfonyStyle($this->input, $this->output);


        $dotenv = new Dotenv();
        $dotenv->usePutenv();
        $currentDir = __DIR__;
        if (str_starts_with($currentDir, 'phar://')) {
            $currentDir = dirname(substr($currentDir, 7, stripos($currentDir, '.phar') - 2));
        }

        // Look for .env files in user's home, then in the upper dir (typical dev environment layout),
        // then in current dir (typical prod environment) and finally in current working directory.
        $dotenvFiles = [
            (getenv('HOME', true) ?: getenv('HOME')) . DIRECTORY_SEPARATOR . '.env',
            $currentDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env',
            $currentDir . DIRECTORY_SEPARATOR . '.env',
            getcwd() . DIRECTORY_SEPARATOR . '.env'
        ];
        foreach ($dotenvFiles as $dotenvFile) {
            if (file_exists($dotenvFile)) {
                try {
                    $dotenv->load($dotenvFile);
                } catch (PathException) {
                }
            }
        }

        foreach (self::CERTBOT_ENV as $item) {
            $value = getenv($item, true) ?: getenv($item);
            if (empty($value)) {
                throw new \RuntimeException('Missing required environment variable: ' . $item);
            }
            $this->loadedEnv[$item] = $value;
        }
        foreach (self::ADDITIONAL_ENV as $item) {
            $this->loadedEnv[$item] = getenv($item, true) ?: getenv($item);
        }
        $token = getenv(self::API_ENV_NAME, true) ?: getenv(self::API_ENV_NAME);
        if (empty($token)) {
            throw new \RuntimeException('Missing required environment variable: ' . self::API_ENV_NAME);
        }
        $this->loadedEnv[self::API_ENV_NAME] = $token;
    }

    /**
     * Start the command
     * @return int CLI return code; 0 means the call succeeded, > 0 if an error happened
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function process(): int
    {
        $client = OnlineClient::getInstance($this->loadedEnv[self::API_ENV_NAME], (bool)$this->loadedEnv['GET_TRACES']);
        $record = new ResourceRecord();
        $record->name = self::CHALLENGE_RECORD_NAME;
        $record->type = 'TXT';
        $record->ttl = (int)($this->loadedEnv['CHALLENGE_TTL'] ?? self::DEFAULT_TTL);
        $record->data = $this->loadedEnv['CERTBOT_VALIDATION'];

        if (!isset($this->loadedEnv['CERTBOT_AUTH_OUTPUT'])) { // hook called to create the challenge RR
            try {
                $client->addRecord($this->loadedEnv['CERTBOT_DOMAIN'], $record);
            } catch (\Exception $exception) {
                $this->ioStyle->getErrorStyle()->error("Failed creating challenge record for domain {$this->loadedEnv['CERTBOT_DOMAIN']}. Error: {$exception->getMessage()}");
                $this->dumpTrace($client);
                return 1;
            }
        } else { // hook called to perform a cleanup
            try {
                $client->deleteRecord($this->loadedEnv['CERTBOT_DOMAIN'], $record);
            } catch (\Exception $exception) {
                $this->ioStyle->getErrorStyle()->error("Failed performing cleanup for domain {$this->loadedEnv['CERTBOT_DOMAIN']}. Error: {$exception->getMessage()}");
                $this->dumpTrace($client);
                return 2;
            }
        }

        $this->dumpTrace($client);
        return 0;
    }

    private function dumpTrace(OnlineClient $client): void
    {
        if ($this->loadedEnv['GET_TRACES']) {
            $this->ioStyle->info(implode("\n", $client->getTraces()));
        }
    }
}