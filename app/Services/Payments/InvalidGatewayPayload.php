<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * A signed webhook whose body does not match the expected format.
 */
class InvalidGatewayPayload extends RuntimeException {}
