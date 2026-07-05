<?php
/**
 * ==========================================================================
 * includes/validation.php
 * ==========================================================================
 * Pure validation functions — every function here returns true/false or a
 * normalized value, and never touches the database or session directly
 * (except is_strong_password/etc. which are stateless). Persisting
 * "old input" for re-populating forms is handled by set_old_input() in
 * functions.php, called from the page/controller, not from here.
 * ==========================================================================
 */

declare(strict_types=1);

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 150;
}

/**
 * is_valid_phone()
 *
 * Accepts Bangladeshi mobile numbers in local (01XXXXXXXXX) or international
 * (+8801XXXXXXXXX) format, matching users.phone / addresses.phone varchar(20).
 */
function is_valid_phone(string $phone): bool
{
    $phone = trim($phone);
    return (bool) preg_match('/^(\+8801|01)[3-9]\d{8}$/', $phone);
}

/**
 * is_strong_password()
 *
 * At least 8 characters, one letter and one number. Deliberately not
 * overly restrictive (no forced special-character rules that push users
 * toward predictable substitutions).
 */
function is_strong_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

function is_not_empty(string $value): bool
{
    return trim($value) !== '';
}

function is_valid_length(string $value, int $min, int $max): bool
{
    $len = mb_strlen(trim($value));
    return $len >= $min && $len <= $max;
}

function is_positive_number(mixed $value): bool
{
    return is_numeric($value) && (float) $value > 0;
}

function is_non_negative_number(mixed $value): bool
{
    return is_numeric($value) && (float) $value >= 0;
}

function is_valid_integer_id(mixed $value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0;
}

/**
 * is_valid_rating() — product_reviews.rating CHECK (1..5).
 */
function is_valid_rating(mixed $value): bool
{
    $int = filter_var($value, FILTER_VALIDATE_INT);
    return $int !== false && $int >= 1 && $int <= 5;
}

/**
 * Validator
 *
 * Small fluent-ish helper so pages like register.php / checkout.php don't
 * need a dozen scattered if-statements. Collects field-level errors and
 * exposes them via errors()/hasErrors()/first().
 *
 * Usage:
 *   $v = new Validator();
 *   $v->required('full_name', $fullName, 'Full name is required.');
 *   $v->email('email', $email);
 *   if ($v->hasErrors()) { ... }
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    public function required(string $field, string $value, string $message): static
    {
        if (!is_not_empty($value)) {
            $this->errors[$field] ??= $message;
        }
        return $this;
    }

    public function email(string $field, string $value, string $message = 'Please enter a valid email address.'): static
    {
        if (is_not_empty($value) && !is_valid_email($value)) {
            $this->errors[$field] ??= $message;
        }
        return $this;
    }

    public function phone(string $field, string $value, string $message = 'Please enter a valid phone number (e.g. 01XXXXXXXXX).'): static
    {
        if (is_not_empty($value) && !is_valid_phone($value)) {
            $this->errors[$field] ??= $message;
        }
        return $this;
    }

    public function length(string $field, string $value, int $min, int $max, ?string $message = null): static
    {
        if (!is_valid_length($value, $min, $max)) {
            $this->errors[$field] ??= $message ?? "Must be between {$min} and {$max} characters.";
        }
        return $this;
    }

    public function password(string $field, string $value, string $message = 'Password must be at least 8 characters and include a letter and a number.'): static
    {
        if (!is_strong_password($value)) {
            $this->errors[$field] ??= $message;
        }
        return $this;
    }

    public function matches(string $field, string $value, string $other, string $message): static
    {
        if ($value !== $other) {
            $this->errors[$field] ??= $message;
        }
        return $this;
    }

    public function custom(string $field, bool $isValid, string $message): static
    {
        if (!$isValid) {
            $this->errors[$field] ??= $message;
        }
        return $this;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }
}
