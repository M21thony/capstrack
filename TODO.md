# TODO - Admin approval/reject email notification fix

- [x] Update `AdminApprovalController` to send approval/rejection email to the correct requestor account (`AccountRequest`) instead of directly to `CapstoneExpert`.

- [x] Ensure approve/reject decision is applied to the correct status fields used by UI (`status` vs `approved_status`).


- [x] Keep reject reason passed into `InstructorRejected` mailable.
- [x] Add guard for missing email before sending.

- [ ] Run basic checks/tests (at least `php artisan test` if available).

