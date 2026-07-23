<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicateProjectParticipantException;
use App\Models\ParticipantPersonOption;
use App\Models\Person;
use App\Models\ProjectParticipant;
use App\Models\ProjectParticipantPage;
use DateTimeImmutable;
use PDO;
use PDOException;

final class PdoProjectParticipantRepository implements ProjectParticipantRepository
{
    private ?PDO $pdo = null;
    public function __construct(private readonly ConnectionFactory $connections) {}

    public function findById(int $id): ?ProjectParticipant
    {
        $statement = $this->connection()->prepare($this->selectSql() . ' WHERE pp.id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByProjectAndPerson(int $projectId, int $personId): ?ProjectParticipant
    {
        $statement = $this->connection()->prepare($this->selectSql() . ' WHERE pp.project_id = :project_id AND pp.person_id = :person_id');
        $statement->execute(['project_id' => $projectId, 'person_id' => $personId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function listForProject(int $projectId, array $filters, int $page, int $perPage): ProjectParticipantPage
    {
        [$filterSql, $parameters] = $this->filters($filters);
        $parameters = ['project_id' => $projectId] + $parameters;
        $where = ' WHERE pp.project_id = :project_id' . $filterSql;
        $joins = ' FROM project_participants pp JOIN people pe ON pe.id = pp.person_id LEFT JOIN users u ON u.id = pe.user_id JOIN projects pr ON pr.id = pp.project_id';
        $count = $this->connection()->prepare('SELECT COUNT(*)' . $joins . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $statement = $this->connection()->prepare(
            $this->selectSql() . $where . ' ORDER BY pp.is_active DESC, pe.last_name ASC, pe.first_name ASC, pp.id ASC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) $statement->bindValue(':' . $key, $value);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        return new ProjectParticipantPage(
            array_map(fn (array $row): ProjectParticipant => $this->hydrate($row)->withoutNotes(), $statement->fetchAll()),
            $total, $page, $perPage,
        );
    }

    public function summaryForProject(int $projectId, int $limit = 5): array
    {
        $statement = $this->connection()->prepare(
            $this->selectSql() . ' WHERE pp.project_id = :project_id
             ORDER BY pp.is_active DESC, pe.last_name ASC, pe.first_name ASC, pp.id ASC LIMIT :limit'
        );
        $statement->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn (array $row): ProjectParticipant => $this->hydrate($row)->withoutNotes(), $statement->fetchAll());
    }
    public function allForProject(int$p):array{$s=$this->connection()->prepare($this->selectSql().' WHERE pp.project_id=:p ORDER BY pe.last_name,pe.first_name,pp.id');$s->execute(['p'=>$p]);return array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll());}

    public function countForProject(int $projectId, ?bool $active = null): int
    {
        $sql = 'SELECT COUNT(*) FROM project_participants WHERE project_id = :project_id';
        $parameters = ['project_id' => $projectId];
        if ($active !== null) {
            $sql .= ' AND is_active = :active';
            $parameters['active'] = $active ? 1 : 0;
        }
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    public function create(array $data, ?int $requiredManagerPersonId = null): ProjectParticipant
    {
        $pdo=$this->connection();$pdo->beginTransaction();
        try {
            $project=$pdo->prepare('SELECT manager_person_id FROM projects WHERE id=:id FOR UPDATE');$project->execute(['id'=>$data['project_id']]);$row=$project->fetch();
            if(!is_array($row))throw new \OutOfBoundsException('Project not found.');
            if($requiredManagerPersonId!==null&&(int)$row['manager_person_id']!==$requiredManagerPersonId)throw new AuthorizationException('Project ownership changed.');
            $person=$pdo->prepare('SELECT is_active FROM people WHERE id=:id FOR UPDATE');$person->execute(['id'=>$data['person_id']]);$personRow=$person->fetch();
            if(!is_array($personRow)||(int)$personRow['is_active']!==1)throw new \InvalidArgumentException('Select an active eligible person.');
            $duplicate=$pdo->prepare('SELECT id FROM project_participants WHERE project_id=:project_id AND person_id=:person_id FOR UPDATE');$duplicate->execute(['project_id'=>$data['project_id'],'person_id'=>$data['person_id']]);
            if($duplicate->fetchColumn()!==false)throw new DuplicateProjectParticipantException('That person already participates in this project.');
            $statement=$pdo->prepare('INSERT INTO project_participants(project_id,person_id,project_role,participation_start,participation_end,is_active,notes)
                VALUES(:project_id,:person_id,:project_role,:participation_start,:participation_end,:is_active,:notes)');
            $statement->execute($this->parameters($data));$id=(int)$pdo->lastInsertId();$pdo->commit();
        } catch (PDOException $exception) {
            if($pdo->inTransaction())$pdo->rollBack();
            $this->translateConstraint($exception);
            throw $exception;
        }catch(\Throwable$exception){
            if($pdo->inTransaction())$pdo->rollBack();throw$exception;
        }
        return $this->findById($id)
            ?? throw new \RuntimeException('Created participant could not be loaded.');
    }

    public function update(int $id, int $projectId, array $data, ?int $requiredManagerPersonId = null): ProjectParticipant
    {
        $sql = 'UPDATE project_participants pp JOIN projects pr ON pr.id = pp.project_id
            SET pp.project_role=:project_role,pp.participation_start=:participation_start,
                pp.participation_end=:participation_end,pp.is_active=:is_active,pp.notes=:notes
            WHERE pp.id=:id AND pp.project_id=:project_id';
        $parameters = ['id' => $id, 'project_id' => $projectId] + $this->editableParameters($data);
        if ($requiredManagerPersonId !== null) {
            $sql .= ' AND pr.manager_person_id=:required_manager';
            $parameters['required_manager'] = $requiredManagerPersonId;
        }
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        if ($statement->rowCount() === 0) $this->assertWriteTarget($id, $projectId, $requiredManagerPersonId);
        return $this->findById($id) ?? throw new \OutOfBoundsException('Participant not found.');
    }

    public function setActive(int $id, int $projectId, bool $active, ?int $requiredManagerPersonId = null): ProjectParticipant
    {
        $sql = 'UPDATE project_participants pp JOIN projects pr ON pr.id=pp.project_id SET pp.is_active=:active
            WHERE pp.id=:id AND pp.project_id=:project_id';
        $parameters = ['active' => $active ? 1 : 0, 'id' => $id, 'project_id' => $projectId];
        if ($requiredManagerPersonId !== null) {
            $sql .= ' AND pr.manager_person_id=:required_manager';
            $parameters['required_manager'] = $requiredManagerPersonId;
        }
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        if ($statement->rowCount() === 0) $this->assertWriteTarget($id, $projectId, $requiredManagerPersonId);
        return $this->findById($id) ?? throw new \OutOfBoundsException('Participant not found.');
    }

    public function delete(int $id, int $projectId, ?int $requiredManagerPersonId = null): void
    {
        $sql = 'DELETE pp FROM project_participants pp JOIN projects pr ON pr.id=pp.project_id
            WHERE pp.id=:id AND pp.project_id=:project_id';
        $parameters = ['id' => $id, 'project_id' => $projectId];
        if ($requiredManagerPersonId !== null) {
            $sql .= ' AND pr.manager_person_id=:required_manager';
            $parameters['required_manager'] = $requiredManagerPersonId;
        }
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        if ($statement->rowCount() !== 1) $this->assertWriteTarget($id, $projectId, $requiredManagerPersonId);
    }

    public function personAlreadyParticipates(int $projectId, int $personId, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM project_participants WHERE project_id=:project_id AND person_id=:person_id';
        $parameters = ['project_id' => $projectId, 'person_id' => $personId];
        if ($exceptId !== null) {
            $sql .= ' AND id<>:id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }

    public function availablePeople(int $projectId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT pe.id,pe.first_name,pe.last_name,pe.position_type,pe.affiliation,pe.institutional_email,
                    pe.is_active,pe.active_from,pe.active_to,u.username
             FROM people pe LEFT JOIN users u ON u.id=pe.user_id
             LEFT JOIN project_participants pp ON pp.person_id=pe.id AND pp.project_id=:project_id
             WHERE pp.id IS NULL AND pe.is_active=1 ORDER BY pe.last_name,pe.first_name,pe.id'
        );
        $statement->execute(['project_id' => $projectId]);
        return array_map(static fn (array $row): ParticipantPersonOption => new ParticipantPersonOption(
            (int) $row['id'], (string) $row['first_name'] . ' ' . (string) $row['last_name'],
            Person::POSITION_LABELS[(string) $row['position_type']] ?? (string) $row['position_type'],
            $row['affiliation'] === null ? null : (string) $row['affiliation'],
            $row['institutional_email'] === null ? null : (string) $row['institutional_email'],
            (bool) $row['is_active'], $row['username'] === null ? null : (string) $row['username'],
            $row['active_from'] === null ? null : (string) $row['active_from'],
            $row['active_to'] === null ? null : (string) $row['active_to'],
        ), $statement->fetchAll());
    }

    private function connection(): PDO { return $this->pdo ??= $this->connections->create(); }
    private function selectSql(): string
    {
        return 'SELECT pp.*,pe.first_name person_first_name,pe.last_name person_last_name,
            pe.institutional_email,pe.affiliation,pe.position_type,pe.is_internal person_is_internal,
            pe.is_active person_is_active,pe.active_from person_active_from,pe.active_to person_active_to,
            u.username linked_username,u.is_active linked_user_is_active,
            pr.acronym project_acronym,pr.title project_title,pr.status project_status
            FROM project_participants pp JOIN people pe ON pe.id=pp.person_id
            LEFT JOIN users u ON u.id=pe.user_id JOIN projects pr ON pr.id=pp.project_id';
    }
    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function parameters(array $data): array
    {
        return ['project_id' => $data['project_id'], 'person_id' => $data['person_id']] + $this->editableParameters($data);
    }
    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function editableParameters(array $data): array
    {
        return [
            'project_role' => $data['project_role'], 'participation_start' => $data['participation_start'],
            'participation_end' => $data['participation_end'], 'is_active' => $data['is_active'] ? 1 : 0,
            'notes' => $data['notes'],
        ];
    }
    /** @return array{string,array<string,mixed>} */
    private function filters(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        if ($filters['search'] !== '') {
            $search = '%' . strtr($filters['search'], ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
            $searchConditions = ["pe.first_name LIKE :s_first ESCAPE '='", "pe.last_name LIKE :s_last ESCAPE '='",
                "pe.institutional_email LIKE :s_email ESCAPE '='", "pe.affiliation LIKE :s_affiliation ESCAPE '='",
                "u.username LIKE :s_username ESCAPE '='", "pp.project_role LIKE :s_role ESCAPE '='"];
            foreach (ProjectParticipant::ROLE_LABELS as $role => $label) {
                if (str_contains(mb_strtolower($label), mb_strtolower($filters['search']))) {
                    $key = 's_role_label_' . count($parameters);
                    $searchConditions[] = 'pp.project_role = :' . $key;
                    $parameters[$key] = $role;
                }
            }
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            foreach (['s_first','s_last','s_email','s_affiliation','s_username','s_role'] as $key) $parameters[$key] = $search;
        }
        if ($filters['active'] !== 'all') {
            $conditions[] = 'pp.is_active=:participation_active';
            $parameters['participation_active'] = $filters['active'] === 'active' ? 1 : 0;
        }
        if ($filters['project_role'] !== '') {
            $conditions[] = 'pp.project_role=:project_role';
            $parameters['project_role'] = $filters['project_role'];
        }
        if ($filters['internal'] !== 'all') {
            $conditions[] = 'pe.is_internal=:person_internal';
            $parameters['person_internal'] = $filters['internal'] === 'internal' ? 1 : 0;
        }
        if ($filters['person_active'] !== 'all') {
            $conditions[] = 'pe.is_active=:person_active';
            $parameters['person_active'] = $filters['person_active'] === 'active' ? 1 : 0;
        }
        return [$conditions === [] ? '' : ' AND ' . implode(' AND ', $conditions), $parameters];
    }
    private function assertWriteTarget(int $id, int $projectId, ?int $requiredManagerPersonId): void
    {
        $participant = $this->findById($id);
        if ($participant === null || $participant->projectId !== $projectId) throw new \OutOfBoundsException('Participant not found.');
        if ($requiredManagerPersonId !== null) {
            $statement = $this->connection()->prepare(
                'SELECT COUNT(*) FROM project_participants pp JOIN projects pr ON pr.id=pp.project_id
                 WHERE pp.id=:id AND pp.project_id=:project_id AND pr.manager_person_id=:manager'
            );
            $statement->execute(['id'=>$id,'project_id'=>$projectId,'manager'=>$requiredManagerPersonId]);
            if ((int) $statement->fetchColumn() !== 1) throw new AuthorizationException('Project ownership changed.');
        }
    }
    private function translateConstraint(PDOException $exception): void
    {
        if (($exception->errorInfo[0] ?? '') === '23000'
            && str_contains(strtolower((string) ($exception->errorInfo[2] ?? '')), 'project_participants_project_person_unique')) {
            throw new DuplicateProjectParticipantException('That person already participates in this project.', 0, $exception);
        }
    }
    /** @param array<string,mixed> $row */
    private function hydrate(array $row): ProjectParticipant
    {
        $date = static fn (mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string) $value);
        return new ProjectParticipant(
            (int) $row['id'], (int) $row['project_id'], (int) $row['person_id'], (string) $row['project_role'],
            $date($row['participation_start']), $date($row['participation_end']), (bool) $row['is_active'],
            $row['notes'] === null ? null : (string) $row['notes'], new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']), (string) $row['person_first_name'],
            (string) $row['person_last_name'], $row['institutional_email'] === null ? null : (string) $row['institutional_email'],
            $row['affiliation'] === null ? null : (string) $row['affiliation'], (string) $row['position_type'],
            (bool) $row['person_is_internal'], (bool) $row['person_is_active'], $date($row['person_active_from']),
            $date($row['person_active_to']), $row['linked_username'] === null ? null : (string) $row['linked_username'],
            $row['linked_user_is_active'] === null ? null : (bool) $row['linked_user_is_active'],
            (string) $row['project_acronym'], (string) $row['project_title'], (string) $row['project_status'],
        );
    }
}
