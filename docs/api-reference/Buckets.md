# Buckets Class

## Overview

The `Buckets` class provides a comprehensive interface to Google Cloud Storage (GCS), allowing you to manage files, directories, and objects in GCS buckets. It handles uploads, downloads, permissions, signed URLs, and more.

## Requirements

- Google Cloud Storage API enabled
- Service account with Storage Object Admin role
- Configuration in `config.json`:
  ```json
  {
    "core.datastorage.on": true,
    "core.gcp.datastorage.project_id": "your-project-id"
  }
  ```

## Basic Usage

```php
// Load Buckets class
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Check for errors
if($buckets->error) {
    echo "Error: " . implode(', ', $buckets->errorMsg);
    return;
}

// Upload file
$buckets->uploadFile('/path/in/bucket/file.pdf', '/local/path/file.pdf');

// Download file
$buckets->download('gs://my-bucket/file.pdf', '/local/destination.pdf');

// List files
$files = $buckets->scan('/documents/');

// Delete file
$buckets->deleteFile('/path/in/bucket/file.pdf');
```

## Constructor

```php
function __construct(Core7 &$core, $bucket = null, $options = [])
```

**Parameters:**
- `$bucket` (string): Bucket name in format `gs://bucket-name` or just `bucket-name`
- `$options` (array, optional): Additional options
  - `project_id` (string): GCP project ID
  - `keyFile` (array): Service account credentials

**Example:**
```php
// Simple initialization
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// With project ID
$buckets = $this->core->loadClass('Buckets', [
    'gs://my-bucket',
    ['project_id' => 'my-project']
]);

// With service account
$buckets = $this->core->loadClass('Buckets', [
    'gs://my-bucket',
    ['keyFile' => $serviceAccountArray]
]);
```

---

## File Upload Methods

### manageUploadFiles()

```php
function manageUploadFiles($path = '', $options = []): array|false
```

Manages files uploaded via `$_FILES` and moves them to Cloud Storage.

**Parameters:**
- `$path` (string): Destination path in bucket (must start with `/`)
- `$options` (array): Upload options
  - `public` (bool): Make files publicly accessible
  - `field_name` (string): Process only specific field name
  - `apply_hash_to_filenames` (bool): Rename files with hash to avoid duplicates
  - `allowed_extensions` (string): Comma-separated allowed extensions (e.g., 'jpg,pdf,png')
  - `allowed_content_types` (string): Comma-separated allowed content types (e.g., 'image,application/pdf')

**Returns:** Array of uploaded files with details, or `false` on error

**Example:**
```php
// In your API endpoint
public function ENDPOINT_upload()
{
    if(!isset($_FILES['documents'])) {
        return $this->setError('No files uploaded', 400);
    }

    $buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

    // Configure upload options
    $options = [
        'public' => true,
        'apply_hash_to_filenames' => true,
        'allowed_extensions' => 'jpg,png,pdf',
        'allowed_content_types' => 'image,application/pdf'
    ];

    // Upload files
    $uploaded = $buckets->manageUploadFiles('/uploads', $options);

    if($buckets->error) {
        return $this->setError(implode(', ', $buckets->errorMsg), 400);
    }

    $this->addReturnData($uploaded);
}
```

**Response Structure:**
```json
{
  "field_name": [
    {
      "name": "20221221042421_upload63a9cab5988ac.jpg",
      "type": "image/jpeg",
      "tmp_name": "/tmp/phpG9pBMz",
      "error": 0,
      "size": 1412041,
      "hash_from_name": "original-filename.jpg",
      "movedTo": "gs://my-bucket/uploads/20221221042421_upload63a9cab5988ac.jpg",
      "publicUrl": "https://storage.googleapis.com/my-bucket/uploads/file.jpg",
      "mediaLink": "https://storage.googleapis.com/download/storage/v1/b/..."
    }
  ]
}
```

---

### uploadFile()

```php
function uploadFile(string $filename_path, string $source_file_path, array $options = []): bool
```

Uploads a file from local filesystem to Cloud Storage.

**Parameters:**
- `$filename_path` (string): Destination path in bucket (with or without `gs://`)
- `$source_file_path` (string): Source file path on local filesystem
- `$options` (array): Upload options
  - `public` (bool): Make file publicly accessible
  - `metadata` (array): Custom metadata
  - `contentType` (string): Content-Type header
  - `cacheControl` (string): Cache-Control header

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Simple upload
$buckets->uploadFile('/documents/report.pdf', '/local/path/report.pdf');

// Upload with options
$buckets->uploadFile(
    '/images/photo.jpg',
    '/tmp/photo.jpg',
    [
        'public' => true,
        'metadata' => ['user_id' => '123', 'uploaded_at' => date('Y-m-d H:i:s')],
        'contentType' => 'image/jpeg',
        'cacheControl' => 'public, max-age=3600'
    ]
);

if($buckets->error) {
    echo "Upload failed: " . implode(', ', $buckets->errorMsg);
}
```

---

### uploadContents()

```php
function uploadContents(string $filename_path, string $contents, array $options = []): bool
```

Uploads content directly to Cloud Storage without using a local file.

**Parameters:**
- `$filename_path` (string): Destination path in bucket
- `$contents` (string): File contents
- `$options` (array): Upload options (same as `uploadFile`)

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Upload text content
$buckets->uploadContents(
    '/data/config.json',
    json_encode(['key' => 'value']),
    ['contentType' => 'application/json']
);

// Upload generated CSV
$csv = "Name,Email\nJohn,john@example.com\nJane,jane@example.com";
$buckets->uploadContents('/exports/users.csv', $csv);

// Upload image from memory
$imageData = file_get_contents('php://input');
$buckets->uploadContents('/uploads/image.jpg', $imageData, ['public' => true]);
```

---

### putContents()

```php
function putContents(string $filename, $data, string $path = '', array $options = []): bool
```

Alternative method to upload contents to a file.

**Parameters:**
- `$filename` (string): File name
- `$data` (string): File contents
- `$path` (string): Directory path in bucket
- `$options` (array): Upload options

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets->putContents('data.json', json_encode($data), '/exports/');
```

---

## File Download Methods

### getContents()

```php
function getContents($file, $path = ''): string|false
```

Downloads and returns file contents as a string.

**Parameters:**
- `$file` (string): File name or full path
- `$path` (string): Directory path (if $file is just filename)

**Returns:** File contents as string, or `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Get file contents
$contents = $buckets->getContents('/data/config.json');

if($contents) {
    $config = json_decode($contents, true);
}

// Alternative syntax
$contents = $buckets->getContents('config.json', '/data/');

// Download image
$imageData = $buckets->getContents('/images/photo.jpg');
if($imageData) {
    header('Content-Type: image/jpeg');
    echo $imageData;
}
```

---

### download()

```php
function download(string $source, string $destination): bool
```

Downloads a file from Cloud Storage to local filesystem.

**Parameters:**
- `$source` (string): Source file path in bucket (with or without `gs://`)
- `$destination` (string): Local destination path

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Download file
$buckets->download(
    'gs://my-bucket/documents/report.pdf',
    '/tmp/report.pdf'
);

// Download without gs:// prefix
$buckets->download(
    '/exports/data.csv',
    '/local/path/data.csv'
);

if($buckets->error) {
    echo "Download failed: " . implode(', ', $buckets->errorMsg);
}
```

---

## Directory Methods

### mkdir()

```php
public function mkdir(string $path): bool
```

Creates a directory in the bucket.

**Parameters:**
- `$path` (string): Directory path (must start with `/`)

**Returns:** `true` on success or if already exists, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Create directory
$buckets->mkdir('/uploads/2024/');
$buckets->mkdir('/documents/invoices/');

// Nested directories
$buckets->mkdir('/data/exports/monthly/2024/');
```

---

### rmdir()

```php
public function rmdir(string $path): bool
```

Removes a directory and all its contents.

**Parameters:**
- `$path` (string): Directory path

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Remove directory and all files in it
$buckets->rmdir('/temp/');
$buckets->rmdir('gs://my-bucket/old-data/');

if($buckets->error) {
    echo "Failed to remove directory";
}
```

---

### isDir()

```php
public function isDir($path = ''): bool
```

Checks if a path is a directory.

**Parameters:**
- `$path` (string): Directory path

**Returns:** `true` if directory exists, `false` otherwise

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

if($buckets->isDir('/uploads/')) {
    echo "Directory exists";
} else {
    $buckets->mkdir('/uploads/');
}
```

---

### scan()

```php
function scan(string $path = ''): array|false
```

Scans a directory and returns detailed file information.

**Parameters:**
- `$path` (string): Directory path to scan

**Returns:** Array of file objects with details, or `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Scan directory
$files = $buckets->scan('/documents/');

foreach($files as $file) {
    echo $file['name'];           // File name
    echo $file['size'];           // File size in bytes
    echo $file['timeCreated'];    // Creation timestamp
    echo $file['updated'];        // Last update timestamp
    echo $file['contentType'];    // MIME type
    echo $file['mediaLink'];      // Download URL
}

// Scan subdirectory
$images = $buckets->scan('/uploads/images/');
```

---

### fastScan()

```php
function fastScan(string $path = ''): array|false
```

Quickly scans a directory and returns just file names (faster than `scan()`).

**Parameters:**
- `$path` (string): Directory path to scan

**Returns:** Array of file names, or `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Quick scan for file names only
$fileNames = $buckets->fastScan('/documents/');

// Result: ['file1.pdf', 'file2.docx', 'file3.txt']

foreach($fileNames as $fileName) {
    echo $fileName . "\n";
}
```

---

## File Operations

### deleteFile()

```php
function deleteFile(string $filename_path): bool
```

Deletes a file from Cloud Storage.

**Parameters:**
- `$filename_path` (string): File path to delete

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Delete file
$buckets->deleteFile('/uploads/old-file.pdf');
$buckets->deleteFile('gs://my-bucket/temp/data.csv');

if($buckets->error) {
    echo "Delete failed";
}
```

---

### copyFile()

```php
function copyFile(string $filename_path_source, string $filename_path_target): bool
```

Copies a file to a new location.

**Parameters:**
- `$filename_path_source` (string): Source file path
- `$filename_path_target` (string): Destination file path

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Copy file
$buckets->copyFile(
    '/documents/report.pdf',
    '/backups/report-backup.pdf'
);

// Copy between directories
$buckets->copyFile(
    '/uploads/photo.jpg',
    '/archive/2024/photo.jpg'
);
```

---

### moveFile()

```php
function moveFile(string $filename_path_source, string $filename_path_target): bool
```

Moves a file to a new location (copy + delete).

**Parameters:**
- `$filename_path_source` (string): Source file path
- `$filename_path_target` (string): Destination file path

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Move file
$buckets->moveFile(
    '/temp/upload.pdf',
    '/documents/final.pdf'
);

// Rename file
$buckets->moveFile(
    '/uploads/IMG_001.jpg',
    '/uploads/profile-photo.jpg'
);
```

---

### isFile()

```php
function isFile(string $path): bool
```

Checks if a file exists.

**Parameters:**
- `$path` (string): File path

**Returns:** `true` if file exists, `false` otherwise

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

if($buckets->isFile('/documents/report.pdf')) {
    echo "File exists";
} else {
    echo "File not found";
}
```

---

## Permission Methods

### setFilePublic()

```php
function setFilePublic(string $file_path): bool
```

Makes a file publicly accessible.

**Parameters:**
- `$file_path` (string): File path

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Make file public
$buckets->setFilePublic('/documents/public-report.pdf');

// Get public URL
$publicUrl = $buckets->getPublicUrl('/documents/public-report.pdf');
```

---

### setFilePrivate()

```php
function setFilePrivate(string $file_path): bool
```

Makes a file private (removes public access).

**Parameters:**
- `$file_path` (string): File path

**Returns:** `true` on success, `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Make file private
$buckets->setFilePrivate('/documents/confidential.pdf');
```

---

## Signed URL Methods

### getSignedUploadUrl()

```php
function getSignedUploadUrl($upload_file_path, int $expiration_in_minutes = 15): string|false
```

Generates a signed URL for uploading files directly to Cloud Storage.

**Parameters:**
- `$upload_file_path` (string): Destination file path
- `$expiration_in_minutes` (int): URL expiration time (default: 15 minutes)

**Returns:** Signed URL string, or `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Generate upload URL
$uploadUrl = $buckets->getSignedUploadUrl('/uploads/user-file.pdf', 30);

// Return to client for direct upload
$this->addReturnData([
    'upload_url' => $uploadUrl,
    'expires_in' => 30 * 60 // seconds
]);

// Client-side (JavaScript)
// fetch(uploadUrl, {
//   method: 'PUT',
//   body: file,
//   headers: {'Content-Type': 'application/pdf'}
// });
```

---

### getSignedDownloadUrl()

```php
function getSignedDownloadUrl($filename_path, int $expiration_in_minutes = 1, $download_options = []): string|false
```

Generates a signed URL for downloading private files.

**Parameters:**
- `$filename_path` (string): File path
- `$expiration_in_minutes` (int): URL expiration time (default: 1 minute)
- `$download_options` (array): Additional options
  - `responseDisposition` (string): Content-Disposition header
  - `responseType` (string): Content-Type override

**Returns:** Signed URL string, or `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Simple signed URL
$downloadUrl = $buckets->getSignedDownloadUrl('/documents/report.pdf', 5);

// Force download with custom filename
$downloadUrl = $buckets->getSignedDownloadUrl(
    '/documents/report.pdf',
    10,
    ['responseDisposition' => 'attachment; filename="My-Report.pdf"']
);

// Return to client
$this->addReturnData([
    'download_url' => $downloadUrl,
    'expires_in' => 600 // seconds
]);
```

---

## Information Methods

### getPublicUrl()

```php
function getPublicUrl(string $file_path, string $content_type = ''): string|false
```

Gets the public URL for a file.

**Parameters:**
- `$file_path` (string): File path
- `$content_type` (string): Content-Type (optional)

**Returns:** Public URL string, or `false` if not public

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Get public URL
$url = $buckets->getPublicUrl('/images/photo.jpg');
// Returns: https://storage.googleapis.com/my-bucket/images/photo.jpg

// Use in response
$this->addReturnData([
    'image_url' => $url
]);
```

---

### getFileInfo()

```php
function getFileInfo(string $file_path): array|false
```

Gets detailed information about a file.

**Parameters:**
- `$file_path` (string): File path

**Returns:** File information array, or `false` on error

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

$info = $buckets->getFileInfo('/documents/report.pdf');

if($info) {
    echo $info['name'];           // File name
    echo $info['size'];           // Size in bytes
    echo $info['contentType'];    // MIME type
    echo $info['timeCreated'];    // Creation time
    echo $info['updated'];        // Last update time
    echo $info['md5Hash'];        // MD5 hash
    echo $info['crc32c'];         // CRC32C checksum
    echo $info['mediaLink'];      // Download link
    echo $info['metadata'];       // Custom metadata
}
```

---

### getInfo()

```php
function getInfo(): array
```

Gets information about the bucket.

**Returns:** Bucket information array

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

$info = $buckets->getInfo();

echo $info['name'];              // Bucket name
echo $info['location'];          // Bucket location
echo $info['storageClass'];      // Storage class
echo $info['timeCreated'];       // Creation time
```

---

### getBucketPath()

```php
public function getBucketPath(string $path): string
```

Converts a path to full bucket path format.

**Parameters:**
- `$path` (string): Relative or absolute path

**Returns:** Full bucket path (gs://bucket-name/path)

**Example:**
```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

$fullPath = $buckets->getBucketPath('/documents/file.pdf');
// Returns: gs://my-bucket/documents/file.pdf

$fullPath = $buckets->getBucketPath('gs://my-bucket/file.pdf');
// Returns: gs://my-bucket/file.pdf
```

---

### getMimeTypeFromExtension()

```php
public function getMimeTypeFromExtension(string $extension): string
```

Gets MIME type from file extension.

**Parameters:**
- `$extension` (string): File extension (with or without dot)

**Returns:** MIME type string

**Example:**
```php
$mime = $buckets->getMimeTypeFromExtension('pdf');
// Returns: application/pdf

$mime = $buckets->getMimeTypeFromExtension('.jpg');
// Returns: image/jpeg

$mime = $buckets->getMimeTypeFromExtension('json');
// Returns: application/json
```

---

## Error Handling

The Buckets class provides error information through properties:

```php
$buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

// Check for errors
if($buckets->error) {
    echo "Error Code: " . $buckets->errorCode . "\n";
    echo "Error Messages: " . implode(', ', $buckets->errorMsg) . "\n";
}

// Example with error handling
$result = $buckets->uploadFile('/path/file.pdf', '/local/file.pdf');

if($buckets->error) {
    $this->core->logs->add($buckets->errorMsg, 'buckets', 'error');
    return $this->setError('Upload failed', 500);
}
```

---

## Complete Examples

### File Upload API

```php
<?php
class API extends RESTful
{
    // POST /storage/upload
    public function ENDPOINT_upload()
    {
        if(!$this->checkMethod('POST')) return;

        // Check uploaded files
        if(!isset($_FILES['file'])) {
            return $this->setError('No file uploaded', 400);
        }

        // Load Buckets
        $buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);
        if($buckets->error) {
            return $this->setError('Storage initialization failed', 500);
        }

        // Configure upload
        $options = [
            'public' => true,
            'apply_hash_to_filenames' => true,
            'allowed_extensions' => 'jpg,png,pdf,docx',
            'allowed_content_types' => 'image,application/pdf,application/vnd.openxmlformats'
        ];

        // Upload files
        $uploaded = $buckets->manageUploadFiles('/uploads/' . date('Y/m/'), $options);

        if($buckets->error) {
            return $this->setError(implode(', ', $buckets->errorMsg), 400);
        }

        $this->setReturnStatus(201);
        $this->addReturnData($uploaded);
    }
}
```

### File Download API

```php
// GET /storage/download/{filename}
public function ENDPOINT_download()
{
    if(!$this->checkMethod('GET')) return;

    $filename = $this->checkMandatoryParam(1, 'Filename required');
    if(!$filename) return;

    $buckets = $this->core->loadClass('Buckets', ['gs://my-bucket']);

    // Check if file exists
    $filePath = '/documents/' . $filename;
    if(!$buckets->isFile($filePath)) {
        return $this->setError('File not found', 404);
    }

    // Generate signed download URL
    $downloadUrl = $buckets->getSignedDownloadUrl($filePath, 5, [
        'responseDisposition' => 'attachment; filename="' . $filename . '"'
    ]);

    if($buckets->error) {
        return $this->setError('Failed to generate download URL', 500);
    }

    // Redirect to signed URL
    header('Location: ' . $downloadUrl);
    exit;
}
```

### Image Gallery

```php
// GET /gallery/list
public function ENDPOINT_list()
{
    $buckets = $this->core->loadClass('Buckets', ['gs://my-images']);

    // Scan images directory
    $files = $buckets->scan('/gallery/');

    if($buckets->error) {
        return $this->setError('Failed to list files', 500);
    }

    // Filter images and add public URLs
    $images = [];
    foreach($files as $file) {
        if(strpos($file['contentType'], 'image/') === 0) {
            $images[] = [
                'name' => $file['name'],
                'size' => $file['size'],
                'url' => $buckets->getPublicUrl('/gallery/' . $file['name']),
                'created' => $file['timeCreated']
            ];
        }
    }

    $this->addReturnData(['images' => $images, 'total' => count($images)]);
}
```

---

## See Also

- [Getting Started Guide](../guides/getting-started.md)
- [GCP Integration Guide](../guides/gcp-integration.md)
- [DataStore Class](DataStore.md)
- [API Development Guide](../guides/api-development.md)
