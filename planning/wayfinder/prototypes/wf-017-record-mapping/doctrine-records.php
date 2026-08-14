<?php

declare(strict_types=1);

namespace PrototypeRecordMapping\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class UserRecord
{
    /** @var Collection<int, RoleRecord> */
    private Collection $roles;

    public function __construct(private string $id, private string $email)
    {
        $this->roles = new ArrayCollection();
    }

    public function revise(string $email): void
    {
        $this->email = $email;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    /** @param list<RoleRecord> $roles */
    public function replaceRoles(array $roles): void
    {
        $this->roles->clear();
        foreach ($roles as $role) {
            $this->roles->add($role);
        }
    }

    /** @return list<string> */
    public function roleIds(): array
    {
        $ids = array_map(static fn (RoleRecord $role): string => $role->id(), $this->roles->toArray());
        sort($ids);

        return $ids;
    }
}

final readonly class RoleRecord
{
    public function __construct(private string $id) {}

    public function id(): string
    {
        return $this->id;
    }
}
