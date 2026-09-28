<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * Comment remplacer la valeur d'un champ au moment de l'anonymisation.
 *
 * How to replace the value of a field when anonymising.
 */
enum Strategy: string
{
    /** Choisit d'après le nom du champ : "mail" -> Email, sinon Redact. */
    case Auto = 'auto';

    /** Met null. Le champ doit accepter null. */
    case Nullify = 'nullify';

    /** Met un texte fixe, "[removed]" par défaut. */
    case Redact = 'redact';

    /** Met une chaîne vide. */
    case EmptyText = 'empty';

    /** Met "Anonymous". */
    case Text = 'text';

    /** Met une adresse unique et invalide, par exemple anonymous-42@anonymous.invalid. */
    case Email = 'email';

    /** Remplace par une empreinte : la valeur ne revient pas, mais deux valeurs égales le restent. */
    case Hash = 'hash';

    /** Met zéro. */
    case Zero = 'zero';

    /** Garde seulement l'année d'une date (1er janvier). */
    case YearOnly = 'year_only';
}
