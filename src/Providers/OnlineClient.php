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
    /** @var string[] */
    private array $traces = [];

    /**
     * @throws RuntimeException when a required environment variable is missing
     */
    protected function __construct(
        #[SensitiveParameter] protected string $apiToken,
        private readonly bool $trace = false
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
     * @param bool $trace
     * @return OnlineClient
     */
    public static function getInstance(#[SensitiveParameter] string $apiKey, bool $trace = false): OnlineClient
    {
        if (empty(self::$instances[$apiKey])) {
            self::$instances[$apiKey] = new OnlineClient($apiKey, $trace);
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
     * Retrieve generated traces for debugging purposes
     * @return string[]
     */
    final public function getTraces(): array
    {
        return $this->traces;
    }

    /**
     * Add a new trace if trace collection is enabled
     *
     * @param string $traceLog
     * @return void
     */
    final protected function addTrace(string $traceLog): void
    {
        if ($this->trace) {
            $this->traces[] = $traceLog;
        }
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
        $this->addTrace('Payload: ' . json_encode($payload));
        $this->addTrace('Method: PATCH');
        $this->addTrace('URI: ' . self::API_ENDPOINT . "/domain/$domainName/version/active");
        $response = $this->client->request(
            'PATCH', self::API_ENDPOINT . "/domain/$domainName/version/active", ['json' => [$payload]]
        );
        $statusCode = $response->getStatusCode();
        $this->addTrace('HTTP response code: ' . $statusCode);
        $data = null;
        if ($statusCode !== 204) {
            $data = $response->toArray(false);
            $this->addTrace('Response content: ' . json_encode($data));
        }
        if (200 > $statusCode || 300 <= $statusCode) {
            $this->addTrace('Final operation status: NOK');
            throw new RuntimeException("Error while performing the '$operationType->value' operation on the active version (code: $statusCode): {$data['error']}");
        }
        $this->addTrace('Final operation status: OK');
    }
}