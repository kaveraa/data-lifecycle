# Data Lifecycle

<p align="center"><img src="https://raw.githubusercontent.com/kaveraa/data-lifecycle/73997d0/art/banner.svg" alt="Data Lifecycle" width="100%"></p>

[![Tests](https://github.com/kaveraa/data-lifecycle/actions/workflows/tests.yml/badge.svg)](https://github.com/kaveraa/data-lifecycle/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/kaveraa/data-lifecycle.svg)](https://packagist.org/packages/kaveraa/data-lifecycle)
[![Downloads](https://img.shields.io/packagist/dt/kaveraa/data-lifecycle.svg)](https://packagist.org/packages/kaveraa/data-lifecycle)
[![PHP](https://img.shields.io/packagist/dependency-v/kaveraa/data-lifecycle/php.svg)](https://packagist.org/packages/kaveraa/data-lifecycle)
[![License](https://img.shields.io/github/license/kaveraa/data-lifecycle.svg)](https://github.com/kaveraa/data-lifecycle/blob/main/LICENSE)

**English** - [Français](https://github.com/kaveraa/data-lifecycle/blob/main/README.fr.md)

The GDPR asks you not to keep personal data longer than needed (article 5.1.e). In real life almost nobody does it: you would have to find the idle accounts, warn the people, disable without breaking anything, leave a way back, then anonymise or delete. And be able to show it.

This package does that journey, with **one declaration per entity**, for **Laravel** and for **Symfony / Doctrine**.

```php
#[KeepFor('3 years')]             // kept 3 years after the last sign of life
#[WarnBefore('30 days')]          // an email 30 days before the deadline
#[DisableFirst('30 days')]        // disabled, then 30 days to come back
#[ThenAnonymise('email', 'name')] // after that the row stays, but anonymous
class User
{
}
```

```bash
php artisan lifecycle:report   # what would happen, without writing anything
php artisan lifecycle:run      # for real
```

- **The whole journey**: warn, disable, leave a grace period, then anonymise or delete. Not only delete.
- **Observe mode**: `lifecycle:report` says exactly how many rows would be touched, and which ones, without a single write. This is what you run in production for weeks before daring the rest.
- **Reversible**: while the grace period lasts, a person who comes back finds the account untouched, and the clock restarts.
- **No schema is forced**: a policy only reads the columns it needs. A simple policy works with a single date column.
- **Two frameworks, one package**: the cycle is written once, in plain PHP; Laravel and Doctrine are only drivers.
- **Testable**: the clock can be replaced (`FrozenClock`), so three years pass in three lines of test.
- **Light**: two PSR interfaces, nothing else.

---

## Table of contents

- [The problem](#the-problem)
- [Requirements](#requirements)
- [Installation](#installation)
- [Write a policy](#write-a-policy)
- [The columns to add](#the-columns-to-add)
- [Run the cycle](#run-the-cycle)
- [Observe mode](#observe-mode)
- [Warn the person](#warn-the-person)
- [When the person comes back](#when-the-person-comes-back)
- [Anonymise](#anonymise)
- [The activity signal](#the-activity-signal)
- [Where a row stands](#where-a-row-stands)
- [All the options](#all-the-options)
- [What this package does not do](#what-this-package-does-not-do)
- [Development](#development)

## The problem

A database keeps everything, forever, by default. Accounts left behind six years ago are still there, with their address, their name, their history. It is a risk if data leaks, and it goes against the GDPR.

The usual answer is a home-made script, run once, that deletes in bulk. It is frightening, so nobody runs it. This package replaces that script with something you dare to run:

```
last sign of life                                                          today
        |                                                                    |
        |------------------- 3 years (KeepFor) ------------------|           |
                                          |                      |           |
                                    reminder D-30            disabled    anonymised
                                    (WarnBefore)          (DisableFirst)  or deleted
                                                          |<- 30 days ->|
                                                           to come back
```

## Requirements

- PHP 8.2 or more.
- Laravel 12+, or Symfony 7.2+ with Doctrine ORM 3+.
- One date column per entity: the last sign of life (`last_active_at`, `last_order_at`, `sent_at`, your choice).

## Installation

```bash
composer require kaveraa/data-lifecycle
```

### Laravel

```bash
php artisan lifecycle:install
```

The command publishes `config/data-lifecycle.php` and an example migration. The service provider is found on its own.

### Symfony

Add the bundle in `config/bundles.php`:

```php
return [
    // ...
    Kaveraa\DataLifecycle\Symfony\DataLifecycleBundle::class => ['all' => true],
];
```

Then write `config/packages/data_lifecycle.yaml`:

```yaml
data_lifecycle:
    discover:
        - App\Entity\User
```

## Write a policy

Two ways, as you like. Attributes read better, configuration is handier when the rule changes with the environment. If both exist for the same class, configuration wins.

### With attributes

```php
use Kaveraa\DataLifecycle\Attribute\DisableFirst;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenAnonymise;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;
use Kaveraa\DataLifecycle\Attribute\WarnBefore;
use Kaveraa\DataLifecycle\Strategy;

#[KeepFor('3 years')]
#[WarnBefore('30 days')]
#[WarnBefore('7 days')]
#[DisableFirst('30 days')]
#[ThenAnonymise('email', 'name', ['birth_date' => Strategy::YearOnly])]
class User extends Authenticatable
{
}
```

An entity can have only a start and an end:

```php
#[KeepFor('90 days', since: 'sent_at')]
#[ThenDelete]
class Invitation
{
}
```

Then say where to look for these classes, in `discover`:

```php
// config/data-lifecycle.php
'discover' => [
    App\Models\User::class,
    App\Models\Invitation::class,
],
```

### With configuration

```php
// config/data-lifecycle.php
'subjects' => [
    App\Models\User::class => [
        'keep_for' => '3 years',
        'warn_before' => ['30 days', '7 days'],
        'grace' => '30 days',
        'anonymise' => ['email' => 'email', 'name' => 'text'],
    ],

    App\Models\Invitation::class => [
        'keep_for' => '90 days',
        'fields' => ['since' => 'sent_at'],
        'delete' => true,
    ],
],
```

Durations are written in plain words: `3 years`, `18 months`, `30 days`, `48 hours`. The ISO 8601 form (`P30D`) works too.

## The columns to add

You only add the columns your policy needs.

| Column | When it is needed | Type |
|---|---|---|
| `last_active_at` | always (it is the starting point) | date, nullable |
| `lifecycle_warn_stage` | only with `#[WarnBefore]` | small integer, default 0 |
| `lifecycle_warned_at` | only with `#[WarnBefore]` | date, nullable |
| `disabled_at` | only with `#[DisableFirst]` | date, nullable |
| `anonymised_at` | only with `#[ThenAnonymise]` | date, nullable |

So a `#[KeepFor] + #[ThenDelete]` policy needs **only** the date column. The names can be changed, globally or policy by policy:

```php
'fields' => ['since' => 'seen_at', 'disabled_at' => 'blocked_at'],
```

Add an index on the date column, and on `disabled_at`: they carry the queries.

## Run the cycle

```bash
php artisan lifecycle:run                       # everything, for real
php artisan lifecycle:run --dry-run             # without writing anything
php artisan lifecycle:run --subject="App\Models\User"
php artisan lifecycle:run --step=warn           # only the reminders
php artisan lifecycle:run --limit=500           # at most 500 rows per step
```

With Symfony the same commands are `bin/console lifecycle:run` and `bin/console lifecycle:report`.

Once a day is enough. Laravel:

```php
// routes/console.php
Schedule::command('lifecycle:run')->dailyAt('03:30');
```

Symfony, with cron:

```
30 3 * * * /usr/bin/php /var/www/bin/console lifecycle:run
```

A run is made to be stopped and resumed: `--limit` bounds every step, and the next run carries on where it stopped.

### The first run

On a database that was never cleaned, the whole backlog comes out at once: thousands of rows are already past the deadline. They get their first reminder, then are disabled in the same run, which leaves nobody time to react. Two precautions:

1. Run `lifecycle:report` first, and look at the numbers.
2. Catch up gently: play `--step=warn` alone for the length of your reminder (30 days if you warn 30 days before), and only then the full command.

```bash
php artisan lifecycle:run --step=warn --limit=200   # for 30 days
php artisan lifecycle:run                           # after that
```

## Observe mode

This is the front door of the package. Nothing is written, no event is sent, and the report says what would happen:

```bash
php artisan lifecycle:report
```

```
Dry run: nothing was written.

 Entity       Step       Rows     Examples
 User         warn          412   18, 45, 61, 88, 90
 User         disable        73   7, 12, 30, 44, 51
 User         erase          19   3, 9, 14, 21, 25
 Invitation   erase       1 204   2, 4, 5, 6, 8
```

A row that is very late can show up twice, in `warn` and in `disable`: in observe mode nothing is written between the two steps, so the report shows exactly what a real run would do, one step after the other.

Let it run for a few weeks in a scheduled task, watch the numbers settle, then remove `--dry-run`. Writing can also be blocked from the configuration while you set things up:

```dotenv
DATA_LIFECYCLE_DRY_RUN=true
```

As long as this setting is true, `lifecycle:run` stays in observe mode and says so.

## Warn the person

The package sends no email: it tells you when to send one, and you write the message. There are five events, listened to like any event of your framework.

```php
use Kaveraa\DataLifecycle\Event\SubjectWarned;

Event::listen(function (SubjectWarned $event): void {
    $user = $event->entity();

    Mail::to($user)->send(new AccountExpiring(
        dueAt: $event->dueAt,        // date of the disabling
        reminder: $event->warnIndex, // 0 for the first reminder, 1 for the next one
    ));
});
```

| Event | When |
|---|---|
| `SubjectWarned` | a reminder has to go out |
| `SubjectDisabled` | the row has just been disabled |
| `SubjectAnonymised` | the personal data is gone |
| `SubjectDeleted` | the row has been deleted |
| `SubjectReactivated` | the person came back |

No event is sent in observe mode.

## When the person comes back

This is the whole point of the grace period: disabling is not deleting.

```php
use Kaveraa\DataLifecycle\Lifecycle;

public function login(Request $request, Lifecycle $lifecycle)
{
    // ...
    $lifecycle->reactivate($user); // no more reminder, no more disabling, clock back to zero
}
```

`reactivate()` returns `false` on a row that is already anonymised: what is gone does not come back.

## Anonymise

Anonymising instead of deleting keeps your counts right (orders, statistics, invoices) while the person disappears.

```php
#[ThenAnonymise('email', 'name')]
```

Every field gets a strategy. Without one, `Strategy::Auto` chooses from the name: a field that contains `mail` gets an address, everything else gets `[removed]`.

| Strategy | Result |
|---|---|
| `Strategy::Email` | `anonymous-42@anonymous.invalid`, one per row |
| `Strategy::Text` | `Anonymous` |
| `Strategy::Redact` | `[removed]` |
| `Strategy::EmptyText` | an empty string |
| `Strategy::Nullify` | `null` (the column must accept it) |
| `Strategy::Zero` | `0` |
| `Strategy::YearOnly` | keeps the year of a date, sets the 1st of January |
| `Strategy::Hash` | a fingerprint: the value does not come back, but two equal values stay equal |

`Strategy::Hash` helps when you need to know that two rows came from the same person, without knowing who. The replacement texts can be changed in the configuration.

## The activity signal

Everything rests on a date you can trust. Writing `last_active_at` on every request costs one write per request: not acceptable. The package ships a guard that writes only once per window (15 minutes by default).

Laravel, in `bootstrap/app.php`:

```php
$middleware->web(append: [
    \Kaveraa\DataLifecycle\Laravel\Middleware\TrackActivity::class,
]);
```

With Symfony the listener is wired by the bundle. The window is set with `activity.throttle` (in minutes; `0` turns it off).

Watch out for the trap: an automatic login from a cookie, a monitoring call or a scheduled task that touches the table will reset the clock. An account that looks active because a robot goes through it is not active. Pick a deliberate action of the person as the starting point.

## Where a row stands

```php
$lifecycle->stageOf($user);  // Stage::Active, Warned, Disabled or Erased
$lifecycle->dueAt($user);    // date of the coming disabling
```

With Laravel, the `HasLifecycle` trait puts the same answers on the model, plus scopes:

```php
use Kaveraa\DataLifecycle\Laravel\Concerns\HasLifecycle;

class User extends Authenticatable
{
    use HasLifecycle;
}

User::active()->count();
User::disabled()->get();
$user->lifecycleStage();
$user->lifecycleDueAt();
$user->reactivate();
```

## All the options

| Option | Default | Role |
|---|---|---|
| `dry_run` | `false` | Blocks every write, everywhere |
| `limit` | `1000` | Rows at most per step and per policy |
| `fields.since` | `last_active_at` | Column of the last sign of life |
| `fields.warn_stage` | `lifecycle_warn_stage` | How many reminders were sent |
| `fields.warned_at` | `lifecycle_warned_at` | Date of the last reminder |
| `fields.disabled_at` | `disabled_at` | Date of the disabling |
| `fields.anonymised_at` | `anonymised_at` | Date of the anonymisation |
| `anonymiser.email_domain` | `anonymous.invalid` | Domain of the replacement addresses |
| `anonymiser.redacted_text` | `[removed]` | Replacement text |
| `anonymiser.anonymous_name` | `Anonymous` | Replacement name |
| `anonymiser.pepper` | the application key | Salt of `Strategy::Hash` |
| `activity.throttle` | `15` | Minutes between two writes of the activity signal |
| `subjects` | `[]` | The policies written in configuration |
| `discover` | `[]` | The classes whose attributes are read |

## What this package does not do

- **This is not legal advice.** The durations are yours: they depend on your activity and your duties (an invoice is kept ten years, a rejected job application two years). The package applies the duration you decide.
- **It does not hold your record of processing activities** and does not answer access or portability requests.
- **It does not touch your backups** or your logs: a row anonymised in the database is still readable in yesterday's backup. Think about how long you keep your backups.
- **It does not guess your relations**: anonymising a user does not empty the linked tables. Write one policy per entity, or clean up in a listener of `SubjectAnonymised`.

## Development

```bash
git clone https://github.com/kaveraa/data-lifecycle.git
cd data-lifecycle
composer install
vendor/bin/phpunit
```

To suggest a change, read the [CONTRIBUTING.md](https://github.com/kaveraa/data-lifecycle/blob/main/CONTRIBUTING.md) guide. See the [CHANGELOG](https://github.com/kaveraa/data-lifecycle/blob/main/CHANGELOG.md) for the history of versions.

To report a vulnerability, open a [private security advisory](https://github.com/kaveraa/data-lifecycle/security/advisories/new) rather than a public issue.

## License

MIT. See [LICENSE](https://github.com/kaveraa/data-lifecycle/blob/main/LICENSE).
