# Secure PNG and JPEG uploads

The image upload boundary accepts PNG, JPEG, and JPG client filenames. It returns either private stored-file metadata or a stable localized failure code. The browser form and CUPS submission flow are separate slices; this contract owns format validation and resource limits.

## Validation order

The service applies these controls in order:

1. map the PHP upload error without exposing transport or filesystem details;
2. reject missing, path-like, control-character, and unsupported client names;
3. enforce `UPLOAD_MAX_BYTES` against both PSR-7 metadata and the moved file;
4. store the file under a random 128-bit name outside `public/`;
5. detect the media type with server-side Fileinfo and require it to agree with the extension;
6. read dimensions and require the detected image type to match PNG or JPEG;
7. enforce the width, height, and total-pixel limits before allocating a decoded image;
8. require the PNG `IEND` chunk or JPEG end-of-image marker to be at the physical end of the file;
9. decode the image with GD and confirm that its decoded dimensions match the inspected dimensions.

Any failure after the move deletes the private copy immediately. `.jpeg` and `.jpg` inputs use a canonical random `.jpg` storage suffix. The client-provided MIME value is never trusted.

## Resource limits

`IMAGE_MAX_WIDTH`, `IMAGE_MAX_HEIGHT`, and `IMAGE_MAX_PIXELS` are independent safety limits. The total-pixel check runs before GD decoding because a small compressed file can require much more memory when decoded. Operators should lower these values when the web container has a strict memory budget and raise them only after measuring the intended print workload.

The default limits accept images up to 16,384 pixels on either side and 50 million total pixels. They do not resize an image or change its quality.

## Orientation and byte preservation

Easy Print preserves accepted image bytes exactly. It does not auto-rotate or rewrite JPEG EXIF orientation metadata. The stored width and height describe the encoded pixel matrix, and the original file is passed to CUPS unchanged. Therefore, the selected CUPS orientation and the print pipeline remain authoritative.

Automatic EXIF normalization would be an image-editing feature, would change document bytes, and is outside this slice. If introduced later, it must be explicit in the UI and covered by format-specific quality tests.

## Polyglot-like and malformed content

GD decoding alone may accept a valid image followed by unrelated bytes. Requiring the format terminator at the physical end rejects that common polyglot-like construction. Fileinfo, terminator checks, and GD decoding are complementary controls; none is used as the only proof of validity.

Easy Print does not render uploaded images in the browser. Accepted files remain private until CUPS submission, are deleted after spool acceptance, and are covered by the abandoned-file cleanup policy.
