<?php
/**
 * Test script for Buckets class
 *
 * Run: php runscript.php tests/Buckets?bucket=gs://your-bucket-name
 *
 * Tests:
 * - Basic bucket operations (mkdir, rmdir, scan, fastScan)
 * - File operations (putContents, getContents, uploadContents, deleteFile)
 * - File manipulation (copyFile, moveFile)
 * - Path validation (isDir, isFile, getBucketPath)
 * - MIME type detection (getMimeTypeFromExtension)
 * - Bucket information (getInfo, getAdminUrl)
 * - File information (getFileInfo)
 * - KMS encryption (initKMS, getKMSKeyName, encryptLargeFile, decryptLargeFile)
 */
class Script extends CoreScripts {

    /** @var Buckets */
    private $bucket;
    private $useKMS = false;
    private $kmsConfig = [];

    private $test_base_path = '/test_buckets_script';
    private $test_results = [];

    function main() {
        $this->core->logs->add('Starting Buckets Test Script');
        if(!$this->core->config->get('core.datastorage.on')) {
            return $this->sendTerminal('core.datastorage.on is not enabled. Please enable it in the config [local_script.json]');
        }

        // Get bucket name from params or formParams
        if(!$bucket_name = $this->formParams['bucket'] ?? null) {
            $this->sendTerminal('Missing bucket name. You can pass it as a query parameter: ?bucket=gs://your-bucket-name');
            if($default = $this->core->config->get('bucketUploadPathTest'))
                if(strpos($default, 'gs://')!==0) $default='';
            do {
                $bucket_name = $this->prompt->title('Bucket name (gs://..):')
                    ->defaultValue($default ?? '')
                    ->cacheVar('bucket')
                    ->mandatory(true)
                    ->query();
            } while(strpos($default, 'gs://')!==0);
            if($prefix = preg_replace('/^gs:\/\/[^\/]*/', '', $bucket_name)) {
                $this->test_base_path = "{$prefix}{$this->test_base_path}";
                $bucket_name = str_replace($prefix.'_delete_prefix', '', $bucket_name.'_delete_prefix');
            }
            
        }

        if (!$bucket_name) {
            $this->sendTerminal('ERROR: Bucket name is required. Use ?bucket=gs://your-bucket-name');
            return;
        }

        //region CHECK test with Google KMS
        if(!isset($this->formParams['kms'])) {
            $useKMS = $this->prompt->title('¿Test KMS file encryption (https://console.cloud.google.com/security/kms/keyrings)?')
                ->defaultValue('n')
                ->cacheVar('mks')
                ->mandatory(true)
                ->allowedValues(['y','n'])
                ->query();

            $this->useKMS = $useKMS==='y';
        } else $this->useKMS = $this->formParams['kms'] === 'y';

        if($this->useKMS) {
            $this->kmsConfig['projectId'] = $this->prompt->init()->title(' - GCP Project Id?')
                ->defaultValue($this->core->gc_project_id??'')
                ->cacheVar('project_id')
                ->mandatory(true)
                ->query();

            $this->kmsConfig['location'] = $this->prompt->init()->title(' - GCP KeyRing Location?')
                ->defaultValue('global')
                ->cacheVar('location')
                ->mandatory(true)
                ->query();

            $this->kmsConfig['keyRing'] = $this->prompt->init()->title(' - GCP KeyRing Name?')
                ->cacheVar('keyRing')
                ->mandatory(true)
                ->query();

            $this->kmsConfig['keyId'] = $this->prompt->init()->title(' - GCP keyRing Id?')
                ->cacheVar('keyId')
                ->mandatory(true)
                ->query();

        }
        //endregion


        // Initialize Buckets class
        $this->bucket = $this->core->loadClass('Buckets', $bucket_name);
        if ($this->bucket->error) {
            $this->sendTerminal('ERROR initializing Buckets: ' . json_encode($this->bucket->errorMsg));
            return;
        }

        if($this->useKMS) {
            $this->bucket->initKMS($this->kmsConfig);
            if ($this->bucket->error) {
                $this->sendTerminal('ERROR initializing Buckets: ' . json_encode($this->bucket->errorMsg));
                return;
            }
            $KMDName = $this->bucket->getKMSKeyName();
            if ($this->bucket->error) {
                $this->sendTerminal('ERROR initializing Buckets: ' . json_encode($this->bucket->errorMsg));
                return;
            }
            $this->sendTerminal("   Using KMS: {$KMDName}");
        }

        $this->sendTerminal("=== BUCKETS TEST SCRIPT ===");
        $this->sendTerminal("Bucket: {$bucket_name}");
        $this->sendTerminal("Version: {$this->bucket->version}");
        $this->sendTerminal("");

        // Run tests
        $this->testBucketInfo();
        $this->testGetBucketPath();
        $this->testMimeTypeDetection();
        $this->testMkdir();
        $this->testPutContents();
        $this->testGetContents();
        $this->testUploadContents();
        $this->testIsFile();
        $this->testIsDir();
        $this->testGetFileInfo();
        $this->testCopyFile();
        $this->testMoveFile();
        $this->testScan();
        $this->testFastScan();

        // KMS tests
        if ($this->useKMS) {
            $this->testKMSInit();
            $this->testKMSKeyName();
            $this->testKMSEncryptDecrypt();
        }

        $this->testDeleteFile();
        $this->testRmdir();

        // Show summary
        $this->showSummary();

        // Cleanup
        $this->cleanup();
    }

    /**
     * Test getInfo() and getAdminUrl()
     */
    private function testBucketInfo() {
        $this->sendTerminal("TEST: Bucket Info");

        $info = $this->bucket->getInfo();
        if ($this->bucket->error) {
            $this->addTestResult('getInfo()', false, $this->bucket->errorMsg);
            return;
        }

        $admin_url = $this->bucket->getAdminUrl();

        $this->sendTerminal("  - Bucket Name: " . ($info['name'] ?? 'N/A'));
        $this->sendTerminal("  - Location: " . ($info['location'] ?? 'N/A'));
        $this->sendTerminal("  - Storage Class: " . ($info['storageClass'] ?? 'N/A'));
        $this->sendTerminal("  - Admin URL: " . ($admin_url ?? 'N/A'));

        $success = isset($info['name']) && $admin_url;
        $this->addTestResult('getInfo() & getAdminUrl()', $success);

    }

    /**
     * Test getBucketPath()
     */
    private function testGetBucketPath() {
        $this->sendTerminal("TEST: getBucketPath()");

        $tests = [
            ['/test', 'Path with leading slash'],
            ['test', 'Path without leading slash'],
            ['', 'Empty path'],
        ];

        $all_passed = true;
        foreach ($tests as $test) {
            $path = $this->bucket->getBucketPath($test[0]);
            $this->sendTerminal("  - {$test[1]}: {$path}");
            if (strpos($path, 'gs://') !== 0) {
                $all_passed = false;
            }
        }

        $this->addTestResult('getBucketPath()', $all_passed);
    }

    /**
     * Test getMimeTypeFromExtension()
     */
    private function testMimeTypeDetection() {
        $this->sendTerminal("TEST: MIME Type Detection");

        $extensions = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'mp4' => 'video/mp4',
            'unknown_ext' => 'application/octet-stream',
        ];

        $all_passed = true;
        foreach ($extensions as $ext => $expected_mime) {
            $mime = $this->bucket->getMimeTypeFromExtension($ext);
            $passed = ($mime === $expected_mime);
            $status = $passed ? 'OK' : 'FAIL';
            $this->sendTerminal("  - {$ext}: {$mime} [{$status}]");
            if (!$passed) {
                $all_passed = false;
            }
        }

        $this->addTestResult('getMimeTypeFromExtension()', $all_passed);
    }

    /**
     * Test mkdir()
     */
    private function testMkdir() {
        $this->sendTerminal("TEST: mkdir()");

        $result = $this->bucket->mkdir($this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('mkdir()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Created directory: {$this->test_base_path}");
        $this->addTestResult('mkdir()', $result === true);

    }

    /**
     * Test putContents()
     */
    private function testPutContents() {
        $this->sendTerminal("TEST: putContents()");

        $filename = 'test_file.txt';
        $content = 'Hello World from Buckets Test Script';

        $result = $this->bucket->putContents($filename, $content, $this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('putContents()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Created file: {$this->test_base_path}/{$filename}");
        $this->addTestResult('putContents()', $result === true);
    }

    /**
     * Test getContents()
     */
    private function testGetContents() {
        $this->sendTerminal("TEST: getContents()");

        $filename = 'test_file.txt';
        $expected_content = 'Hello World from Buckets Test Script';

        $content = $this->bucket->getContents($filename, $this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('getContents()', false, $this->bucket->errorMsg);
            return;
        }

        $passed = ($content === $expected_content);
        $this->sendTerminal("  - Retrieved content: " . substr($content, 0, 50) . "...");
        $this->addTestResult('getContents()', $passed);
    }

    /**
     * Test uploadContents()
     */
    private function testUploadContents() {
        $this->sendTerminal("TEST: uploadContents()");

        $filename = '/test_upload.json';
        $content = json_encode(['test' => 'data', 'timestamp' => time()]);

        $result = $this->bucket->uploadContents($this->test_base_path . $filename, $content);
        if ($this->bucket->error) {
            $this->addTestResult('uploadContents()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Uploaded file: {$this->test_base_path}{$filename}");
        $this->sendTerminal("  - File info: " . (isset($result['name']) ? $result['name'] : 'N/A'));

        $this->addTestResult('uploadContents()', isset($result['name']));
    }

    /**
     * Test isFile()
     */
    private function testIsFile() {
        $this->sendTerminal("TEST: isFile()");

        $existing_file = $this->test_base_path . '/test_file.txt';
        $non_existing_file = $this->test_base_path . '/non_existing.txt';

        $exists = $this->bucket->isFile($existing_file);
        $not_exists = !$this->bucket->isFile($non_existing_file);

        $this->sendTerminal("  - Existing file check: " . ($exists ? 'TRUE' : 'FALSE'));
        $this->sendTerminal("  - Non-existing file check: " . ($not_exists ? 'FALSE (correct)' : 'TRUE (incorrect)'));

        $this->addTestResult('isFile()', $exists && $not_exists);
    }

    /**
     * Test isDir()
     */
    private function testIsDir() {
        $this->sendTerminal("TEST: isDir()");

        $existing_dir = $this->test_base_path;
        $non_existing_dir = '/non_existing_dir_12345';

        $exists = $this->bucket->isDir($existing_dir);
        $not_exists = !$this->bucket->isDir($non_existing_dir);

        $this->sendTerminal("  - Existing dir check: " . ($exists ? 'TRUE' : 'FALSE'));
        $this->sendTerminal("  - Non-existing dir check: " . ($not_exists ? 'FALSE (correct)' : 'TRUE (incorrect)'));

        $this->addTestResult('isDir()', $exists && $not_exists);
    }

    /**
     * Test getFileInfo()
     */
    private function testGetFileInfo() {
        $this->sendTerminal("TEST: getFileInfo()");

        $file_path = $this->test_base_path . '/test_file.txt';

        $info = $this->bucket->getFileInfo($file_path);
        if ($this->bucket->error) {
            $this->addTestResult('getFileInfo()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - File name: " . ($info['name'] ?? 'N/A'));
        $this->sendTerminal("  - Content type: " . ($info['contentType'] ?? 'N/A'));
        $this->sendTerminal("  - Size: " . ($info['size'] ?? 'N/A') . " bytes");

        $this->addTestResult('getFileInfo()', isset($info['name']));
    }

    /**
     * Test copyFile()
     */
    private function testCopyFile() {
        $this->sendTerminal("TEST: copyFile()");

        $source = $this->test_base_path . '/test_file.txt';
        $target = $this->test_base_path . '/test_file_copy.txt';

        $result = $this->bucket->copyFile($source, $target);
        if ($this->bucket->error) {
            $this->addTestResult('copyFile()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Copied from: {$source}");
        $this->sendTerminal("  - Copied to: {$target}");

        $this->addTestResult('copyFile()', $result === true);
    }

    /**
     * Test moveFile()
     */
    private function testMoveFile() {
        $this->sendTerminal("TEST: moveFile()");

        $source = $this->test_base_path . '/test_file_copy.txt';
        $target = $this->test_base_path . '/test_file_moved.txt';

        $result = $this->bucket->moveFile($source, $target);
        if ($this->bucket->error) {
            $this->addTestResult('moveFile()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Moved from: {$source}");
        $this->sendTerminal("  - Moved to: {$target}");

        $this->addTestResult('moveFile()', $result === true);
    }

    /**
     * Test scan()
     */
    private function testScan() {
        $this->sendTerminal("TEST: scan()");

        $files = $this->bucket->scan($this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('scan()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Files found: " . count($files));
        foreach ($files as $name => $info) {
            $this->sendTerminal("    * {$name} [{$info['type']}]");
        }

        $this->addTestResult('scan()', is_array($files) && count($files) > 0);
    }

    /**
     * Test fastScan()
     */
    private function testFastScan() {
        $this->sendTerminal("TEST: fastScan()");

        $files = $this->bucket->fastScan($this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('fastScan()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Files found: " . count($files));

        $this->addTestResult('fastScan()', is_array($files) && count($files) > 0);
    }

    /**
     * Test deleteFile()
     */
    private function testDeleteFile() {
        $this->sendTerminal("TEST: deleteFile()");

        $files_to_delete = [
            $this->test_base_path . '/test_file.txt',
            $this->test_base_path . '/test_upload.json',
            $this->test_base_path . '/test_file_moved.txt',
        ];

        $all_deleted = true;
        foreach ($files_to_delete as $file) {
            $result = $this->bucket->deleteFile($file);
            if ($this->bucket->error || !$result) {
                $all_deleted = false;
                $this->sendTerminal("  - Failed to delete: {$file}");
            } else {
                $this->sendTerminal("  - Deleted: {$file}");
            }
        }

        $this->addTestResult('deleteFile()', $all_deleted);
    }

    /**
     * Test rmdir()
     */
    private function testRmdir() {
        $this->sendTerminal("TEST: rmdir()");

        $result = $this->bucket->rmdir($this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('rmdir()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Removed directory: {$this->test_base_path}");
        $this->addTestResult('rmdir()', $result === true);
    }

    /**
     * Test KMS initKMS() - reinitialize and verify no errors
     */
    private function testKMSInit() {
        $this->sendTerminal("TEST: KMS initKMS()");

        // Reset error state and reinitialize
        $this->bucket->error = false;
        $this->bucket->errorMsg = [];
        $result = $this->bucket->initKMS($this->kmsConfig);

        if ($this->bucket->error) {
            $this->addTestResult('initKMS()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Project: {$this->kmsConfig['projectId']}");
        $this->sendTerminal("  - Location: {$this->kmsConfig['location']}");
        $this->sendTerminal("  - KeyRing: {$this->kmsConfig['keyRing']}");
        $this->sendTerminal("  - KeyId: {$this->kmsConfig['keyId']}");

        $this->addTestResult('initKMS()', $result === true);
    }

    /**
     * Test KMS getKMSKeyName() - verify key name format
     */
    private function testKMSKeyName() {
        $this->sendTerminal("TEST: KMS getKMSKeyName()");

        $keyName = $this->bucket->getKMSKeyName();
        if ($this->bucket->error) {
            $this->addTestResult('getKMSKeyName()', false, $this->bucket->errorMsg);
            return;
        }

        $this->sendTerminal("  - Key name: {$keyName}");

        // Verify format: projects/{project}/locations/{location}/keyRings/{keyRing}/cryptoKeys/{keyId}
        $expectedPrefix = "projects/{$this->kmsConfig['projectId']}/locations/{$this->kmsConfig['location']}/keyRings/{$this->kmsConfig['keyRing']}/cryptoKeys/{$this->kmsConfig['keyId']}";
        $passed = ($keyName === $expectedPrefix);

        if (!$passed) {
            $this->sendTerminal("  - Expected: {$expectedPrefix}");
        }

        $this->addTestResult('getKMSKeyName()', $passed);
    }

    /**
     * Test KMS encryptLargeFile() and decryptLargeFile() round-trip
     */
    private function testKMSEncryptDecrypt() {
        $this->sendTerminal("TEST: KMS encryptLargeFile() + decryptLargeFile()");

        $originalContent = 'KMS encryption test content - ' . date('Y-m-d H:i:s') . ' - ' . bin2hex(random_bytes(16));
        $sourcePath = $this->test_base_path . '/kms_test_source.txt';
        $encryptedPath = $this->test_base_path . '/kms_test_encrypted.bin';
        $decryptedPath = $this->test_base_path . '/kms_test_decrypted.txt';

        // 1. Create source file
        $this->sendTerminal("  - Creating source file...");
        $this->bucket->putContents('kms_test_source.txt', $originalContent, $this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('KMS encrypt+decrypt', false, $this->bucket->errorMsg);
            return;
        }

        $sourceGsPath = $this->bucket->getBucketPath($sourcePath);
        $encryptedGsPath = $this->bucket->getBucketPath($encryptedPath);
        $decryptedGsPath = $this->bucket->getBucketPath($decryptedPath);

        // 2. Encrypt
        $this->sendTerminal("  - Encrypting file...");
        $encryptResult = $this->bucket->encryptLargeFile($sourceGsPath, $encryptedGsPath);
        if ($this->bucket->error || !$encryptResult) {
            $this->addTestResult('KMS encrypt+decrypt', false, $this->bucket->errorMsg ?: ['encryptLargeFile returned false']);
            return;
        }
        $this->sendTerminal("  - Encryption OK");

        // 3. Verify encrypted file exists and differs from original
        $encryptedContent = $this->bucket->getContents('kms_test_encrypted.bin', $this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('KMS encrypt+decrypt', false, ['Could not read encrypted file: ' . json_encode($this->bucket->errorMsg)]);
            return;
        }
        $this->sendTerminal("  - Encrypted file size: " . strlen($encryptedContent) . " bytes (original: " . strlen($originalContent) . " bytes)");

        $isDifferent = ($encryptedContent !== $originalContent);
        $this->sendTerminal("  - Encrypted content differs from original: " . ($isDifferent ? 'YES' : 'NO'));

        // 4. Decrypt
        $this->sendTerminal("  - Decrypting file...");
        $this->bucket->error = false;
        $this->bucket->errorMsg = [];
        $decryptResult = $this->bucket->decryptLargeFile($encryptedGsPath, $decryptedGsPath);
        if ($this->bucket->error || !$decryptResult) {
            $this->addTestResult('KMS encrypt+decrypt', false, $this->bucket->errorMsg ?: ['decryptLargeFile returned false']);
            return;
        }
        $this->sendTerminal("  - Decryption OK");

        // 5. Verify decrypted content matches original
        $decryptedContent = $this->bucket->getContents('kms_test_decrypted.txt', $this->test_base_path);
        if ($this->bucket->error) {
            $this->addTestResult('KMS encrypt+decrypt', false, ['Could not read decrypted file: ' . json_encode($this->bucket->errorMsg)]);
            return;
        }

        $contentMatches = ($decryptedContent === $originalContent);
        $this->sendTerminal("  - Decrypted content matches original: " . ($contentMatches ? 'YES' : 'NO'));

        if (!$contentMatches) {
            $this->sendTerminal("  - Original:  " . substr($originalContent, 0, 80));
            $this->sendTerminal("  - Decrypted: " . substr($decryptedContent, 0, 80));
        }

        $passed = $isDifferent && $contentMatches;
        $this->addTestResult('KMS encrypt+decrypt', $passed);

        // 6. Cleanup KMS test files
        $this->sendTerminal("  - Cleaning up KMS test files...");
        $this->bucket->deleteFile($sourcePath);
        $this->bucket->deleteFile($encryptedPath);
        $this->bucket->deleteFile($decryptedPath);
    }

    /**
     * Add test result
     */
    private function addTestResult($test_name, $success, $error = null) {
        $this->test_results[] = [
            'test' => $test_name,
            'success' => $success,
            'error' => $error
        ];

        $status = $success ? '✓ PASS' : '✗ FAIL';
        $this->sendTerminal("  [{$status}]");
        if (!$success && $error) {
            $this->sendTerminal("  ERROR: " . json_encode($error));
        }
        $this->sendTerminal("");
    }

    /**
     * Show summary
     */
    private function showSummary() {
        $this->sendTerminal("=== TEST SUMMARY ===");

        $total = count($this->test_results);
        $passed = 0;
        $failed = 0;

        foreach ($this->test_results as $result) {
            if ($result['success']) {
                $passed++;
            } else {
                $failed++;
            }
        }

        $this->sendTerminal("Total tests: {$total}");
        $this->sendTerminal("Passed: {$passed}");
        $this->sendTerminal("Failed: {$failed}");
        $this->sendTerminal("");

        if ($failed > 0) {
            $this->sendTerminal("Failed tests:");
            foreach ($this->test_results as $result) {
                if (!$result['success']) {
                    $this->sendTerminal("  - {$result['test']}");
                    if ($result['error']) {
                        $this->sendTerminal("    Error: " . json_encode($result['error']));
                    }
                }
            }
        }

        // Return test results as JSON
        $this->sendTerminal("");
        $this->sendTerminal("=== TEST RESULTS (JSON) ===");
        $this->sendTerminal([
            'results' => $this->test_results,
            'success' => ($failed === 0),
            'message' => 'Buckets Test Complete',
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
        ]);
    }

    /**
     * Cleanup - remove any remaining test files
     */
    private function cleanup() {
        $this->sendTerminal("=== CLEANUP ===");

        // Try to remove the test directory if it still exists
        if ($this->bucket->isDir($this->test_base_path)) {
            // Try to delete any remaining files
            $files = $this->bucket->fastScan($this->test_base_path);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $file_path = $this->test_base_path . '/' . $file;
                        if ($this->bucket->isFile($file_path)) {
                            $this->bucket->deleteFile($file_path);
                            $this->sendTerminal("  - Cleaned up: {$file_path}");
                        }
                    }
                }
            }

            // Try to remove the directory
            $this->bucket->rmdir($this->test_base_path);
            $this->sendTerminal("  - Removed test directory: {$this->test_base_path}");
        }

        $this->sendTerminal("Cleanup complete");
    }
}
