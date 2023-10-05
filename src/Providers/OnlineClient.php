<?php
declare(strict_types=1);

namespace PounceTech\Providers;

use PounceTech\Models\ResourceRecord;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\ {
    ClientExceptionInterface,
    DecodingExceptionInterface,
    RedirectionExceptionInterface,
    ServerExceptionInterface,
    TransportExceptionInterface
};
use Symfony\Contracts\HttpClient\HttpClientInterface;

enum RECORD_OPERATION: string
{
    case ADD = 'ADD';
    case UPDATE = 'REPLACE';
    case DELETE = 'DELETE';
}

class OnlineClient
{
    public const API_ENDPOINT = 'https://api.online.net/api/v1';

    /** @var OnlineClient[] */
    protected static array $instances = [];
    protected HttpClientInterface $client;

    /**
     * @throws RuntimeException when a required environment variable is missing
     */
    protected function __construct(
        #[SensitiveParameter] protected string $apiToken
    ) {
        $this->client = new RetryableHttpClient(
            HttpClient::create(
                [
                    'auth_bearer' => $this->apiToken,
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                ]
            )
        );
    }

    /**
     * @param string $apiKey
     * @return OnlineClient
     */
    public static function getInstance(#[SensitiveParameter] string $apiKey): OnlineClient
    {
        if (empty(self::$instances[$apiKey])) {
            self::$instances[$apiKey] = new OnlineClient($apiKey);
        }

        return self::$instances[$apiKey];
    }

    /**
     * Add a new record / RR set to the active version
     *
     * @param string $domainName
     * @param ResourceRecord $record
     * @param array|null $rrSet
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function addRecord(string $domainName, ResourceRecord $record, ?array $rrSet = null): void
    {
        $this->alterRecord($domainName, RECORD_OPERATION::ADD, $record, $rrSet);
    }

    /**
     * Try and delete a record
     * Note: does not fail is the record doesn't exist
     *
     * @param string $domainName
     * @param ResourceRecord $record
     * @param array|null $rrSet
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function deleteRecord(string $domainName, ResourceRecord $record, ?array $rrSet = null): void
    {
        $this->alterRecord($domainName, RECORD_OPERATION::DELETE, $record, $rrSet);
    }

    /**
     * Alter an RRSet on the active version
     *
     * @param string $domainName
     * @param RECORD_OPERATION $operationType
     * @param ResourceRecord $record
     * @param ResourceRecord[] $rrSet
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    protected function alterRecord(string $domainName, RECORD_OPERATION $operationType, ResourceRecord $record, ?array $rrSet = null): void
    {
        $payload = [
            'name' => $record->name,
            'type' => $record->type,
            'changeType' => $operationType->value,
            'records' => $rrSet ? array_merge($rrSet, [$record]) : [$record]
        ];
        $response = $this->client->request('PATCH', self::API_ENDPOINT . "/domain/$domainName/version/active", ['json' => [$payload]]);
        $statusCode = $response->getStatusCode();
        $data = null;
        if ($statusCode !== 204) {
            $data = $response->toArray(false);
        }
        if (200 > $statusCode || 300 <= $statusCode) {
            throw new RuntimeException("Error while performing the '$operationType->value' operation on the active version (code: $statusCode): {$data['error']}");
        }
    }
}