# SignRequest API Client
Client lib for the SignRequest API

## Usage
```php

// initialize the client
$client = new \AtaneNL\SignRequest\Client('yourApiKey123', 'your-subdomain');

// send a document to SignRequest
$cdr = $client->createDocument('/path/to/file', 'localReferenceToThisFile');

// define recipients
$recipient = [
            'first_name'          => 'John',
            'last_name'           => 'Smith',
            'email'               => 'smith@example.com',
            'verify_phone_number' => false,
            'needs_to_sign'       => true,
            'order'               => 0,
            'language'            => 'nl',
            'force_language'      => true,
            'redirect_url'        => 'http://www.example.com/thank-you-for-signing',  // redirect here after the user finished signing
        ];
$recipients = [$recipient]; // you can add as many as you need

// notify intended signers
$result = $client->sendSignRequest($cdr->uuid, 'sender@company.com', $recipients, "Please sign this");
```

The default language is set to dutch, change it by:
```php
\AtaneNL\SignRequest\Client::setDefaultLanguage('en');
```

Refer to the [SignRequest API manual page](https://signrequest.com/api/v1/docs/) for full options.


## Test
Running tests:
```shell
composer test
```
Running phpstan:
```shell
composer phpstan
```
