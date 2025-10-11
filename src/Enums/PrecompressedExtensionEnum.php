<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Enums;

/**
 * Known file extensions that are typically already compressed and should be skipped.
 */
enum PrecompressedExtensionEnum: string
{
    // Images
    case Png = 'png';
    case Jpg = 'jpg';
    case Jpeg = 'jpeg';
    case Gif = 'gif';
    case Webp = 'webp';
    case Avif = 'avif';

    // Fonts
    case Woff = 'woff';
    case Woff2 = 'woff2';
    case Ttf = 'ttf';
    case Eot = 'eot';

    // Video/Audio
    case Mp4 = 'mp4';
    case Webm = 'webm';
    case Ogg = 'ogg';
    case Mp3 = 'mp3';
    case Flac = 'flac';

    // Archives
    case Zip = 'zip';
    case Gz = 'gz';
    case Br = 'br';
    case Zst = 'zst';
    case SevenZ = '7z';
    case Rar = 'rar';

    // Other
    case Pdf = 'pdf';
    case Ico = 'ico';

    /**
     * @return list<string> Default set of extensions to skip.
     */
    public static function defaults(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
