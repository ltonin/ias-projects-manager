<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectParticipant;
use DateTimeImmutable;

final class ProjectParticipantValidator
{
    /** @param array<string,mixed> $input @return array<string,string> */
    public function validate(array $input, Project $project, Person $person): array
    {
        $errors = [];
        $role = trim((string) ($input['project_role'] ?? ''));
        if (!array_key_exists($role, ProjectParticipant::ROLE_LABELS)) {
            $errors['project_role'] = 'Select a valid project role.';
        }
        $start = $this->date($input['participation_start'] ?? null, 'participation_start', $errors);
        $end = $this->date($input['participation_end'] ?? null, 'participation_end', $errors);
        if ($start !== null && $end !== null && $end < $start) {
            $errors['participation_end'] = 'Participation end must not precede participation start.';
        }
        if ($start !== null && $project->startDate !== null && $start < $project->startDate) {
            $errors['participation_start'] = 'Participation start must not precede the project start date.';
        }
        if ($end !== null && $project->endDate !== null && $end > $project->endDate) {
            $errors['participation_end'] = 'Participation end must not follow the project end date.';
        }
        if ($start !== null && $person->activeFrom !== null && $start < $person->activeFrom) {
            $errors['participation_start'] = 'Participation start must not precede the person association start date.';
        }
        if ($end !== null && $person->activeTo !== null && $end > $person->activeTo) {
            $errors['participation_end'] = 'Participation end must not follow the person association end date.';
        }
        if (mb_strlen(trim((string) ($input['notes'] ?? ''))) > 2000) {
            $errors['notes'] = 'Notes must not exceed 2,000 characters.';
        }
        return $errors;
    }

    /** @param array<string,string> $errors */
    private function date(mixed $value, string $field, array &$errors): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $errors[$field] = 'Enter a valid date.';
            return null;
        }
        return $date;
    }
}
