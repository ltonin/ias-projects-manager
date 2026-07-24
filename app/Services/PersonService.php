<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Models\PersonPage;
use App\Repositories\PersonRepository;
use App\Validation\PersonValidator;
use App\Validation\UserValidator;

final class PersonService
{
    public const DEFAULT_PER_PAGE = 25;
    public const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly PersonRepository $people,
        private readonly PersonValidator $validator,
    ) {
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function validate(array $input, ?int $exceptId = null): array
    {
        $errors = $this->validator->validate($input);
        $email = $this->nullable(UserValidator::normalizeEmail((string) ($input['institutional_email'] ?? '')));
        if ($email !== null && !isset($errors['institutional_email']) && $this->people->emailExists($email, $exceptId)) {
            $errors['institutional_email'] = 'That institutional email is already in use.';
        }
        $userId = $this->userId($input['user_id'] ?? null);
        if ($userId !== null && !isset($errors['user_id'])) {
            if (!$this->people->userExists($userId)) {
                $errors['user_id'] = 'The selected user no longer exists.';
            } elseif ($this->people->userIsLinked($userId, $exceptId)) {
                $errors['user_id'] = 'That user is already linked to another person.';
            }
        }
        return $errors;
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): Person
    {
        return $this->people->create($this->normalize($input));
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): Person
    {
        return $this->people->update($id, $this->normalize($input));
    }

    public function setActive(int $id, bool $active): Person
    {
        return $this->people->setActive($id, $active);
    }

    /** @param array<string, mixed> $query
     * @return array{page:PersonPage,filters:array{search:string,active:string,internal:string,position_type:string,linked:string}}
     */
    public function listing(array $query): array
    {
        $filters = [
            'search' => mb_substr(trim((string) ($query['search'] ?? '')), 0, 200),
            'active' => in_array((string) ($query['active'] ?? 'active'), ['active', 'inactive', 'all'], true) ? (string) ($query['active'] ?? 'active') : 'active',
            'internal' => in_array((string) ($query['internal'] ?? 'all'), ['internal', 'external', 'all'], true) ? (string) ($query['internal'] ?? 'all') : 'all',
            'position_type' => array_key_exists((string) ($query['position_type'] ?? ''), Person::POSITION_LABELS) ? (string) $query['position_type'] : '',
            'linked' => in_array((string) ($query['linked'] ?? 'all'), ['linked', 'unlinked', 'all'], true) ? (string) ($query['linked'] ?? 'all') : 'all',
        ];
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $perPage = filter_var($query['per_page'] ?? self::DEFAULT_PER_PAGE, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_PER_PAGE]]) ?: self::DEFAULT_PER_PAGE;
        $result = $this->people->search($filters, (int) $page, (int) $perPage);
        if ($result->total > 0 && $page > $result->pageCount()) {
            $result = $this->people->search($filters, $result->pageCount(), (int) $perPage);
        }
        return ['page' => $result, 'filters' => $filters];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function normalize(array $input): array
    {
        return [
            'user_id' => $this->userId($input['user_id'] ?? null),
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'institutional_email' => $this->nullable(UserValidator::normalizeEmail((string) ($input['institutional_email'] ?? ''))),
            'affiliation' => $this->nullable(trim((string) ($input['affiliation'] ?? ''))),
            'position_type' => (string) ($input['position_type'] ?? ''),
            'is_internal' => (string) ($input['is_internal'] ?? '') === '1',
            'active_from' => $this->nullable(trim((string) ($input['active_from'] ?? ''))),
            'active_to' => $this->nullable(trim((string) ($input['active_to'] ?? ''))),
            'is_active' => (string) ($input['is_active'] ?? '') === '1',
            'default_monthly_capacity_hours'=>$this->canonicalDecimal((string)($input['default_monthly_capacity_hours']??'125.00')),
            'annual_capacity_hours'=>$this->canonicalDecimal((string)($input['annual_capacity_hours']??Person::defaultAnnualCapacity((string)($input['position_type']??'')))),
            'notes' => $this->nullable(trim((string) ($input['notes'] ?? ''))),
        ];
    }

    private function nullable(string $value): ?string { return $value === '' ? null : $value; }
    private function canonicalDecimal(string$value):string{$value=trim($value);if(!str_contains($value,'.'))return$value.'.00';return strlen(substr(strrchr($value,'.')?:'',1))===1?$value.'0':$value;}
    private function userId(mixed $value): ?int
    {
        $string = trim((string) $value);
        return $string === '' ? null : (int) $string;
    }
}
