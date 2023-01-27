<?php

namespace JefferyHuntley\BunnyStorage\Exceptions;

use Exception;

class BunnyCDNStorageAuthenticationException extends BunnyCDNStorageException
{
    public function __construct($storageZoneName, $accessKey, $code = 0, Exception $previous = null) {
        parent::__construct("Authentication failed for storage zone '{$storageZoneName}' with access key '{$accessKey}'.", $code, $previous);
    }

    public function __toString() {
        return __CLASS__ . ": {$this->message}\n";
    }
}