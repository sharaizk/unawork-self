<?php

namespace App\Services\PayHere;

use RuntimeException;

/**
 * A checkout could not be started. The message is already translated and safe to
 * show to the user.
 */
class PayHereCheckoutException extends RuntimeException
{
}
