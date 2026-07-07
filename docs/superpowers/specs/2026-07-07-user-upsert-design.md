# User Upsert — CSV Import

**Date:** 2026-07-07
**Status:** Approved

## Overview

When importing users via CSV, the system auto-detects whether a user already exists by email. If new, it creates the user (current behavior). If existing, it updates profile fields and adds missing roles/interests — never removes existing roles.

No flag or UI change — upsert is always on.

## Behavior

### Lookup

Email is required (`RequiredUserHeaders::$userRequiredHeaders`). Lookup via `CachedEntities::getCachedUserByEmail()`.

- **Email not found** → create new user (existing flow)
- **Email found** → update existing user

### Fields updated (existing user)

| Field | Behavior |
|-------|----------|
| firstname, lastname | Updated |
| affiliation, country | Updated |
| orcid | Updated if provided |
| email | Updated (change email) |
| tempPassword | Only set if non-empty; otherwise skipped |
| username | Skipped (keep existing) |
| roles | Add missing only — never remove existing |
| reviewInterests | Add missing only |
| subscription | Overwrite if type/start/end all provided |

### Fields unchanged (existing user)

- `dateRegistered` — not modified
- `mustChangePassword` — only set if password changed
- Existing roles beyond those in CSV — preserved

## Files Changed

| File | Change |
|------|--------|
| `classes/commands/UserCommand.php` | Move `validateUserAlreadyExistsWithThisEmail` and `validateUserAlreadyExistsWithThisUsername` to run only on the **create** path (after lookup determines user is new). For existing users, skip these validations. Add email lookup to decide create vs update path. Skip password for existing users if `tempPassword` empty. |
| `shared/processors/UsersProcessor.php` | Split `process()` into `create()` (existing logic) and `update()` (new). Add `process()` dispatcher that picks create or update based on email lookup. |
| `shared/processors/UserGroupsProcessor.php` | Add `assignMissingOnly()` — checks existing user-group assignments, only assigns roles user doesn't already have. |
| `locale/en/locale.po` | Add `plugins.importexport.csv.userUpdated` message for output. |

## Data Flow

```
CSV row parsed → validate fields, journal, roles, subscription, orcid
  → CachedEntities::getCachedUserByEmail(email)
      ├─ null  → validate username uniqueness → UsersProcessor::create()
      └─ found → UsersProcessor::update()
                   ├─ setGivenName, setFamilyName, setAffiliation, setCountry, setEmail
                   ├─ if orcid → setOrcid
                   ├─ if tempPassword non-empty → setPassword, setMustChangePassword(true)
                   ├─ UserGroupsProcessor::assignMissingOnly()
                   ├─ UserInterestsProcessor::process()
                   └─ UserSubscriptionProcessor::process() [unchanged]
```

## UsersProcessor Changes

```php
// process() becomes dispatcher:
public static function process(object $data, string $locale): User
{
    $existing = CachedEntities::getCachedUserByEmail($data->email);
    if ($existing) {
        return static::update($existing, $data, $locale);
    }
    return static::create($data, $locale);
}

// create() = current process() body (newDataObject → set* → add)
// update() = edit() → set* → Repo::user()->edit()
```

## UserGroupsProcessor — assignMissingOnly

```php
public static function assignMissingOnly(array $roles, int $userId, int $contextId, string $locale): void
{
    $existingAssignments = UserGroupAssignment::withUserId($userId)
        ->withContextId($contextId)
        ->get();
    $existingRoleNames = array_map(fn($a) => mb_strtolower($a->userGroup->name[$locale]), $existingAssignments);

    foreach ($roles as $role) {
        if (!in_array(mb_strtolower($role), $existingRoleNames)) {
            // assign this role
        }
    }
}
```

## Error Handling

- Dry mode: existing `DB::transaction()` + `DB::rollBack()` covers updates too
- Non-existent journal path → same error as today (`validateContextIsValid`)
- Invalid roles → same validation (`validateAllUserGroupsAreValid`)
- Subscription dates invalid → same validation
- Missing required fields → same validation

## Testing

1. **UsersProcessorTest** — `create()` path unchanged, `update()` path: verify fields updated, password skipped when empty, password set when provided
2. **UserGroupsProcessorTest** — `assignMissingOnly()`: new role added, existing role skipped
3. **Integration — dry mode** — CSV with mix of new + existing emails, verify no DB changes persisted after rollback
4. **Integration — full import** — existing user updated (check profile, roles), new user created

## Locale

```
msgid "plugins.importexport.csv.userUpdated"
msgstr "User \"{$email}\" updated."
```
