<?php

namespace App\Services;

/** Expected inventory validation/ownership conflict, safe to return to the client. */
class InventoryTransitionException extends \RuntimeException
{
}
