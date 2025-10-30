## Architecture Overview

- Providers implement `ProviderInterface` with `supports(url)` and `download(url)`.
- `ProviderRegistry` selects the first provider that canHandle a URL (Strategy pattern).
- `Downloader` is a simple facade that resolves a provider and returns an `UploadedFile`.
// Removed `TemporaryFileFactory` after switching to direct stream-to-disk in providers.
- Exceptions: `DownloadException` (base), `UnsupportedUrlException`, `ProviderErrorException`, `NetworkException`.

### Why this structure
It cleanly separates concerns: URL recognition vs. download logic per provider. Adding providers does not change existing ones (Open/Closed principle) and is testable in isolation.

### Design patterns
- Strategy (provider selection),
- Facade (`Downloader`),
- Factory (temporary file creation),
- SRP across classes.

## Implementation Details

### URL handling
- Direct URLs: any `http(s)` URL that is not Google Drive or OneDrive.
- Google Drive: supports `/file/d/{id}/view`, `?id=...`, and `uc?export=download&id=...`. Handles confirm token for large files.
- OneDrive: supports `1drv.ms` and `onedrive.live.com` public links; appends `download=1` and follows redirects.

### Error handling
- Non-2xx HTTP → `NetworkException`.
- Provider URL parsing/flow errors → `ProviderErrorException`.
- No provider match → `UnsupportedUrlException`.

## Future Considerations

### Add Dropbox support
Create `DropboxProvider` implementing `ProviderInterface`, parse shared link formats, and resolve to a direct download (often `?dl=1`). Register it in `ProviderRegistry`.

### Improvements
- Implemented stream-to-disk writing to reduce memory usage for large files.
- Retry with backoff on transient network errors.
- Richer filename/extension inference via `symfony/mime`.

### Private files
Inject authenticated `HttpClientInterface` instances or provider-specific OAuth clients. Extend providers to accept tokens or credentials and add auth headers accordingly.
