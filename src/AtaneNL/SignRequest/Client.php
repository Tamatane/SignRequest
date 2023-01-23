<?php

namespace AtaneNL\SignRequest;

use AtaneNL\SignRequest\Exceptions\LocalException;
use AtaneNL\SignRequest\Exceptions\RemoteException;
use AtaneNL\SignRequest\Exceptions\SendSignRequestException;
use GuzzleHttp\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class Client
{
    const DEFAULT_API_BASEURL = "https://[SUBDOMAIN]signrequest.com/api/v1";
    protected string $apiBaseUrl;
    protected static string $defaultLanguage = 'nl';
    protected ?string $subdomain; // the subdomain
    protected ClientInterface $httpClient;


    /**
     * @param string $token
     * @param string|null $subdomain
     * @param array $clientOptions GuzzleClient options extend/override
     */
    public function __construct(string $token, ?string $subdomain = null, array $clientOptions = [])
    {
        $this->subdomain = $subdomain;
        $this->setApiBaseUrl();
        $this->setHttpClient(
            $this->buildHttpClient($token, $clientOptions)
        );
    }

    public function getSubdomain(): ?string
    {
        return $this->subdomain;
    }

    public function setApiBaseUrl($url = self::DEFAULT_API_BASEURL): void
    {
        $this->apiBaseUrl = $url;
    }

    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    public function setHttpClient(ClientInterface $client)
    {
        $this->httpClient = $client;
    }

    public function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    public static function getDefaultLanguage(): string
    {
        return self::$defaultLanguage;
    }

    public static function setDefaultLanguage(string $defaultLanguage): void
    {
        self::$defaultLanguage = $defaultLanguage;
    }

    protected function buildHttpClient(string $token, array $clientOptions = []): ClientInterface
    {
        return new \GuzzleHttp\Client(array_merge([
            'headers' => [
                'user-agent' => 'SignRequestClient/3.0',
                'accept' => 'application/json',
                'Authorization' => "Token {$token}"
            ]
        ], $clientOptions));
    }

    /**
     * Send a document to SignRequest.
     * https://signrequest.com/api/v1/docs/#operation/documents_create
     *
     * @param string $filePath The absolute path to a file.
     * @param string $identifier unique identifier for this file
     * @param string|null $callbackUrl url to call when signing is completed
     * @param string|null $filename the filename as the signer will see it
     * @param array $settings
     * @return CreateDocumentResponse
     * @throws SendSignRequestException
     */
    public function createDocument(string $filePath, string $identifier, ?string $callbackUrl = null, ?string $filename = null, array $settings = []): CreateDocumentResponse
    {
        if ($filename === null) {
            $filename = pathinfo($filePath, PATHINFO_BASENAME);
        }

        $contents = file_get_contents($filePath);

        return $this->createDocumentFromContents($contents, $identifier, $callbackUrl, $filename, $settings);
    }

    /**
     * @param string $contents
     * @param string $identifier
     * @param string|null $callbackUrl
     * @param string|null $filename
     * @param array $settings
     * @return CreateDocumentResponse
     * @throws SendSignRequestException
     */
    public function createDocumentFromContents(string $contents, string $identifier, ?string $callbackUrl = null, string $filename = null, array $settings = []): CreateDocumentResponse
    {
        try {
            $response = $this->createDocumentRequest([
                'json' => array_merge($settings, [
                    'file_from_content' => $this->prepareFileContents($contents),
                    'file_from_content_name' => $filename,
                    'external_id' => $identifier,
                    'events_callback_url' => $callbackUrl,
                ])
            ]);
        } catch (RemoteException $e) {
            throw new SendSignRequestException($e->getMessage(), $e->getCode(), $e);
        }

        return new CreateDocumentResponse($response);
    }

    /**
     * Send a document to SignRequest using the file_from_url option.
     * @param string $url The URL of the page we want to sign.
     * @param string $identifier
     * @param string|null $callbackUrl
     * @param array $settings
     * @return CreateDocumentResponse
     * @throws SendSignRequestException
     */
    public function createDocumentFromURL(string $url, string $identifier, ?string $callbackUrl = null, array $settings = []): CreateDocumentResponse
    {
        try {
            $response = $this->createDocumentRequest([
                'json' => array_merge($settings, [
                    'file_from_url' => $url,
                    'external_id' => $identifier,
                    'events_callback_url' => $callbackUrl,
                ])
            ]);
        } catch (RemoteException $e) {
            throw new SendSignRequestException($e->getMessage(), $e->getCode(), $e);
        }

        return new CreateDocumentResponse($response);
    }

    /**
     * Send a document to SignRequest using the template option.
     * @param string $url the URL of the template we want to sign
     * @param string|null $identifier
     * @param string|null $callbackUrl
     * @param array $settings
     * @return CreateDocumentResponse
     * @throws SendSignRequestException
     */
    public function createDocumentFromTemplate(string $url, ?string $identifier = null, ?string $callbackUrl = null, array $settings = []): CreateDocumentResponse
    {
        try {
            $response = $this->createDocumentRequest([
                'json' => array_merge($settings, [
                    'template' => $url,
                    'external_id' => $identifier,
                    'events_callback_url' => $callbackUrl,
                ])
            ]);
        } catch (RemoteException $e) {
            throw new SendSignRequestException($e->getMessage(), $e->getCode(), $e);
        }

        return new CreateDocumentResponse($response);
    }

    /**
     * Gets templates from sign request frontend.
     * @return array response
     * @throws RemoteException
     */
    public function getTemplates(): array
    {
        return $this->decodeJsonResponse(
            $this->request('templates', 'get')
        );
    }

    /**
     * Add attachment to document sent to SignRequest.
     * @param string $filePath
     * @param CreateDocumentResponse $createDocumentResponse
     * @param string|null $filename
     * @return array response
     * @throws SendSignRequestException
     */
    public function addAttachmentToDocument(string $filePath, CreateDocumentResponse $createDocumentResponse, ?string $filename = null): array
    {
        try {
            if ($filename === null) {
                $filename = pathinfo($filePath, PATHINFO_BASENAME);
            }

            $contents = file_get_contents($filePath);

            $response = $this->request('document-attachments', 'post', [
                'json' => [
                    'file_from_content_name' => $filename,
                    'file_from_content' => $this->prepareFileContents($contents),
                    'document' => $createDocumentResponse->url,
                ]
            ]);
        } catch (RemoteException $e) {
            throw new SendSignRequestException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->decodeJsonResponse($response);
    }

    /**
     * Send a sign request for a created document.
     * @param string $documentId uuid
     * @param string $sender Senders e-mail address
     * @param array $recipients
     * @param null $message
     * @param bool $sendReminders Send automatic reminders
     * @param array $settings Add additional request parameters or override defaults
     * @return array SignRequest response data
     * @throws SendSignRequestException
     */
    public function sendSignRequest(string $documentId, string $sender, array $recipients, $message = null, bool $sendReminders = false, array $settings = []): array
    {
        try {
            foreach ($recipients as &$r) {
                if (!array_key_exists('language', $r)) {
                    $r['language'] = self::$defaultLanguage;
                }
            }

            $response = $this->request('signrequests', 'post', [
                'json' => array_merge([
                    "disable_text" => true,
                    "disable_attachments" => true,
                    "disable_date" => true,
                ], $settings, [
                    "document" => $this->makeRequestUri("documents/{$documentId}"),
                    "from_email" => $sender,
                    "message" => $message,
                    "signers" => $recipients,
                    "send_reminders" => $sendReminders
                ])
            ]);

            return $this->decodeJsonResponse($response);
        } catch (RemoteException $e) {
            throw new SendSignRequestException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Send a reminder to all recipients who have not signed yet.
     * @param string $signRequestId uuid
     * @return array response
     * @throws Exceptions\RemoteException
     */
    public function sendSignRequestReminder(string $signRequestId): array
    {
        return $this->decodeJsonResponse(
            $this->request("signrequests/{$signRequestId}/resend_signrequest_email", "post")
        );
    }

    /**
     * Cancel an existing sign request
     *
     * @param string $signRequestId uuid
     * @return array response
     * @throws RemoteException
     */
    public function cancelSignRequest(string $signRequestId): array
    {
        return $this->decodeJsonResponse(
            $this->request("signrequests/{$signRequestId}/cancel_signrequest", "post")
        );
    }

    /**
     * Gets the current status for a sign request.
     * @param string $signRequestId uuid
     * @return array response
     * @throws RemoteException
     */
    public function getSignRequestStatus(string $signRequestId): array
    {
        return $this->decodeJsonResponse(
            $this->request("signrequests/{$signRequestId}", "get")
        );
    }

    /**
     * Get a file.
     * @param string $documentId uuid
     * @return array response
     * @throws RemoteException
     */
    public function getDocument(string $documentId): array
    {
        return $this->decodeJsonResponse(
            $this->request("documents/{$documentId}", "get")
        );
    }

    /**
     * Create a new team.
     * The client should be initialized *without* a subdomain for this method to function properly!!!
     * @param string $name
     * @param string $subdomain
     * @return string
     * @throws Exceptions\LocalException
     * @throws Exceptions\RemoteException
     */
    public function createTeam(string $name, string $subdomain): ?string
    {
        try {
            $this->assertGlobalClient();

            $response = $this->decodeJsonResponse(
                $this->request("teams", "post", [
                    'json' => [
                        "name" => $name,
                        "subdomain" => $subdomain,
                    ]
                ])
            );

            return $response['subdomain'] ?? null;

        } catch (RemoteException $e) {
            throw new RemoteException("Unable to create team {$name}: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * @param string $subdomain
     * @return array
     * @throws LocalException
     * @throws RemoteException
     */
    public function getTeam(string $subdomain): array
    {
        try {
            $this->assertGlobalClient();

            return $this->decodeJsonResponse(
                $this->request("teams/${subdomain}", 'get')
            );
        } catch (RemoteException $e) {
            throw new RemoteException("Unable to get team {$subdomain}: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * @param string $subdomain
     * @param array $params (specify any parameters to update, such as name, logo, phone, primary_color)
     * @return array
     * @throws LocalException
     * @throws RemoteException
     */
    public function updateTeam(string $subdomain, array $params): array
    {
        try {
            $this->assertGlobalClient();

            return $this->decodeJsonResponse(
                $this->request("teams/${subdomain}", 'post')
            );
        } catch (RemoteException $e) {
            throw new RemoteException("Unable to get team {$subdomain}: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Make a request to the API for the given action
     *
     * @param string $action
     * @param string $method HTTP verb
     * @param array $options
     * @return ResponseInterface
     * @throws RemoteException
     */
    protected function request(string $action, string $method, array $options = []): ResponseInterface
    {
        try {
            return $this->getHttpClient()->request($method, $this->makeRequestUri($action), $options);
        } catch (ClientExceptionInterface $e) {
            throw new RemoteException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Transform action to callable request url
     *
     * @param string $action
     * @return string
     */
    protected function makeRequestUri(string $action): string
    {
        return $this->getApiUrl($this->getSubdomain()) . "/" . $action . "/";
    }

    /**
     * Set the API url based on the subdomain.
     *
     * @param string|null $subdomain
     */
    protected function getApiUrl(?string $subdomain): string
    {
        return preg_replace('/\[SUBDOMAIN\]/', ltrim($subdomain . ".", "."), $this->getApiBaseUrl());
    }

    /**
     * https://signrequest.com/api/v1/docs/#operation/documents_create
     * @throws RemoteException
     */
    protected function createDocumentRequest(array $options): ResponseInterface
    {
        return $this->request('documents', 'post', $options);
    }

    /**
     * Transform response containing json to an array
     *
     * @param ResponseInterface $response
     * @return array
     */
    protected function decodeJsonResponse(ResponseInterface $response): array
    {
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Prepare file contents in SignRequest expected format
     *
     * @param string $contents
     * @return string
     */
    protected function prepareFileContents(string $contents): string
    {
        return base64_encode($contents);
    }

    /**
     * Assert client is not initialized with a subdomain
     * @return void
     * @throws LocalException
     */
    protected function assertGlobalClient(): void
    {
        if ($this->getSubdomain() !== null) {
            throw new Exceptions\LocalException("This request cannot be sent to a subdomain. Initialize the client without a subdomain.");
        }
    }
}
