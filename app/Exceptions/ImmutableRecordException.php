<?php

namespace App\Exceptions;

use LogicException;

/** Thrown when code tries to change or delete a record that must never change. */
class ImmutableRecordException extends LogicException {}
