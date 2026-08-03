# TRYBE

TRYBE is a web-based three-sided marketplace that connects researchers, participants, and collaborators in one platform.

This branch includes a Laravel feature module for **Study Incentive Variety Settings** only:

- Four incentive types: **Cash**, **Vouchers**, **Course Credits**, and **Volunteer/Unpaid**
- Escrow locking workflow for cash and voucher studies before publication
- Mandatory institution documentation upload for course-credit studies
- Stronger database layer with escrow indexes, denormalized escrow read fields, incentive document table, and incentive audit table
- Reusable incentive form/display partials with theme palette: `#dff0ea`, `#95adbe`, `#574f7d`, `#4f3a65`
