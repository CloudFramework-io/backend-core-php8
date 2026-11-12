<?php
// CLOUD Documentum:
//   DevGroup: /cf/products/cloud-development
//   DevUnit: /php/classes/Core7/CoreSecurity, /cloud-development/frameworks/php
//   Activities: 6651158731161600
use Google\Cloud\Kms\V1\Client\KeyManagementServiceClient;
use Google\Cloud\Kms\V1\EncryptRequest;
use Google\Cloud\Kms\V1\DecryptRequest;

if (!defined ("_GoogleKMS_CLASS_") ) {
    define("_GoogleKMS_CLASS_", TRUE);

    /**
     * GoogleKMS: low level access to Cloud KMS symmetric keys (Encrypt / Decrypt), since 8.5.27.
     *
     * It is the only place of the framework that talks to Cloud KMS: CoreSecurity (values 'v3:') and Buckets
     * (encryptLargeFile / decryptLargeFile, which wrap their data key with wrapKey() / unwrapKey()) use it.
     *
     * Rules:
     *  - One KeyManagementServiceClient per instance, created the first time it is needed (REST transport) and reused.
     *  - Each call has a maximum of TIMEOUT_MILLIS per attempt and the retries of the client library are disabled.
     *    The class retries ONCE, and only when the failure is transient (UNAVAILABLE, DEADLINE_EXCEEDED, HTTP 503/504
     *    or a connection error). A permission error, a wrong key or a wrong additional authenticated data (AAD) is never
     *    retried.
     *  - It never reads key metadata (getCryptoKey, listCryptoKeyVersions...): the read quota of Cloud KMS is small
     *    (300 reads per minute per project) and every request would spend it. The version used to encrypt comes from
     *    the name of the EncryptResponse.
     *  - It never writes the plaintext, the ciphertext nor the additional authenticated data in errorMsg nor in the
     *    logs: only the method, the status and the key resource.
     *  - $error / $errorCode / $errorMsg describe the LAST call: every encrypt() / decrypt() starts by resetting them,
     *    so one instance can be reused for the rest of the request after a failure.
     *
     * ```php
     * $kms = $this->core->loadClass('GoogleKMS');
     * $res = $kms->encrypt($plaintext, 'cloudframework:default', 'projects/{p}/locations/{l}/keyRings/{r}/cryptoKeys/{k}');
     * if($res===false) return $kms->errorMsg;
     * // $res = ['ciphertext'=>(binary), 'version'=>'1']
     * $plain = $kms->decrypt($res['ciphertext'], 'cloudframework:default', '.../cryptoKeys/{k}');
     * ```
     * @package CoreClasses
     */
    class GoogleKMS
    {
        /** @var Core7 $core */
        var $core;
        /** @var bool $error true when the last call failed */
        var $error = false;
        /** @var string $errorCode code of the last failure: params-error, kms-client-error, kms-unavailable, kms-permission-denied, kms-invalid-argument, kms-not-found, kms-error, kms-unexpected-response */
        var $errorCode = '';
        /** @var array $errorMsg messages of the last failure, never with plaintext, ciphertext or AAD */
        var $errorMsg = [];
        /** @var string $lastStatus gRPC status name of the last failed attempt (UNAVAILABLE, PERMISSION_DENIED, INVALID_ARGUMENT...) or '' */
        var $lastStatus = '';
        /** @var bool $lastTransient the last failure was transient (after its retry) */
        var $lastTransient = false;
        /** @var int $lastAttempts attempts made by the last call (1 or 2) */
        var $lastAttempts = 0;

        /** Maximum time of one attempt, in milliseconds */
        public const TIMEOUT_MILLIS = 3000;
        /** Maximum plaintext size accepted by Cloud KMS Encrypt (64 KiB) */
        public const MAX_PLAINTEXT_BYTES = 65536;

        /**
         * Injection point for tests: callable(array $client_options): object that returns the client to use instead of a
         * new KeyManagementServiceClient. The object must answer encrypt(EncryptRequest, array) and
         * decrypt(DecryptRequest, array) like the real client. Production code never sets it.
         * @var callable|null
         */
        public static $client_factory = null;

        /** @var object|null $client KeyManagementServiceClient (or the injected one) */
        private $client = null;
        /** @var array $client_options options for the client (transport, credentials) */
        private $client_options = ['transport'=>'rest'];
        /** @var string|null $init_error invalid credentials given to the constructor or setCredentials(): every call fails with it */
        private $init_error = null;

        /**
         * @param Core7 $core
         * @param array|null $options optional:
         *    - 'credentials': service account credentials, as a decoded array, a JSON string or a file path.
         *      Without it the Application Default Credentials of the runtime are used.
         */
        function __construct(Core7 &$core, $options = null)
        {
            $this->core = $core;
            if(is_array($options) && !empty($options['credentials']))
                $this->setCredentials($options['credentials']);
        }

        /**
         * Credentials for the client: a decoded array, a JSON string or an existing file path. Must be called before the
         * first encrypt() / decrypt(): the client is created once.
         * @param array|string $credentials
         * @return bool false (params-error) when it is neither a JSON object, an array nor an existing file
         */
        public function setCredentials(array|string $credentials): bool
        {
            $this->init_error = null;
            if(is_array($credentials)) {
                $this->client_options['credentials'] = $credentials;
                return true;
            }
            $decoded = json_decode($credentials, true);
            if(json_last_error()===JSON_ERROR_NONE && is_array($decoded)) {
                $this->client_options['credentials'] = $decoded;
                return true;
            }
            if(is_file($credentials)) {
                $this->client_options['credentials'] = $credentials;
                return true;
            }
            $this->init_error = 'serviceAccount is not a valid JSON string nor an existing file path';
            return $this->addError($this->init_error,'params-error');
        }

        /**
         * Sets the client to use (tests, or an application that already has one). It replaces the current one.
         * @param object $client object answering encrypt(EncryptRequest, array) / decrypt(DecryptRequest, array)
         * @return void
         */
        public function setClient(object $client): void
        {
            $this->client = $client;
        }

        /**
         * Encrypts $plaintext with a symmetric CryptoKey. The primary version of the key is chosen by Cloud KMS.
         * @param string $plaintext bytes to encrypt (up to MAX_PLAINTEXT_BYTES)
         * @param string $aad additional authenticated data; the same value is required to decrypt. '' = none
         * @param string $keyResource projects/{project}/locations/{location}/keyRings/{ring}/cryptoKeys/{key}
         * @return array|false ['ciphertext'=>binary string, 'version'=>'N' (the version that encrypted it)]
         */
        public function encrypt(string $plaintext, string $aad, string $keyResource): array|false
        {
            $this->resetError();
            if(!$this->validKeyResource($keyResource)) return $this->addError('encrypt: the key resource must be projects/{project}/locations/{location}/keyRings/{ring}/cryptoKeys/{key}','params-error');
            if(strlen($plaintext)>self::MAX_PLAINTEXT_BYTES) return $this->addError('encrypt: the plaintext is larger than '.self::MAX_PLAINTEXT_BYTES.' bytes, the limit of Cloud KMS','params-error');

            $response = $this->call('encrypt', function () use ($plaintext, $aad, $keyResource) {
                $request = (new EncryptRequest())->setName($keyResource)->setPlaintext($plaintext);
                if($aad!=='') $request->setAdditionalAuthenticatedData($aad);
                return $request;
            }, $keyResource);
            if($response===false) return false;

            $ciphertext = $response->getCiphertext();
            $version_name = (string)$response->getName();
            $version = (strpos($version_name,$keyResource.'/cryptoKeyVersions/')===0)?substr($version_name,strlen($keyResource.'/cryptoKeyVersions/')):'';
            if(!is_string($ciphertext) || $ciphertext==='' || !preg_match('/^[1-9][0-9]*$/',$version))
                return $this->addError('encrypt: unexpected response of Cloud KMS for '.$keyResource.' (no ciphertext or no key version)','kms-unexpected-response');

            return ['ciphertext'=>$ciphertext,'version'=>$version];
        }

        /**
         * Decrypts a ciphertext produced by encrypt() with the same key and the same additional authenticated data.
         * Cloud KMS finds the version by itself.
         * @param string $ciphertext binary ciphertext
         * @param string $aad additional authenticated data used to encrypt. '' = none
         * @param string $keyResource projects/{project}/locations/{location}/keyRings/{ring}/cryptoKeys/{key}
         * @return string|false
         */
        public function decrypt(string $ciphertext, string $aad, string $keyResource): string|false
        {
            $this->resetError();
            if(!$this->validKeyResource($keyResource)) return $this->addError('decrypt: the key resource must be projects/{project}/locations/{location}/keyRings/{ring}/cryptoKeys/{key}','params-error');
            if($ciphertext==='') return $this->addError('decrypt: empty ciphertext','params-error');

            $response = $this->call('decrypt', function () use ($ciphertext, $aad, $keyResource) {
                $request = (new DecryptRequest())->setName($keyResource)->setCiphertext($ciphertext);
                if($aad!=='') $request->setAdditionalAuthenticatedData($aad);
                return $request;
            }, $keyResource);
            if($response===false) return false;

            $plaintext = $response->getPlaintext();
            if(!is_string($plaintext))
                return $this->addError('decrypt: unexpected response of Cloud KMS for '.$keyResource,'kms-unexpected-response');
            return $plaintext;
        }

        /**
         * Wraps a data encryption key (envelope encryption): encrypt() without AAD, returning only the ciphertext.
         * @param string $dek
         * @param string $keyResource
         * @return string|false
         */
        public function wrapKey(string $dek, string $keyResource): string|false
        {
            $res = $this->encrypt($dek,'',$keyResource);
            return ($res===false)?false:$res['ciphertext'];
        }

        /**
         * Unwraps a data encryption key wrapped with wrapKey().
         * @param string $wrapped
         * @param string $keyResource
         * @return string|false
         */
        public function unwrapKey(string $wrapped, string $keyResource): string|false
        {
            return $this->decrypt($wrapped,'',$keyResource);
        }

        /**
         * One call to Cloud KMS with the timeout of TIMEOUT_MILLIS and, only for a transient failure, one retry.
         * @param string $method 'encrypt' or 'decrypt'
         * @param callable $build_request builds a new request object for each attempt
         * @param string $keyResource for the error messages
         * @return object|false the response
         */
        private function call(string $method, callable $build_request, string $keyResource): object|false
        {
            $client = $this->getClient();
            if(!$client) return false;

            $call_options = ['timeoutMillis'=>self::TIMEOUT_MILLIS,'retrySettings'=>['retriesEnabled'=>false,'noRetriesRpcTimeoutMillis'=>self::TIMEOUT_MILLIS]];
            $last = null;
            for($attempt=1;$attempt<=2;$attempt++) {
                $this->lastAttempts = $attempt;
                try {
                    return $client->$method($build_request(),$call_options);
                } catch (\Throwable $e) {
                    $last = $e;
                    if(!$this->isTransient($e)) break;
                }
            }

            [$status,$code] = $this->classify($last);
            $this->lastStatus = $status;
            $this->lastTransient = $this->isTransient($last);
            return $this->addError($method.': Cloud KMS '.($status?:'error').' for '.$keyResource.' after '.$this->lastAttempts.' attempt(s)'.$this->safeReason($last),$code);
        }

        /**
         * The client, created the first time it is needed: the injected factory in tests, otherwise a
         * KeyManagementServiceClient with the REST transport.
         * @return object|null
         */
        private function getClient(): ?object
        {
            if(is_object($this->client)) return $this->client;
            if($this->init_error) {
                $this->addError($this->init_error,'params-error');
                return null;
            }
            try {
                if(is_callable(self::$client_factory)) {
                    $client = call_user_func(self::$client_factory,$this->client_options);
                } else {
                    if(!class_exists(KeyManagementServiceClient::class)) {
                        $this->addError('the class Google\Cloud\Kms\V1\Client\KeyManagementServiceClient is not available: add google/cloud-kms to composer.json','kms-client-error');
                        return null;
                    }
                    $client = new KeyManagementServiceClient($this->client_options);
                }
            } catch (\Throwable $e) {
                $this->addError('the Cloud KMS client can not be created: '.get_class($e),'kms-client-error');
                return null;
            }
            if(!is_object($client)) {
                $this->addError('the Cloud KMS client can not be created','kms-client-error');
                return null;
            }
            return $this->client = $client;
        }

        /**
         * A transient failure deserves one retry: UNAVAILABLE, DEADLINE_EXCEEDED (gRPC codes 14 and 4, HTTP 503 and 504)
         * or a connection error raised before any answer.
         * @param \Throwable|null $e
         * @return bool
         */
        private function isTransient(?\Throwable $e): bool
        {
            if(!$e) return false;
            [$status] = $this->classify($e);
            if(in_array($status,['UNAVAILABLE','DEADLINE_EXCEEDED'],true)) return true;
            return (stripos(get_class($e),'ConnectException')!==false);
        }

        /**
         * Status name and error code of an exception of the client library (Google\ApiCore\ApiException), without
         * depending on that class.
         * @param \Throwable|null $e
         * @return array [status, errorCode]
         */
        private function classify(?\Throwable $e): array
        {
            if(!$e) return ['','kms-error'];
            $status = (method_exists($e,'getStatus') && is_string($e->getStatus()))?strtoupper($e->getStatus()):'';
            if(!$status) {
                // numeric gRPC code or HTTP status when there is no status name
                $map = [14=>'UNAVAILABLE',4=>'DEADLINE_EXCEEDED',7=>'PERMISSION_DENIED',3=>'INVALID_ARGUMENT',5=>'NOT_FOUND',16=>'UNAUTHENTICATED'
                    ,503=>'UNAVAILABLE',504=>'DEADLINE_EXCEEDED',403=>'PERMISSION_DENIED',400=>'INVALID_ARGUMENT',404=>'NOT_FOUND',401=>'UNAUTHENTICATED'];
                $status = $map[(int)$e->getCode()] ?? '';
            }
            $code = match ($status) {
                'UNAVAILABLE','DEADLINE_EXCEEDED' => 'kms-unavailable',
                'PERMISSION_DENIED','UNAUTHENTICATED' => 'kms-permission-denied',
                'INVALID_ARGUMENT','FAILED_PRECONDITION' => 'kms-invalid-argument',
                'NOT_FOUND' => 'kms-not-found',
                default => (stripos(get_class($e),'ConnectException')!==false)?'kms-unavailable':'kms-error',
            };
            return [$status,$code];
        }

        /**
         * A short reason for errorMsg: the basic message of the exception when it has one, without line breaks and cut
         * to 200 characters. The library never puts the request payload in it; the class name is used otherwise.
         * @param \Throwable|null $e
         * @return string
         */
        private function safeReason(?\Throwable $e): string
        {
            if(!$e) return '';
            $msg = (method_exists($e,'getBasicMessage') && is_string($e->getBasicMessage()))?$e->getBasicMessage():get_class($e);
            $msg = substr(trim(preg_replace('/\s+/',' ',$msg)),0,200);
            return ($msg!=='')?': '.$msg:'';
        }

        /**
         * projects/{project}/locations/{location}/keyRings/{ring}/cryptoKeys/{key}
         * @param string $keyResource
         * @return bool
         */
        private function validKeyResource(string $keyResource): bool
        {
            return (bool)preg_match('#^projects/[^/\s]+/locations/[^/\s]+/keyRings/[^/\s]+/cryptoKeys/[^/\s]+$#',$keyResource);
        }

        /**
         * Resets the error state (done at the start of every call)
         * @return void
         */
        public function resetError(): void
        {
            $this->error = false;
            $this->errorCode = '';
            $this->errorMsg = [];
            $this->lastStatus = '';
            $this->lastTransient = false;
            $this->lastAttempts = 0;
        }

        /**
         * @param string $msg message without sensitive data
         * @param string $code
         * @return false
         */
        private function addError(string $msg, string $code = 'kms-error'): bool
        {
            $this->error = true;
            $this->errorCode = $code;
            $this->errorMsg[] = $msg;
            return false;
        }
    }
}
