<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

/**
 * Machine-readable error codes for compression exceptions
 */
enum ErrorCode: int
{
    case UNKNOWN_ALGORITHM      = 1001;
    case ALGORITHM_UNAVAILABLE  = 1002;
    case LEVEL_OUT_OF_RANGE     = 1003;
    case FILE_NOT_FOUND         = 1004;
    case FILE_NOT_READABLE      = 1005;
    case PAYLOAD_TOO_LARGE      = 1006;
    case COMPRESSION_FAILED     = 1007;
    case DECOMPRESSION_FAILED   = 1008;
    case DUPLICATE_IDENTIFIER   = 1009;
    case ITEM_NOT_FOUND         = 1010;
    case INVALID_ALGORITHM_SPEC = 1011;
    case EMPTY_ALGORITHMS       = 1012;
    case INVALID_PAYLOAD        = 1013;
    case NO_ITEMS               = 1014;
    case INVALID_LEVEL_TYPE     = 1015;
}
