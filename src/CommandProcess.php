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
    public const CHALLENGE_RECORD_NAME = '_acme-challenge';

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
        try {
            $dotenvFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env';
            if (file_exists($dotenvFile)) {
                $dotenv->load($dotenvFile);
            }
        } catch (PathException) {
        }

        foreach (self::CERTBOT_ENV as $item ) {
            if (!isset($_ENV[$item])) {
                throw new \RuntimeException('Missing required environment variable: ' . $item);
            }
        }
        if (empty($_ENV[self::API_ENV_NAME])) {
            throw new \RuntimeException('Missing required environment variable: ' . self::API_ENV_NAME);
        }
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
        $client = OnlineClient::getInstance($_ENV[self::API_ENV_NAME]);
        $record = new ResourceRecord();
        $record->name = self::CHALLENGE_RECORD_NAME;
        $record->type = 'TXT';
        $record->ttl = (int)($_ENV['CHALLENGE_TTL'] ?? self::DEFAULT_TTL);
        $record->data = $_ENV['CERTBOT_VALIDATION'];

        if (!isset($_ENV['CERTBOT_AUTH_OUTPUT'])) { // hook called to create the challenge RR
            try {
                $client->addRecord($_ENV['CERTBOT_DOMAIN'], $record);
            } catch (\Exception $exception) {
                $this->ioStyle->getErrorStyle()->error("Failed creating challenge record for domain {$_ENV['CERTBOT_DOMAIN']}. Error: {$exception->getMessage()}");
                return 1;
            }
        } else { // hook called to perform a cleanup
            try {
                $client->deleteRecord($_ENV['CERTBOT_DOMAIN'], $record);
            } catch (\Exception $exception) {
                $this->ioStyle->getErrorStyle()->error("Failed performing cleanup for domain {$_ENV['CERTBOT_DOMAIN']}. Error: {$exception->getMessage()}");
                return 2;
            }
        }

        return 0;
    }
}