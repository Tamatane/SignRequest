<?php

namespace AtaneNL\SignRequest;

use Psr\Http\Message\ResponseInterface;

class CreateDocumentResponse
{

    public $uuid;
    public $url;
    public $securityHash;

    public function __construct(ResponseInterface $response)
    {
        $body = json_decode($response->getBody()->getContents());
        $this->uuid = $body->uuid;
        $this->url = $body->url;
        $this->securityHash = $body->security_hash;
    }

}
