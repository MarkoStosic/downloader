## Download Library

This library downloads public files from Google Drive, OneDrive, and direct HTTP/HTTPS URLs and returns Symfony UploadedFile instances.

### Installation

```bash
composer install
```

### Usage

```php
use CodingTask\Download\Downloader;

$downloader = new Downloader();
$file = $downloader->download('https://example.com/file.zip');
```

### Extensibility
Implement `CodingTask\\Download\\Contract\\ProviderInterface` and add your provider to the registry.

See `docs/EXPLANATION.md` for details.
