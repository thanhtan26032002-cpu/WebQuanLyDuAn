<?php

namespace App\Services;

use App\Models\Group;

class GroupMembershipService
{
    /**
     * Move a member into exactly one group, or remove them from every group.
     * The caller must execute this method inside a database transaction.
     */
    public static function assign(string $memberCode, ?string $targetGroupCode): void
    {
        $groups = Group::lockForUpdate()->get();

        foreach ($groups as $group) {
            $memberIds = array_values(array_filter(
                $group->group_member_ids ?? [],
                fn ($id) => $id !== $memberCode,
            ));

            if ($targetGroupCode === $group->group_code) {
                $memberIds[] = $memberCode;
            }

            $group->group_member_ids = array_values(array_unique($memberIds));
            $group->save();
        }
    }
}
