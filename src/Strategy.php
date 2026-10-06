<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * How to replace the value of a field when anonymising.
 */
enum Strategy: string
{
    /** Chooses from the field name: "mail" -> Email, otherwise Redact. */
    case Auto = 'auto';

    /** Sets null. The field must accept null. */
    case Nullify = 'nullify';

    /** Sets a fixed text, "[removed]" by default. */
    case Redact = 'redact';

    /** Sets an empty string. */
    case EmptyText = 'empty';

    /** Sets "Anonymous". */
    case Text = 'text';

    /** Sets a unique and invalid address, for example anonymous-42@anonymous.invalid. */
    case Email = 'email';

    /** Replaces with a hash: the value cannot be recovered, but two equal values stay equal. */
    case Hash = 'hash';

    /** Sets zero. */
    case Zero = 'zero';

    /** Keeps only the year of a date (1st of January). */
    case YearOnly = 'year_only';
}
