# EVENT MANAGEMENT SYSTEM - AI DEVELOPMENT RULES

## PROJECT OVERVIEW

Build a production-quality Event Management System using Laravel.

The application allows users to discover events, register for events,
receive digital tickets, generate QR Codes, perform QR check-in,
and allows organizers to manage events and participants.

The system must be developed incrementally.

DO NOT build the entire application in one step.

Always inspect the existing project before making changes.

Never overwrite existing functionality unless explicitly requested.

---

# TECHNOLOGY STACK

Backend:
- Laravel 13
- PHP 8.3+
- MySQL
- Eloquent ORM

Frontend:
- Blade
- Livewire
- Tailwind CSS
- Alpine.js

Authentication:
- Official Laravel Starter Kit

Development:
- Laravel Boost
- Vite
- Git

---

# DEVELOPMENT PRINCIPLES

1. Follow Laravel conventions.

2. Use MVC architecture.

3. Use Eloquent relationships instead of raw SQL whenever practical.

4. Use Form Request classes for complex validation.

5. Use Policies/Gates for authorization.

6. Never trust user input.

7. Validate all incoming data.

8. Protect all authenticated routes.

9. Organize routes logically.

10. Avoid unnecessary packages.

11. Do not duplicate code.

12. Create reusable Blade/Livewire components.

13. Keep controllers thin.

14. Put complex business logic into appropriate services/actions.

15. Use database transactions when multiple database operations
   must succeed together.

16. Never expose sensitive environment variables.

17. Never hardcode credentials.

18. Never modify .env.example with real credentials.

---

# USER ROLES

The system has three roles:

ADMIN
ORGANIZER
PARTICIPANT

Role permissions must be enforced on the backend.

Frontend hiding is NOT considered authorization.

Example:

Hiding an "Edit Event" button is not enough.

The backend must also prevent unauthorized users from editing
the event.

---

# DATABASE

Main entities:

User
Event
TicketType
Registration
CheckIn

Relationships:

User:
- hasMany Events as organizer
- hasMany Registrations

Event:
- belongsTo User as organizer
- hasMany TicketTypes
- hasMany Registrations

TicketType:
- belongsTo Event
- hasMany Registrations

Registration:
- belongsTo User
- belongsTo Event
- belongsTo TicketType
- hasOne CheckIn

CheckIn:
- belongsTo Registration
- belongsTo User as checker

Use foreign keys and indexes where appropriate.

Use cascading behavior carefully.

Never create relationships without understanding
their database constraints.

---

# EVENT STATUS

Events should support:

draft
published
ongoing
completed
cancelled

Use a consistent approach for event status.

Do not scatter raw status strings throughout the application.

---

# REGISTRATION FLOW

Participant:

1. Browse event.
2. Open event detail.
3. Select ticket.
4. Register.
5. System validates ticket quota.
6. Registration is created.
7. Unique registration code is generated.
8. QR Code is generated from the registration code.
9. Participant can view the digital ticket.

Prevent duplicate registration for the same event
unless explicitly allowed.

Prevent registration when the event is closed.

Prevent registration when ticket quota is exhausted.

Use database transactions where appropriate.

---

# QR CODE

QR Code must NOT contain sensitive personal data.

The QR Code should contain a unique registration identifier/code.

Example:

EVENT-REG-XXXXXXXX

When scanned:

1. Find registration.
2. Validate registration.
3. Check event status.
4. Check whether participant already checked in.
5. If valid, create check-in record.
6. Return success result.

A participant must not be able to check themselves in
unless explicitly authorized.

Only organizer/admin or authorized event staff
can perform check-in.

Duplicate check-in must be prevented.

---

# CHECK-IN

Check-in should store:

registration_id
checked_in_by
checked_in_at

The system must prevent:

- Invalid registration
- Cancelled registration
- Duplicate check-in
- Unauthorized check-in

Check-in actions should be logged properly.

---

# REPORTING

Organizer dashboard should provide:

- Total events
- Published events
- Total registrations
- Total checked-in participants
- Ticket sales/count
- Registration statistics

Reports should be based on database queries,
not hardcoded numbers.

---

# UI / UX

Design style:

Modern
Professional
Clean
Premium
Minimal
Responsive

Use:

- Card-based layouts
- Soft shadows
- Rounded corners
- Clear typography
- Consistent spacing
- Responsive tables
- Responsive forms
- Empty states
- Loading states
- Success states
- Error states

Primary dashboard should use a modern sidebar layout.

The UI must work well on:

- Desktop
- Tablet
- Mobile

Do not create a desktop-only interface.

---

# DASHBOARD

Organizer dashboard should contain:

Stats:

Total Events
Total Participants
Total Tickets
Total Check-ins

Recent events.

Upcoming events.

Recent registrations.

Check-in statistics.

Use real database data.

Never use fake hardcoded statistics
after the relevant database functionality exists.

---

# SECURITY

Security is a priority.

Implement:

- Authentication
- Authorization
- CSRF protection
- Request validation
- Policies
- Role permissions
- Secure password handling
- Mass assignment protection
- Proper route protection

Never trust:

- URL parameters
- Form input
- Hidden form fields
- JavaScript validation

Backend validation is mandatory.

---

# TESTING

For every important feature create tests.

At minimum test:

Authentication
Authorization
Event creation
Event editing
Event deletion
Registration
Ticket quota
Duplicate registration
QR validation
Check-in
Duplicate check-in
Role permissions

Run tests after implementing important functionality.

If a test fails:

1. Read the error.
2. Identify the root cause.
3. Fix the implementation.
4. Run the test again.

Do not simply remove or weaken tests to make them pass.

---

# GIT

Work incrementally.

After completing a meaningful feature:

1. Run tests.
2. Check changed files.
3. Review the implementation.
4. Commit with a clear message.

Example:

feat: add event management

feat: add participant registration

feat: add qr check in

fix: prevent duplicate registration

Never commit:

.env

credentials

API keys

private secrets

---

# AI AGENT BEHAVIOR

Before modifying code:

1. Inspect the project structure.
2. Inspect relevant files.
3. Understand existing architecture.
4. Identify dependencies.
5. Determine the smallest safe change.

Do not blindly create files.

Do not duplicate existing functionality.

Do not change unrelated files.

If requirements are ambiguous:

- choose a sensible Laravel convention
- document the assumption
- continue implementation

Do not repeatedly ask unnecessary questions.

---

# IMPLEMENTATION ORDER

Build the system in this order:

PHASE 1
Project setup

PHASE 2
Authentication

PHASE 3
Database schema

PHASE 4
User roles and authorization

PHASE 5
Event management

PHASE 6
Ticket management

PHASE 7
Participant registration

PHASE 8
Digital ticket

PHASE 9
QR Code generation

PHASE 10
QR Check-in

PHASE 11
Organizer dashboard

PHASE 12
Reports

PHASE 13
Admin dashboard

PHASE 14
UI/UX polishing

PHASE 15
Testing

PHASE 16
Security review

PHASE 17
Production preparation

Never skip directly from Phase 1 to Phase 17.

---

# IMPORTANT AI RULE

DO NOT generate the entire project in one response.

Implement one phase at a time.

After each phase:

- verify files
- run relevant commands
- run tests
- fix errors
- explain what changed
- wait for the next instruction

The goal is a maintainable Laravel application,
not merely code that appears to work.