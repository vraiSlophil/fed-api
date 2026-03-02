<?php

namespace App\Domain\Themes\Support;

use App\Exceptions\ApiException;

final class ThemePermissionInvariant
{
    /**
     * Action flags that imply read access.
     */
    private const ACTION_FLAGS = [
        'can_update_theme',
        'can_add_task',
        'can_edit_task',
        'can_delete_task',
        'can_validate_task',
    ];

    /**
     * Ensure that action permissions are not granted without view permission.
     *
     * @param  array  $permissions  Permission payload to validate.
     *
     * @throws ApiException
     */
    public static function ensureCanViewForActionFlags(array $permissions): void
    {
        if (self::isValid($permissions)) {
            return;
        }

        throw new ApiException(
            messageCode: 'theme.permissions.invalid',
            messageParams: [],
            status: 422,
            message: 'Action permissions require can_view=true'
        );
    }

    /**
     * Validate permission coherence.
     *
     * @param  array  $permissions  Permission payload to validate.
     */
    public static function isValid(array $permissions): bool
    {
        $canView = (bool) ($permissions['can_view'] ?? false);

        foreach (self::ACTION_FLAGS as $flag) {
            if ((bool) ($permissions[$flag] ?? false) && ! $canView) {
                return false;
            }
        }

        return true;
    }
}
