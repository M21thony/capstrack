# Plan: Grading Rubrics — Archive & Restore

## Context
Currently, deleting a grading rubric in the CAPSA portal **permanently removes** it and all its criteria from the `grading_rubrics` and `rubric_criteria` tables. CAPSAs need a safety net: when a rubric is no longer needed, it should be **archived** (soft-deleted) so it can be **restored** later if needed — without creating a new table.

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Archive mechanism | `is_archived` column on existing `grading_rubrics` table | User explicitly said no new table |
| Criteria on archive | Keep criteria intact (do NOT delete) | Data needed for restore; FK cascade handles cleanup if rubric is ever hard-deleted |
| `is_active` on archive | Set to `false` simultaneously | Archived rubric must not appear in instructor grading dropdowns |
| Restore action | Set `is_archived = 0`, `is_active = true` | Full restoration to prior state |
| Default view | Show active rubrics only (`is_archived = 0`) | Archived hidden by default |
| Filter UI | "Archived" tab is mutually exclusive with type tabs | Simpler UX; switching tabs switches the filter category entirely |
| API `index()` | Returns ALL rubrics (active + archived) | Client-side filtering avoids API changes; admin needs archived visible for restore |
| API `show()` | Works for archived rubrics too | Admin must be able to view archived rubric details before restoring |
| Restore confirmation | Reuse delete modal with different text/button label | Minimizes UI code changes |
| `AdminGradingRubricController::destroy()` | No longer deletes criteria; only archives | Criteria stay attached to archived rubric |

## How `is_archived` and `is_active` relate

They are coupled: archiving sets both (`is_archived=1, is_active=false`). Restoring sets both back (`is_archived=0, is_active=true`). They are stored as separate columns so future flexibility exists, but in practice they always mirror each other.

`Instructor\GradingController::rubricFor()` already filters `is_active = true`, so archived rubrics are automatically excluded from the grading dropdown — no changes needed there.

## Files to Modify

### 1. Database Migration — New file
**Path**: `database/migrations/2026_09_12_000000_add_is_archived_to_grading_rubrics.php`

- Add `is_archived` column: `tinyInteger('is_archived')->default(0)` to `grading_rubrics`
- Down: drop column

### 2. Model — `app/Models/GradingRubric.php`
- Add `'is_archived'` to `$fillable`
- Add `'is_archived' => 'boolean'` to `casts()` (or `protected $casts` in Laravel 11 style)

### 3. Controller — `app/Http/Controllers\Api/AdminGradingRubricController.php`

**`destroy()`** (line ~1221):
- **Before**: `$gradingRubric->criteria()->delete(); $gradingRubric->delete();`
- **After**: Set `is_archived = 1`, `is_active = false`, save. Keep criteria. Return message: "Rubric archived successfully."

**New `restore()` method**:
- Find rubric by ID, set `is_archived = 0`, `is_active = true`, save
- Return JSON success with message "Rubric restored successfully."

### 4. Routes — `routes/api.php` (line ~61)
Add after the destroy route:
```php
Route::put('/{gradingRubric}/restore', [AdminGradingRubricController::class, 'restore'])->name('restore');
```

### 5. UI — `resources/views/capsa/rubric_management.blade.php`

#### 5a. Controller — `AdminGradingRubricController::formatRubric()` (line ~1606)
- **Add** `'is_archived' => (bool) $rubric->is_archived` to the returned array
- This is what the UI reads to know which rubrics are archived

#### 5b. `confirmDelete()` JS function (line ~1159)
- Modal subtitle: `'"' + name + '" will be archived and moved out of active use.'` (was "permanently deleted")
- Confirm button text: "Archive" instead of "Delete"
- Action: same — calls DELETE on the rubric, which now archives it server-side

#### 5c. Filter tabs (line ~263-274)
- Add a new tab: `<button class="rb-tab-btn" onclick="filterType('archived',this)" data-type="archived">Archived <span class="rb-tab-count" id="tc-archived">0</span></button>`
- `activeFilter` starts as `"all"` (unchanged); `'archived'` is treated as a special filter type in `getFiltered()`

#### 5d. `renderList()` JS function — archived rubrics get different styling and actions:
- Greyed-out card (e.g., `opacity: 0.6`)
- Show "Restore" button (green) instead of "Delete" (red)
- "Archive" button is hidden for archived rubrics
- For active rubrics: keep existing "Edit" + "Delete" buttons unchanged

#### 5e. `normalizeRubric()` JS (line ~582)
- Add `isArchived: r.is_archived` to the returned object so the UI can filter

### 6. Instructor Controller — `app/Http/Controllers/Instructor/GradingController.php`
- **No changes needed**: `rubricFor()` already filters by `is_active = true`, so archived rubrics are automatically excluded from grading.

### 7. Tests — `tests/Feature/AdminGradingRubricTest.php`

**Update existing test** `test_archive_marks_rubric_as_archived` (was `test_destroy_deletes_rubric_and_criteria`, line 150):
- Rename to reflect archive, not delete
- Assert success message: `'Rubric archived successfully.'`
- Replace `assertDatabaseMissing('grading_rubrics', ...)` with `assertDatabaseHas('grading_rubrics', ['rubric_id' => ..., 'is_archived' => 1, 'is_active' => false])`
- Replace `assertDatabaseMissing('rubric_criteria', ...)` with `assertDatabaseHas('rubric_criteria', ['rubric_id' => ...])` — criteria must survive archive
- **Add `is_archived` to `$fillable` in `GradingRubric` model first** (needed for test factory/create)

**Add new tests**:
- `test_restore_unarchives_rubric`: PUT `/api/admin/grading-rubrics/{id}/restore` → assert `is_archived = 0, is_active = true`
- `test_archive_keeps_criteria`: archive a rubric with criteria → assert criteria still present
- `test_show_works_for_archived`: GET `/api/admin/grading-rubrics/{archived_id}` → assert 200 with rubric data

**No change needed**: `test_destroy_missing_rubric_returns_404` — route model binding still returns 404 for non-existent IDs

## Implementation Steps

1. Create migration + run `php artisan migrate`
2. Update `GradingRubric` model (fillable + casts)
3. Update `AdminGradingRubricController::destroy()` + add `restore()`
4. Add restore route in `routes/api.php`
5. Update `rubric_management.blade.php` UI (archive flow, restore UI, filter tabs)
6. Update tests
7. Run `vendor/bin/pint` on changed PHP files
8. Run `php artisan test --compact --filter=AdminGradingRubricTest`

## Verification
- `php artisan migrate` — new column exists
- `php artisan tinker`: `GradingRubric::first()->update(is_archived: 1)` — works without error
- Archive a rubric via UI → it disappears from default list, appears under "Archived" tab
- Click Restore → it returns to active list
- Instructor grading page → archived rubrics don't appear as options
- All `AdminGradingRubricTest` tests pass
