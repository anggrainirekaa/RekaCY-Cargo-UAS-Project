<?php

namespace App\Exceptions;

use RuntimeException;

/** Raised when a request is syntactically valid but violates a business rule (rendered as HTTP 422). */
class BusinessRuleException extends RuntimeException
{
}
