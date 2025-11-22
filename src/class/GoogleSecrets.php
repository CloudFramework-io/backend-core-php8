<?php
use Google\Cloud\SecretManager\V1\Replication;
use Google\Cloud\SecretManager\V1\Replication\Automatic;
use Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient;
use Google\Cloud\SecretManager\V1\AccessSecretVersionRequest;
use Google\Cloud\SecretManager\V1\AccessSecretVersionResponse;
use Google\Cloud\SecretManager\V1\CreateSecretRequest;
use Google\Cloud\SecretManager\V1\ListSecretsRequest;
use Google\Cloud\SecretManager\V1\ListSecretVersionsRequest;
use Google\Cloud\SecretManager\V1\DeleteSecretRequest;
use Google\Cloud\SecretManager\V1\AddSecretVersionRequest;
use Google\Cloud\SecretManager\V1\SecretPayload;
use Google\Cloud\SecretManager\V1\Secret;
use Google\Cloud\Iam\V1\GetIamPolicyRequest;
use Google\Cloud\Iam\V1\SetIamPolicyRequest;
use Google\Cloud\Iam\V1\Binding;

if (!defined ("_GoogleSecrets_CLASS_") ) {
    define("_GoogleSecrets_CLASS_", TRUE);

    /**
     * GoogleSecrets class provides functionalities to manage Google Cloud Secrets, including
     * creating new secrets, retrieving secret data, and handling errors during operations.
     * Based on: https://docs.cloud.google.com/secret-manager/docs/create-secret-quickstart
     */
    class GoogleSecrets
    {
        var $core;
        var $error = false;
        var $errorCode = '';
        var $errorMsg = [];

        var $client = null;
        var $project_id = null;
        var $project_path = null;

        function __construct(Core7 &$core)
        {
            $this->core = $core;
            if(getenv('PROJECT_ID')) $this->project_id = getenv('PROJECT_ID');
            if($this->core->config->get('core.gcp.project_id')) $this->project_id = $this->core->config->get('core.gcp.project_id');
            if($this->core->config->get('core.gcp.secrets.project_id')) $this->project_id = $this->core->config->get('core.gcp.secrets.project_id');

            if(!$this->project_id) return($this->addError('params-error','Missing PROJECT_ID env_var or core.gcp.project_id, core.gcp.secrets.project_id config vars'));
            $this->client = new SecretManagerServiceClient();
            $this->project_path = $this->client->projectName($this->project_id);
        }

        /**
         * Creates a new secret in the specified project with the given secret ID.
         *
         * https://docs.cloud.google.com/secret-manager/docs/creating-and-accessing-secrets#create-secret-php
         * @param string $secretId The identifier for the secret to be created.
         * @return string|false The JSON-encoded string representation of the created secret upon success,
         *                      or false if an error occurs.
         */
        public function createSecret($secretId) {
            if($this->error) return false;

            // Prepare the request message.
            $secret = new Secret([
                'replication' => new Replication(
                    [
                    'automatic' => new Automatic(),
                    ]
                ),
            ]);
            $request =CreateSecretRequest::build($this->project_path,$secretId,$secret);

            try {
                /** @var Secret $response */
                $response = $this->client->createSecret($request);
                return $response->serializeToJsonString();
            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }

        }


        /**
         * Adds a new version to an existing secret with the provided payload data.
         *
         * https://docs.cloud.google.com/secret-manager/docs/add-secret-version
         * @param string $secretId The identifier of the secret to add a version to.
         * @param string $data The secret data/payload to store in the new version.
         * @return string|false The JSON-encoded string representation of the created secret version upon success,
         *                      or false if an error occurs.
         */
        public function addSecretVersion($secretId, $data) {
            if($this->error) return false;

            $formattedName = $this->client->secretName($this->project_id, $secretId);

            // Create the payload with the secret data
            $payload = new SecretPayload([
                'data' => $data,
            ]);

            $request = (new AddSecretVersionRequest())
                ->setParent($formattedName)
                ->setPayload($payload);
            try {
                $response = $this->client->addSecretVersion($request);
                return $response->serializeToJsonString();
            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }
        }


        /**
         * Retrieves the secret data for a specified secret ID and version.
         *
         * @param string $secretId The identifier of the secret to be retrieved.
         * @param string $version The version of the secret to access. Defaults to 'latest'.
         * @return string|false The secret data if successfully retrieved, or the result of error handling on failure.
         */
        public function getSecret($secretId, $version='latest'): bool|string
        {

            if($this->error) return false;
            $formattedName = $this->client->secretVersionName($this->project_id, $secretId, $version);
            $request = (new AccessSecretVersionRequest())->setName($formattedName);

            try {
                /** @var AccessSecretVersionResponse $response */
                $response = $this->client->accessSecretVersion($request);
                return($response->getPayload()->getData());
            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }
        }


        /**
         * Lists all versions of a specific secret with their states and metadata.
         *
         * https://docs.cloud.google.com/secret-manager/docs/list-secret-versions
         * @param string $secretId The identifier of the secret to list versions for.
         * @return array|false An array of version information if successfully retrieved, or false if an error occurs.
         *                     Each version includes: version number, state, create_time, and name.
         */
        public function getSecretVersions($secretId): bool|array
        {
            if($this->error) return false;

            $formattedName = $this->client->secretName($this->project_id, $secretId);
            $request = (new ListSecretVersionsRequest())->setParent($formattedName);

            try {
                $versions = [];
                $pagedResponse = $this->client->listSecretVersions($request);

                foreach ($pagedResponse as $version) {
                    // Extract version number from version name
                    // Version name format: projects/{project}/secrets/{secret}/versions/{version}
                    $nameParts = explode('/', $version->getName());
                    $versionNumber = end($nameParts);

                    $versions[] = [
                        'version' => $versionNumber,
                        'state' => $version->getState(),
                        'create_time' => $version->getCreateTime() ? $version->getCreateTime()->toDateTime()->format('Y-m-d H:i:s') : null,
                        'name' => $version->getName()
                    ];
                }

                return $versions;
            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }
        }


        /**
         * Lists all secret IDs in the project.
         *
         * https://docs.cloud.google.com/secret-manager/docs/view-secret-details
         * @return array|false An array of secret IDs if successfully retrieved, or false if an error occurs.
         */
        public function listSecrets(): bool|array
        {
            if($this->error) return false;

            $request = (new ListSecretsRequest())->setParent($this->project_path);

            try {
                $secretIds = [];
                $pagedResponse = $this->client->listSecrets($request);

                foreach ($pagedResponse as $secret) {
                    // Extract the secret ID from the secret name
                    // Secret name format: projects/{project}/secrets/{secret_id}
                    $nameParts = explode('/', $secret->getName());
                    $secretIds[] = end($nameParts);
                }

                return $secretIds;
            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }
        }


        /**
         * Deletes a secret by its ID. This permanently deletes the secret and all of its versions.
         *
         * https://docs.cloud.google.com/secret-manager/docs/delete-secret
         * @param string $secretId The identifier of the secret to be deleted.
         * @return bool True if the secret was successfully deleted, or false if an error occurs.
         */
        public function deleteSecret($secretId): bool
        {
            if($this->error) return false;

            $formattedName = $this->client->secretName($this->project_id, $secretId);
            $request = (new DeleteSecretRequest())->setName($formattedName);

            try {
                $this->client->deleteSecret($request);
                return true;
            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }
        }


        /**
         * Gets the IAM policy for a secret, returning all users/service accounts with access and their roles.
         *
         * https://cloud.google.com/secret-manager/docs/access-control
         * @param string $secretId The identifier of the secret to get access information for.
         * @return array|false An array of IAM bindings showing roles and members, or false if an error occurs.
         *                     Each binding includes: role, members (array of user/service account identifiers).
         */
        public function getSecretAccessList($secretId): bool|array
        {
            if($this->error) return false;

            $formattedName = $this->client->secretName($this->project_id, $secretId);
            $request = (new GetIamPolicyRequest())->setResource($formattedName);

            try {
                $policy = $this->client->getIamPolicy($request);
                $accessList = [];

                foreach ($policy->getBindings() as $binding) {
                    $members = [];
                    foreach ($binding->getMembers() as $member) {
                        $members[] = $member;
                    }

                    $accessList[] = [
                        'role' => $binding->getRole(),
                        'members' => $members
                    ];
                }
                return $accessList;
            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }
        }


        /**
         * Grants access to one or more users/service accounts for a specific secret.
         * Adds members to the specified role in the secret's IAM policy.
         *
         * https://cloud.google.com/secret-manager/docs/manage-access-to-secrets
         * @param string $secretId The identifier of the secret to grant access to.
         * @param array|string $members Member(s) to grant access to. Can be a single member string or array of members.
         *                              Format: 'user:email@example.com', 'serviceAccount:name@project.iam.gserviceaccount.com', etc.
         * @param string $role The IAM role to grant. Defaults to 'roles/secretmanager.secretAccessor'.
         * @return bool True if access was granted successfully, or false if an error occurs.
         */
        public function grantSecretAccess($secretId, $members, $role = 'roles/secretmanager.secretAccessor'): bool
        {
            if($this->error) return false;

            // Ensure members is an array
            if(!is_array($members)) {
                $members = [$members];
            }

            $formattedName = $this->client->secretName($this->project_id, $secretId);

            try {
                // Get current IAM policy
                $getRequest = (new GetIamPolicyRequest())->setResource($formattedName);
                $policy = $this->client->getIamPolicy($getRequest);

                // Find existing binding for the role or create new one
                $bindings = $policy->getBindings();
                $roleBinding = null;
                $bindingIndex = -1;

                foreach ($bindings as $index => $binding) {
                    if ($binding->getRole() === $role) {
                        $roleBinding = $binding;
                        $bindingIndex = $index;
                        break;
                    }
                }

                // Add new members to existing binding or create new binding
                if ($roleBinding) {
                    $existingMembers = iterator_to_array($roleBinding->getMembers());
                    $allMembers = array_unique(array_merge($existingMembers, $members));
                    $roleBinding->setMembers($allMembers);
                } else {
                    // Create new binding
                    $newBinding = new Binding([
                        'role' => $role,
                        'members' => $members
                    ]);
                    $bindings[] = $newBinding;
                    $policy->setBindings($bindings);
                }

                // Set the updated IAM policy
                $setRequest = (new SetIamPolicyRequest())
                    ->setResource($formattedName)
                    ->setPolicy($policy);

                $this->client->setIamPolicy($setRequest);
                return true;

            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }
        }


        /**
         * Revokes access from one or more users/service accounts for a specific secret.
         * Removes members from the specified role in the secret's IAM policy.
         *
         * https://cloud.google.com/secret-manager/docs/manage-access-to-secrets
         * @param string $secretId The identifier of the secret to revoke access from.
         * @param array|string $members Member(s) to revoke access from. Can be a single member string or array of members.
         *                              Format: 'user:email@example.com', 'serviceAccount:name@project.iam.gserviceaccount.com', etc.
         * @param string $role The IAM role to revoke from. Defaults to 'roles/secretmanager.secretAccessor'.
         * @return bool True if access was revoked successfully, or false if an error occurs.
         */
        public function revokeAccess($secretId, $members, $role = 'roles/secretmanager.secretAccessor'): bool
        {
            if($this->error) return false;

            // Ensure members is an array
            if(!is_array($members)) {
                $members = [$members];
            }

            $formattedName = $this->client->secretName($this->project_id, $secretId);

            try {
                // Get current IAM policy
                $getRequest = (new GetIamPolicyRequest())->setResource($formattedName);
                $policy = $this->client->getIamPolicy($getRequest);

                // Find existing binding for the role
                $bindings = iterator_to_array($policy->getBindings());
                $updatedBindings = [];
                $found = false;

                foreach ($bindings as $binding) {
                    if ($binding->getRole() === $role) {
                        $found = true;
                        $existingMembers = iterator_to_array($binding->getMembers());

                        // Remove specified members
                        $remainingMembers = array_diff($existingMembers, $members);

                        // Only keep binding if there are remaining members
                        if (!empty($remainingMembers)) {
                            $binding->setMembers(array_values($remainingMembers));
                            $updatedBindings[] = $binding;
                        }
                        // If no remaining members, we don't add this binding (effectively removing it)
                    } else {
                        // Keep other role bindings unchanged
                        $updatedBindings[] = $binding;
                    }
                }

                // If role binding was not found, nothing to revoke
                if (!$found) {
                    return true; // No error, just nothing to do
                }

                // Set the updated IAM policy
                $policy->setBindings($updatedBindings);
                $setRequest = (new SetIamPolicyRequest())
                    ->setResource($formattedName)
                    ->setPolicy($policy);

                $this->client->setIamPolicy($setRequest);
                return true;

            } catch (Exception $e) {
                return($this->addError('system-error',$this->core->jsonDecode($e->getMessage())?:$e->getMessage()));
            }
        }


        /**
         * Resets the current instance by clearing the error state, error code, and error messages list.
         *
         * @return bool Always returns true to indicate the reset was successful.
         */
        function reset(): bool
        {
            $this->error = false;
            $this->errorCode = '';
            $this->errorMsg = [];
            return true;
        }


        /**
         * Marks the current instance as having an error and appends the specified error message to the error messages list.
         *
         * @param mixed $value The error message or object to add to the list of error messages.
         * @return bool Always returns false to indicate an error state.
         */
        function addError(string $code, $value): bool
        {
            $this->error = true;
            $this->errorCode = $code;
            $this->errorMsg[] = $value;
            return false;
        }
    }
}
