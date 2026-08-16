# Secure PDF uploads

The PDF upload boundary accepts one PSR-7 uploaded file and either returns private stored-file metadata or a stable localized failure code. The browser-facing form is introduced separately; this contract contains the security-sensitive file handling.

## Validation order

The service applies these controls in order:

1. map the PHP upload error without returning transport or filesystem details;
2. reject missing, path-like, control-character, and non-PDF client names;
3. enforce the configured size against both PSR-7 metadata and the moved file;
4. use the shared private-upload storage boundary and reject storage inside the public webroot;
5. generate a 128-bit random lowercase hexadecimal filename with a `.pdf` suffix;
6. move the upload to private storage without using any client path component;
7. confirm the resolved destination remains inside the configured storage root;
8. detect `application/pdf` with server-side Fileinfo rather than trusting the client MIME;
9. inspect a bounded header/trailer window for a supported PDF header, terminal `startxref`/`%%EOF`, a bounded cross-reference offset, and an `xref` table or cross-reference stream object.

Any failure after the move deletes the private copy immediately. The success result contains the random stored name, private absolute path, original display name, actual byte size, and detected media type. It never contains document bytes.

## Limits of structural inspection

The bounded inspector rejects common truncation, mislabeled content, trailing payloads, invalid cross-reference offsets, and malformed signatures without parsing arbitrary document objects. It is not a PDF renderer, repair tool, malware scanner, or proof that every object is semantically safe.

Easy Print passes accepted files to CUPS for printing; it does not render PDFs in the browser. Deployments with stronger threat models can add malware scanning as another isolated adapter before submission without changing the upload result contract.

## Storage lifecycle

`TEMPORARY_PATH` must resolve outside `public/`. In Docker it is a private tmpfs available only to the web service. A later submission slice must delete the accepted file immediately after CUPS confirms spool acceptance. The cleanup task will remove abandoned files after `TEMP_FILE_TTL_SECONDS`.

Only metadata and sanitized error codes may enter SQLite or logs. Never log document content, the private path, raw parser output, or an unsanitized client filename.

## Failure localization

Every `PdfUploadFailure` value maps to matching Portuguese and English catalog entries. User messages distinguish actionable categories such as missing file, maximum size, extension, detected MIME, invalid structure, transport failure, and unavailable safe storage while hiding exception details.
