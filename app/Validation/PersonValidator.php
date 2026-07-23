<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\Person;
use DateTimeImmutable;

final class PersonValidator
{
    /** @param array<string, mixed> $input @return array<string, string> */
    public function validate(array $input): array
    {
        $errors = [];
        foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value === '') {
                $errors[$field] = $label . ' is required.';
            } elseif (strlen($value) > 100) {
                $errors[$field] = $label . ' must not exceed 100 characters.';
            }
        }
        $email = trim((string) ($input['institutional_email'] ?? ''));
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
            $errors['institutional_email'] = 'Enter a valid institutional email address.';
        }
        foreach (['affiliation' => 255, 'notes' => 2000] as $field => $maximum) {
            if (strlen(trim((string) ($input[$field] ?? ''))) > $maximum) {
                $errors[$field] = sprintf('%s must not exceed %d characters.', ucfirst($field), $maximum);
            }
        }
        if (!array_key_exists((string) ($input['position_type'] ?? ''), Person::POSITION_LABELS)) {
            $errors['position_type'] = 'Select a valid position type.';
        }
        foreach (['is_internal', 'is_active'] as $field) {
            if (!in_array((string) ($input[$field] ?? ''), ['0', '1'], true)) {
                $errors[$field] = 'Select a valid value.';
            }
        }
        foreach (['active_from', 'active_to'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && !$this->validDate($value)) {
                $errors[$field] = 'Enter a valid date.';
            }
        }
        if (!isset($errors['active_from']) && !isset($errors['active_to'])) {
            $from = trim((string) ($input['active_from'] ?? ''));
            $to = trim((string) ($input['active_to'] ?? ''));
            if ($from !== '' && $to !== '' && $to < $from) {
                $errors['active_to'] = 'Active-to date must not precede active-from date.';
            }
        }
        $userId = trim((string) ($input['user_id'] ?? ''));
        if ($userId !== '' && filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors['user_id'] = 'Select a valid linked user.';
        }
        $capacity=trim((string)($input['default_monthly_capacity_hours']??'125.00'));
        if(preg_match('/^(?:0|[1-9]\\d{0,5})(?:\\.\\d{1,2})?$/',$capacity)!==1)$errors['default_monthly_capacity_hours']='Standard Monthly Capacity must be between 0.00 and 999999.99 with no more than two decimal places.';
        return $errors;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
