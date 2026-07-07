# User Upsert Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Auto-detect existing users by email during CSV import — update profile/roles/interests instead of rejecting duplicates.

**Architecture:** Split `UsersProcessor::process()` into `create()` + `update()` with a dispatcher. Add `UserGroupsProcessor::assignMissingOnly()` that checks `Repo::userGroup()->userInGroup()` before assigning. Modify `UserCommand` to lookup by email and route to create or update path, keeping uniqueness validations only on the create path.

**Tech Stack:** PHP 8.x, OJS/PKP framework, Mockery for tests, PHPUnit

---

### Task 1: Split UsersProcessor — refactor process() into create() and update()

**Files:**
- Modify: `shared/processors/UsersProcessor.php`

- [ ] **Step 1: Rename current process() to create(), add update() and dispatcher**

Read the current file. Replace its entire body with:

```php
<?php

/**
 * @file plugins/importexport/csv/shared/processors/UsersProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsersProcessor
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the users data into the database.
 */

namespace APP\plugins\importexport\csv\shared\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\shared\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\shared\handlers\OrcidHandler;
use PKP\core\Core;
use PKP\security\Validation;
use PKP\user\User;

class UsersProcessor
{
    /**
     * Dispatcher: creates or updates a user based on whether the email already exists.
     */
    public static function process(object $data, string $locale): User
    {
        $existing = CachedEntities::getCachedUserByEmail($data->email);
        if ($existing) {
            return static::update($existing, $data, $locale);
        }
        return static::create($data, $locale);
    }

    /**
     * Create a new user from CSV data.
     */
    public static function create(object $data, string $locale): User
    {
        $user = Repo::user()->newDataObject();

        $user->setGivenName($data->firstname, $locale);
        $user->setFamilyName($data->lastname, $locale);
        $user->setAffiliation($data->affiliation, $locale);
        $user->setEmail($data->email);
        $user->setCountry($data->country);
        $user->setUsername($data->username ?? static::getValidUsername($data->firstname, $data->lastname));
        $user->setPassword(Validation::encryptCredentials($data->username, $data->tempPassword));
        $user->setMustChangePassword(true);
        $user->setDateRegistered(Core::getCurrentDate());

        if (!empty($data->orcid)) {
            $normalizedOrcid = OrcidHandler::normalize($data->orcid);
            if ($normalizedOrcid !== null) {
                $user->setOrcid($normalizedOrcid);
            }
        }

        $userId = Repo::user()->add($user);

        return Repo::user()->get($userId);
    }

    /**
     * Update an existing user from CSV data.
     * Only sets password if tempPassword is non-empty.
     * Username is never changed for existing users.
     */
    public static function update(User $user, object $data, string $locale): User
    {
        $user->setGivenName($data->firstname, $locale);
        $user->setFamilyName($data->lastname, $locale);
        $user->setAffiliation($data->affiliation, $locale);
        $user->setEmail($data->email);
        $user->setCountry($data->country);

        if (!empty($data->tempPassword)) {
            $user->setPassword(Validation::encryptCredentials($user->getUsername(), $data->tempPassword));
            $user->setMustChangePassword(true);
        }

        if (!empty($data->orcid)) {
            $normalizedOrcid = OrcidHandler::normalize($data->orcid);
            if ($normalizedOrcid !== null) {
                $user->setOrcid($normalizedOrcid);
            }
        }

        Repo::user()->edit($user);

        return Repo::user()->get($user->getId());
    }

    public static function getValidUsername(string $firstname, string $lastname): string
    {
        $letters = range('a', 'z');

        do {
            $randomLetters = '';
            for ($i = 0; $i < 3; $i++) {
                $randomLetters .= $letters[array_rand($letters)];
            }

            $username = mb_strtolower(mb_substr($firstname, 0, 1) . $lastname . $randomLetters);
            $existingUser = CachedEntities::getCachedUserByUsername($username);

        } while (!is_null($existingUser));

        return $username;
    }
}
```

- [ ] **Step 2: Run existing UsersProcessor tests to verify create path unchanged**

Run: `php lib/pkp/tools/runAllTests.php plugins/importexport/csv/shared/tests/Unit/Processors/UsersProcessorTest.php`
Expected: All tests PASS

- [ ] **Step 3: Commit**

```bash
git add shared/processors/UsersProcessor.php
git commit -m "refactor: split UsersProcessor into create() and update() methods

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 2: Add assignMissingOnly() to UserGroupsProcessor

**Files:**
- Modify: `shared/processors/UserGroupsProcessor.php`

- [ ] **Step 1: Add assignMissingOnly() method**

Read the current file. Replace its entire body with:

```php
<?php

/**
 * @file plugins/importexport/csv/shared/processors/UserGroupsProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the user groups data into the database.
 */

namespace APP\plugins\importexport\csv\shared\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\shared\cachedAttributes\CachedEntities;

class UserGroupsProcessor
{
    /**
     * Assign all given roles to a user. Used for new users.
     */
    public static function process(array $roles, int $userId, int $contextId, string $locale)
    {
        foreach ($roles as $role) {
            $userGroup = CachedEntities::getCachedUserGroupByName($role, $contextId, $locale);
            if ($userGroup) {
                Repo::userGroup()->assignUserToGroup($userId, $userGroup->id);
            }
        }
    }

    /**
     * Assign only roles the user doesn't already have. Used for existing users.
     * Uses Repo::userGroup()->userInGroup() to check existing assignments.
     */
    public static function assignMissingOnly(array $roles, int $userId, int $contextId, string $locale): void
    {
        foreach ($roles as $role) {
            $userGroup = CachedEntities::getCachedUserGroupByName($role, $contextId, $locale);
            if (!$userGroup) {
                continue;
            }
            if (!Repo::userGroup()->userInGroup($userId, $userGroup->id)) {
                Repo::userGroup()->assignUserToGroup($userId, $userGroup->id);
            }
        }
    }
}
```

- [ ] **Step 2: Run existing UserGroupsProcessor tests**

Run: `php lib/pkp/tools/runAllTests.php plugins/importexport/csv/shared/tests/Unit/Processors/UserGroupsProcessorTest.php`
Expected: All tests PASS

- [ ] **Step 3: Commit**

```bash
git add shared/processors/UserGroupsProcessor.php
git commit -m "feat: add assignMissingOnly() to UserGroupsProcessor

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 3: Update UserCommand — conditional validation, upsert routing

**Files:**
- Modify: `classes/commands/UserCommand.php`

- [ ] **Step 1: Read current file, then apply changes**

Current file is at `classes/commands/UserCommand.php`. Replace lines 118-123 (the email/username validation block) and lines 150-152 (the process call and role assignment) with upsert logic.

The changed section (lines 108-166) becomes:

```php
                    $journal = CachedEntities::getCachedJournal($data->journalPath);

                    InvalidRowValidations::validateContextIsValid($journal, $data->journalPath, 'Journal');

                    $existingUser = CachedEntities::getCachedUserByEmail($data->email);
                    $isNewUser = is_null($existingUser);

                    if ($isNewUser) {
                        InvalidRowValidations::validateUserAlreadyExistsWithThisEmail($data->email);

                        if ($data->username) {
                            InvalidRowValidations::validateUserAlreadyExistsWithThisUsername($data->username);
                        }

                        if (empty($data->username)) {
                            $data->username = UsersProcessor::getValidUsername($data->firstname, $data->lastname);
                        }
                    }

                    $roles = array_map('trim', explode(';', $data->roles));

                    InvalidRowValidations::validateAllUserGroupsAreValid($roles, $journal->getId(), $journal->getPrimaryLocale());

                    if (!empty($data->subscriptionType) || !empty($data->startDate) || !empty($data->endDate)) {
                        InvalidRowValidations::validateSubscriptionFields($data);

                        $subscriptionType = CachedEntities::getCachedSubscriptionType($data->subscriptionType, $journal->getId());

                        InvalidRowValidations::validateSubscriptionType($subscriptionType, $data->subscriptionType);
                        InvalidRowValidations::validateSubscriptionDates($data->startDate, $data->endDate);
                    }

                    if (!empty($data->orcid)) {
                        OrcidHandler::validate($data->orcid);
                    }

                    if ($isNewUser && is_null($data->tempPassword)) {
                        $data->tempPassword = Validation::generatePassword();
                    }

                    $user = UsersProcessor::process($data, $journal->getPrimaryLocale());
                    $userId = $user->getId();
                    $userInterests = array_map('trim', explode(';', $data->reviewInterests));
                    UserInterestsProcessor::process($userInterests, $userId);

                    if ($isNewUser) {
                        UserGroupsProcessor::process($roles, $userId, $journal->getId(), $journal->getPrimaryLocale());
                    } else {
                        UserGroupsProcessor::assignMissingOnly($roles, $userId, $journal->getId(), $journal->getPrimaryLocale());
                    }

                    if (!empty($data->subscriptionType) && !empty($data->startDate) && !empty($data->endDate)) {
                        $dateFormat = 'Y-m-d';
                        $startDate = \DateTime::createFromFormat($dateFormat, $data->startDate);
                        $endDate = \DateTime::createFromFormat($dateFormat, $data->endDate);

                        UserSubscriptionProcessor::process((int) $data->subscriptionType, $user->getId(), $journal->getId(), $startDate, $endDate);
                    }

                    if ($this->sendWelcomeEmail && !$this->dryMode && $isNewUser) {
                        WelcomeEmailHandler::sendWelcomeEmail($journal, $user, $this->senderEmailUser, $data->tempPassword);
                    }
```

The key changes from the original:
1. Line 119 `validateUserAlreadyExistsWithThisEmail` → now inside `if ($isNewUser)` block
2. Lines 121-123 username validation → now inside `if ($isNewUser)` block
3. Line 126 username generation → now inside `if ($isNewUser)` block
4. Line 146 password generation (`Validation::generatePassword()`) → now inside `if ($isNewUser)` block (existing users with empty password just skip it)
5. Line 154 `UserGroupsProcessor::process` → conditional: `process()` for new, `assignMissingOnly()` for existing
6. Line 164 welcome email → added `&& $isNewUser` condition

Use Edit tool to apply each change, or replace the entire run() method's inner loop block.

- [ ] **Step 2: Verify syntax**

Run: `php -l classes/commands/UserCommand.php`
Expected: "No syntax errors detected"

- [ ] **Step 3: Commit**

```bash
git add classes/commands/UserCommand.php
git commit -m "feat: add user upsert logic to UserCommand

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 4: Add locale strings

**Files:**
- Modify: `locale/en/locale.po`

- [ ] **Step 1: Add userUpdated message**

Append to end of `locale/en/locale.po`:

```
msgid "plugins.importexport.csv.userUpdated"
msgstr "User \"{$email}\" updated."
```

- [ ] **Step 2: Commit**

```bash
git add locale/en/locale.po
git commit -m "feat: add userUpdated locale string

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 5: Write tests for UsersProcessor update() path

**Files:**
- Modify: `shared/tests/Unit/Processors/UsersProcessorTest.php`

- [ ] **Step 1: Add update() tests at end of test class (before final closing `}`)**

```php
    // ==================== update() Tests ====================

    public function testUpdateSetsProfileFields(): void
    {
        $existingUser = MockFactory::user()
            ->withId(42)
            ->withUsername('originaluser')
            ->withEmail('old@example.com')
            ->withGivenName('Old')
            ->withFamilyName('Name')
            ->build();
        $existingUser->setAffiliation('Old Affil', 'en');
        $existingUser->setCountry('BR');

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('edit')->once()->andReturn(true);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($existingUser);

        $data = $this->createUserDataObject([
            'firstname' => 'NewFirst',
            'lastname' => 'NewLast',
            'email' => 'new@example.com',
            'affiliation' => 'New Affil',
            'country' => 'US',
        ]);

        $result = UsersProcessor::update($existingUser, $data, 'en');

        $this->assertEquals('NewFirst', $result->getGivenName('en'));
        $this->assertEquals('NewLast', $result->getFamilyName('en'));
        $this->assertEquals('New Affil', $result->getAffiliation('en'));
        $this->assertEquals('US', $result->getCountry());
        $this->assertEquals('new@example.com', $result->getEmail());
    }

    public function testUpdatePreservesUsername(): void
    {
        $existingUser = MockFactory::user()
            ->withId(42)
            ->withUsername('originaluser')
            ->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('edit')->once()->andReturn(true);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($existingUser);

        $data = $this->createUserDataObject([
            'username' => 'differentuser', // CSV provides different username
        ]);

        UsersProcessor::update($existingUser, $data, 'en');

        // Username should remain unchanged
        $this->assertEquals('originaluser', $existingUser->getUsername());
    }

    public function testUpdateSetsPasswordWhenProvided(): void
    {
        $existingUser = MockFactory::user()
            ->withId(42)
            ->withUsername('testuser')
            ->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('edit')->once()->andReturn(true);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($existingUser);

        $data = $this->createUserDataObject([
            'tempPassword' => 'newpassword123',
        ]);

        UsersProcessor::update($existingUser, $data, 'en');

        $this->assertTrue(password_verify('newpassword123', $existingUser->getPassword()));
        $this->assertTrue($existingUser->getMustChangePassword());
    }

    public function testUpdateSkipsPasswordWhenEmpty(): void
    {
        $existingUser = MockFactory::user()
            ->withId(42)
            ->withUsername('testuser')
            ->build();
        $originalPassword = $existingUser->getPassword();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('edit')->once()->andReturn(true);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($existingUser);

        $data = $this->createUserDataObject([
            'tempPassword' => '',
        ]);

        UsersProcessor::update($existingUser, $data, 'en');

        // Password should be unchanged (still the original value)
        $this->assertEquals($originalPassword, $existingUser->getPassword());
    }

    public function testUpdateSetsOrcidWhenValid(): void
    {
        $existingUser = MockFactory::user()
            ->withId(42)
            ->withUsername('testuser')
            ->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('edit')->once()->andReturn(true);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($existingUser);

        $data = $this->createUserDataObject([
            'orcid' => '0000-0002-1825-0097',
        ]);

        UsersProcessor::update($existingUser, $data, 'en');

        $this->assertEquals('https://orcid.org/0000-0002-1825-0097', $existingUser->getOrcid());
    }

    public function testUpdateSkipsOrcidWhenEmpty(): void
    {
        $existingUser = MockFactory::user()
            ->withId(42)
            ->withUsername('testuser')
            ->withOrcid('https://orcid.org/0000-0001-2345-6789')
            ->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('edit')->once()->andReturn(true);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($existingUser);

        $data = $this->createUserDataObject([
            'orcid' => '',
        ]);

        UsersProcessor::update($existingUser, $data, 'en');

        // ORCID should remain unchanged when empty
        $this->assertEquals('https://orcid.org/0000-0001-2345-6789', $existingUser->getOrcid());
    }

    public function testProcessDispatchesToUpdateWhenUserExistsByEmail(): void
    {
        $existingUser = MockFactory::user()
            ->withId(99)
            ->withEmail('existing@example.com')
            ->withUsername('existinguser')
            ->build();

        CachedEntities::$users['existing@example.com'] = $existingUser;
        CachedEntities::$users['existinguser'] = $existingUser;

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('edit')->once()->andReturn(true);
        $userRepoMock->shouldReceive('get')->with(99)->andReturn($existingUser);

        $data = $this->createUserDataObject([
            'email' => 'existing@example.com',
            'firstname' => 'Updated',
        ]);

        $result = UsersProcessor::process($data, 'en');

        $this->assertEquals(99, $result->getId());
        $this->assertEquals('Updated', $result->getGivenName('en'));
    }

    public function testProcessDispatchesToCreateWhenUserDoesNotExist(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $returnedUser = $this->createMockUser(['id' => 55]);
        $userRepoMock->shouldReceive('add')->once()->andReturn(55);
        $userRepoMock->shouldReceive('get')->with(55)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'email' => 'newuser@example.com',
            'username' => 'newuser',
        ]);

        $result = UsersProcessor::process($data, 'en');

        $this->assertEquals(55, $result->getId());
    }
```

- [ ] **Step 2: Run UsersProcessor tests**

Run: `php lib/pkp/tools/runAllTests.php plugins/importexport/csv/shared/tests/Unit/Processors/UsersProcessorTest.php`
Expected: All tests PASS (existing + new)

- [ ] **Step 3: Commit**

```bash
git add shared/tests/Unit/Processors/UsersProcessorTest.php
git commit -m "test: add update() and dispatch tests for UsersProcessor

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 6: Write tests for UserGroupsProcessor assignMissingOnly()

**Files:**
- Modify: `shared/tests/Unit/Processors/UserGroupsProcessorTest.php`

- [ ] **Step 1: Add assignMissingOnly() tests at end of test class (before final closing `}`)**

```php
    // ==================== assignMissingOnly() Tests ====================

    public function testAssignMissingOnlyAssignsNewRole(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        // userInGroup returns false — user doesn't have this role yet
        $userGroupMock->shouldReceive('userInGroup')
            ->once()
            ->with(5, 10)
            ->andReturn(false);

        $userGroupMock->shouldReceive('assignUserToGroup')
            ->once()
            ->with(5, 10);

        UserGroupsProcessor::assignMissingOnly(['Author'], 5, 1, 'en');
        $this->assertTrue(true);
    }

    public function testAssignMissingOnlySkipsExistingRole(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        // userInGroup returns true — user already has this role
        $userGroupMock->shouldReceive('userInGroup')
            ->once()
            ->with(5, 10)
            ->andReturn(true);

        $userGroupMock->shouldReceive('assignUserToGroup')
            ->never();

        UserGroupsProcessor::assignMissingOnly(['Author'], 5, 1, 'en');
        $this->assertTrue(true);
    }

    public function testAssignMissingOnlyMixedNewAndExisting(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        $readerGroup = $this->createMockUserGroup(['id' => 11, 'name' => ['en' => 'Reader']]);
        $reviewerGroup = $this->createMockUserGroup(['id' => 12, 'name' => ['en' => 'Reviewer']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup, 11 => $readerGroup, 12 => $reviewerGroup];

        // Author: already assigned, Reader: new, Reviewer: already assigned
        $userGroupMock->shouldReceive('userInGroup')
            ->with(5, 10)
            ->andReturn(true);
        $userGroupMock->shouldReceive('userInGroup')
            ->with(5, 11)
            ->andReturn(false);
        $userGroupMock->shouldReceive('userInGroup')
            ->with(5, 12)
            ->andReturn(true);

        // Only Reader should be assigned
        $userGroupMock->shouldReceive('assignUserToGroup')
            ->once()
            ->with(5, 11);
        $userGroupMock->shouldReceive('assignUserToGroup')
            ->never()
            ->with(5, 10);
        $userGroupMock->shouldReceive('assignUserToGroup')
            ->never()
            ->with(5, 12);

        UserGroupsProcessor::assignMissingOnly(['Author', 'Reader', 'Reviewer'], 5, 1, 'en');
        $this->assertTrue(true);
    }

    public function testAssignMissingOnlySkipsNonMatchingRoles(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userGroupMock->shouldReceive('userInGroup')
            ->never();
        $userGroupMock->shouldReceive('assignUserToGroup')
            ->never();

        UserGroupsProcessor::assignMissingOnly(['NonExistentRole'], 5, 1, 'en');
        $this->assertTrue(true);
    }

    public function testAssignMissingOnlyWithEmptyRoles(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $userGroupMock->shouldReceive('userInGroup')
            ->never();
        $userGroupMock->shouldReceive('assignUserToGroup')
            ->never();

        UserGroupsProcessor::assignMissingOnly([], 5, 1, 'en');
        $this->assertTrue(true);
    }

    public function testAssignMissingOnlyMatchesRolesCaseInsensitively(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userGroupMock->shouldReceive('userInGroup')
            ->once()
            ->with(5, 10)
            ->andReturn(false);
        $userGroupMock->shouldReceive('assignUserToGroup')
            ->once()
            ->with(5, 10);

        UserGroupsProcessor::assignMissingOnly(['author'], 5, 1, 'en');
        $this->assertTrue(true);
    }
```

- [ ] **Step 2: Run UserGroupsProcessor tests**

Run: `php lib/pkp/tools/runAllTests.php plugins/importexport/csv/shared/tests/Unit/Processors/UserGroupsProcessorTest.php`
Expected: All tests PASS (existing + new)

- [ ] **Step 3: Commit**

```bash
git add shared/tests/Unit/Processors/UserGroupsProcessorTest.php
git commit -m "test: add assignMissingOnly() tests for UserGroupsProcessor

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 7: Run full test suite and verify

- [ ] **Step 1: Run all plugin tests**

```bash
php lib/pkp/tools/runAllTests.php plugins/importexport/csv/shared/tests/
```

Expected: All tests PASS

- [ ] **Step 2: Run syntax check on all changed files**

```bash
php -l shared/processors/UsersProcessor.php
php -l shared/processors/UserGroupsProcessor.php
php -l classes/commands/UserCommand.php
```

Expected: "No syntax errors detected" for each

- [ ] **Step 3: Commit any remaining changes**

```bash
git add -A
git commit -m "chore: final verification of user upsert feature

Co-Authored-By: Claude <noreply@anthropic.com>"
```
