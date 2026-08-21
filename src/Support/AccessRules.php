<?php

namespace Blunx\AI\Support;

/**
 * Access rule resolution (RBAC).
 *
 * Pure, Laravel-free helpers: rules are passed as arguments, so the logic can
 * be tested in isolation. Supports recursive merging and the USER_ID
 * placeholder substitution.
 */
class AccessRules
{
    /**
     * Recursive array merge: numeric (list) keys are concatenated, associative
     * keys are merged recursively with the override taking precedence.
     *
     * @param  mixed $base
     * @param  mixed $override
     * @return mixed
     */
    public static function arrayReplaceRecursive($base, $override)
    {
        if (!is_array($base) || !is_array($override)) {
            return $override === null ? $base : $override;
        }

        $out = $base;
        foreach ($override as $k => $v) {
            $baseVal = $out[$k] ?? null;
            if (is_array($baseVal) && array_is_list($baseVal) && is_array($v) && array_is_list($v)) {
                $out[$k] = array_merge($baseVal, $v);
            } elseif (is_array($baseVal) && is_array($v)) {
                $out[$k] = self::arrayReplaceRecursive($baseVal, $v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * Resolve the access rules for a user role.
     *
     * A listed role gets its own rules only (no merge with `default`); an
     * unlisted role gets the `default` rules. The USER_ID placeholder is
     * replaced with the given user ID.
     *
     * @param  array|null  $allRules Complete rules (e.g. config('blunx_access')).
     * @param  string|null $role     User role (null → null).
     * @param  int|null    $userId   User ID replacing the USER_ID placeholder.
     * @return array|null
     */
    public static function getAccessRulesForUser(?array $allRules, ?string $role, ?int $userId = null): ?array
    {
        if (!$role) {
            return null;
        }
        if (!$allRules) {
            return null;
        }

        $rules = $allRules[$role] ?? ($allRules['default'] ?? []);

        if (empty($rules)) {
            return null;
        }

        if (isset($rules['row_level']) && is_array($rules['row_level'])) {
            foreach ($rules['row_level'] as $table => $config) {
                if (isset($config['value']) && $config['value'] === 'USER_ID') {
                    $rules['row_level'][$table]['value'] = $userId;
                }
            }
        }

        // Canonical serialization: empty associative dictionaries become {}
        // (not []) to match the API contract.
        foreach (['forbidden_columns', 'row_level'] as $objKey) {
            if (isset($rules[$objKey]) && is_array($rules[$objKey]) && $rules[$objKey] === []) {
                $rules[$objKey] = (object) [];
            }
        }

        return $rules;
    }
}
