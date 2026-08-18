# Notifications Module A-Z Implementation Guide

Hii document inaelezea implementation ya Notifications module ya current system kuanzia data layer mpaka frontend usage, ili team nyingine iweze kuiadapt bila kubahatisha.

## 1. What the module solves

Notifications module inafanya vitu vitatu vikuu:

1. Inahifadhi notifications ndani ya database.
2. Inazipeleka kwa user kupitia realtime Echo.
3. Inaruhusu user kuzisoma, kuzimark as read, na kuzisynchronise kwenye frontend.

Kwa use case ya current app, notifications zinatokea hasa wakati:

1. Task imeundwa.
2. Task ime-reassigniwa.
3. Task status imebadilika.
4. Scheduler imefika muda wa notification iliyopangwa.

---

## 2. Architecture overview

Flow ya notification ina layers hizi:

1. Business action happens, mfano task created.
2. Backend creates a notification row kwenye `notifications` table.
3. Scheduler command `notifications:process-due` inachukua notifications ambazo zimefika muda.
4. Command inatuma realtime event kupitia Echo.
5. Frontend inasoma notification list na kuupdate unread badge.

Kwa sababu hiyo, module hii ni simple hybrid:

1. Echo for live browser updates.
2. Database as source of truth.

---

## 3. Database layer

### 3.1 notifications table

Schema ipo kwenye [database/migrations/2026_05_22_092817_create_natifications_table.php](database/migrations/2026_05_22_092817_create_natifications_table.php).

Important fields:

1. `user_id` - owner wa notification.
2. `title` - heading ya notification.
3. `message` - body text.
4. `type` - business event type, mfano `task_assigned`.
5. `channel` - current delivery mode: `echo`.
6. `is_read` - frontend read state.
7. `is_sent` - whether processor already delivered it.
8. `scheduled_date` na `scheduled_time` - trigger ya scheduler.
9. `sent_at` - wakati system ilituma.
10. `read_at` - wakati user aliisoma.
11. `action_url` - deep link ya frontend.
12. `data` - JSON payload kwa extra metadata.

### 3.2 relationships

1. `User` hasMany `Notification`.
2. `Notification` belongsTo `User`.

Models zinapatikana kwenye:

1. [app/Models/User.php](app/Models/User.php)
2. [app/Models/Notification.php](app/Models/Notification.php)

---

## 4. Data model behavior

### 4.1 Notification model

Model iko kwenye [app/Models/Notification.php](app/Models/Notification.php).

Important behavior:

1. `fillable` includes all create/update-safe fields.
2. `casts` converts booleans, dates, datetimes, and JSON payloads.
3. `data` is cast to array so processor can store delivery metadata.

### 4.2 delivery metadata

Current processor stores delivery state inside `notifications.data.delivery`.

Example shape:

```json
{
  "task_id": "15",
  "delivery": {
    "echo": true
  }
}
```

Purpose:

1. Prevent duplicate sends on retries.
2. Track whether Echo already succeeded.

---

## 5. Notification types

Current implementation uses these business types:

1. `task_assigned`
2. `task_reassigned`
3. `task_started`
4. `task_completed`
5. `task_reopened`
6. `task_due_soon` for seeded/demo or future scheduling flows

If another system adopts this module, new types can be added without changing the contract, as long as:

1. `type` remains a string.
2. `data` carries event-specific metadata.
3. Frontend can map type to display label/icon.

---

## 6. API routes

All notification routes live inside the `auth:sanctum` middleware group in [routes/api.php](routes/api.php).

### 6.1 Notifications endpoints

1. `GET /api/users/{userId}/notifications`
2. `PUT /api/users/{userId}/notifications/{id}/read`
3. `PUT /api/users/{userId}/notifications/read-all`

### 6.2 auth

Routes require Sanctum bearer token authentication. User scope validation is done per endpoint so a user can only access their own notifications.

---

## 7. Notifications API contract

### 7.1 List notifications

Method: `GET`

Endpoint: `/api/users/{userId}/notifications`

Query params:

1. `status=unread|read|all`
2. `per_page=1..100`

Behavior:

1. Returns only notifications for the authenticated user.
2. Returns paginated `notifications`.
3. Returns `unread_count` for the full user inbox, not only the page.

### 7.2 Mark one as read

Method: `PUT`

Endpoint: `/api/users/{userId}/notifications/{id}/read`

Behavior:

1. If already read, request remains idempotent.
2. If unread, it sets `is_read=true` and `read_at=now()`.
3. Returns updated `unread_count`.

### 7.3 Mark all as read

Method: `PUT`

Endpoint: `/api/users/{userId}/notifications/read-all`

Behavior:

1. Marks all unread notifications for that user as read.
2. Sets `read_at` and `updated_at`.
3. Returns `updated_count` and `unread_count=0`.

Controller implementation lives in [app/Http/Controllers/Api/NotificationController.php](app/Http/Controllers/Api/NotificationController.php).

---

## 8. Task-driven notification creation

Task flow is the main producer of notifications in current backend.

Implementation is inside [app/Http/Controllers/Api/TaskController.php](app/Http/Controllers/Api/TaskController.php).

### 8.1 task created

When a task is created:

1. The assignee gets a notification.
2. Type is `task_assigned`.
3. `channel` is `echo`.
4. `action_url` points to `/tasks/{id}`.

### 8.1.1 how createTaskAssignmentNotification is triggered

Creation flow inside [app/Http/Controllers/Api/TaskController.php](app/Http/Controllers/Api/TaskController.php) is:

1. `store()` creates the task using `Task::create(...)`.
2. Immediately after create, `store()` calls `createTaskAssignmentNotification($task, $user->id, 'task_assigned')`.
3. Inside `createTaskAssignmentNotification`, it first checks if creator and assignee are the same user.
4. If they are the same, method exits early to avoid self-notification spam.
5. If different, `Notification::create([...])` inserts a new row for the assignee.

The inserted notification payload includes:

1. `user_id` = `assigned_to` from task.
2. `title` and `message` built from type and actor name.
3. `type` = `task_assigned` on create flow.
4. `channel` = `both` in current controller implementation.
5. `is_read=false`, `is_sent=false` so scheduler can process it.
6. `scheduled_date` and `scheduled_time` set to now.
7. `action_url` = `/tasks/{id}` for frontend deep-link.
8. `data` object with `task_id`, `title`, and `assigned_by`.

Short call chain summary:

1. Create task.
2. Call helper method.
3. Helper builds payload.
4. Save notification row.
5. Scheduler command picks unsent row and broadcasts via Echo.

### 8.2 task reassigned

When `assigned_to` changes:

1. The new assignee gets a notification.
2. Type is `task_reassigned`.
3. Notification text mentions reassignment.

### 8.3 hybrid task status permissions

Task status updates use a hybrid rule:

1. Creator has full edit control.
2. Assignee can change status only.
3. Invalid transitions return `422`.

This is useful if another system wants creator/assignee separation without changing notification behavior.

---

## 9. Scheduler and processing command

Command: `notifications:process-due`

File: [app/Console/Commands/ProcessDueNotifications.php](app/Console/Commands/ProcessDueNotifications.php)

### 9.1 what it does

1. Queries notifications where `is_sent=false`.
2. Filters by `scheduled_date <= today`.
3. Filters by `scheduled_time <= now`.
4. Dispatches Echo if channel is `echo`.
5. Marks notification as sent after Echo succeeds.

### 9.2 retry safety

To avoid duplicate delivery:

1. Echo success is tracked in `data.delivery.echo`.
2. On next scheduler run, already delivered notifications are skipped.

### 9.3 scheduler registration

Schedule is registered in [app/Console/Kernel.php](app/Console/Kernel.php).

It runs:

1. every minute.
2. without overlapping.

### 9.4 production cron

Server cron must call:

```bash
* * * * * php /absolute/path/to/artisan schedule:run >> /dev/null 2>&1
```

Without cron, the scheduler will not execute.

### 9.5 how to understand `notifications:process-due`

Njia rahisi ya kuelewa command hii ni kuiona kama "delivery worker" wa notifications.

Inafanya kazi kwa logic hii:

1. Inatafuta rows zenye `is_sent=false`.
2. Inachuja only rows ambazo muda wake umefika (`scheduled_date` na `scheduled_time`).
3. Inabroadcast notification kwa Echo channel ya owner.
4. Ikifanikiwa, inaweka `is_sent=true` na `sent_at=now()`.
5. Ikishindwa, row inabaki `is_sent=false` ili ijaribiwe tena run inayofuata.

Kwa hiyo command hii haicreate notification mpya. Inachakata zile ambazo zipo tayari kwenye `notifications` table.

#### quick verification flow

1. Unda task mpya ili notification row iingie kwenye table.
2. Hakikisha row ina `is_sent=false`.
3. Run command manually:
  `php artisan notifications:process-due`
4. Angalia output, kawaida:
  `Processed notifications: X`
5. Re-check row hiyo, sasa iwe na:
  `is_sent=true` na `sent_at` imejazwa.

#### why this matters to frontend

1. Frontend inaweza kuona notification list kupitia API hata kabla ya realtime event.
2. Echo event inatokea pale command imeichakata.
3. `is_sent` inasaidia backend kujua ni zipi bado hazijapelekwa.

---

## 10. Realtime Echo delivery

### 10.1 event

Event: `NotificationReceived`

File: [app/Events/NotificationReceived.php](app/Events/NotificationReceived.php)

It broadcasts:

1. `notification`
2. `unread_count`

### 10.2 channel

Private channel name:

1. `notifications.{userId}`

### 10.3 authorization

Channel auth is enforced in [routes/channels.php](routes/channels.php).

User can only listen to their own channel.

### 10.4 why Echo matters

Echo is useful when:

1. User is actively in browser.
2. User is on another tab but still has the same browser session active.
3. You want immediate badge updates without waiting for poll.

---

## 11. Frontend integration checklist

Frontend should implement these steps:

1. Login with Sanctum bearer token.
2. Subscribe to Echo channel `notifications.{userId}`.
3. Render unread count from API or realtime event.
4. Fetch notification list on page load and on interval if needed.
5. Mark a notification read when user opens it.
6. Mark all as read from the bell menu.

### 11.1 FullCalendar-style adaptation

If frontend team wants to adapt this module to another UI system:

1. Keep API contracts the same.
2. Map `type` to UI labels and icons.
3. Use `action_url` as deep link.
4. Use `data` for extra metadata.
5. Use `unread_count` as badge source.

### 11.2 browser requirement

For current implementation, frontend must have:

1. Echo listener for live updates.
2. A notification badge or drawer.
3. API fetch on load for initial sync.

---

## 12. Example notification payload

Typical response payload from list endpoint looks like:

```json
{
  "notifications": {
    "data": [
      {
        "id": 12,
        "user_id": 5,
        "title": "New task assigned to you",
        "message": "John assigned task \"Prepare report\" to you.",
        "type": "task_assigned",
        "channel": "echo",
        "is_read": false,
        "is_sent": true,
        "scheduled_date": "2026-06-20",
        "scheduled_time": "10:15:00",
        "sent_at": "2026-06-20T10:15:01Z",
        "read_at": null,
        "action_url": "/tasks/25",
        "data": {
          "task_id": "25",
          "title": "Prepare report",
          "assigned_by": "John",
          "delivery": {
            "echo": true
          }
        }
      }
    ]
  },
  "unread_count": 3
}
```

---

## 13. How another system can adopt this module

If another project wants to use the same pattern, follow this order:

1. Create a `notifications` table with read/sent/schedule fields.
2. Add a user-scoped notifications controller.
3. Create a scheduled processor command.
4. Add realtime broadcast event and private channel auth.
5. Wire task or business events to create notification rows.
6. Expose frontend API endpoints for list/read/read-all.
7. Implement Echo-based live updates on frontend.

The important design principle is this:

1. Database is the source of truth.
2. Realtime is a delivery channel, not the source.
3. Frontend reads from API, not from push alone.

---

## 16. Known design notes

1. Notification dispatch is channel-aware.
2. `read-all` is intentionally separate from single-read.
3. User-scoped authorization is enforced on every user path.
4. The module is safe for web PWA-style live updates because it supports realtime plus API sync.

---

## 17. Summary

Current Notifications module is complete enough for production-style adaptation:

1. Data model exists.
2. API endpoints exist.
3. Task-triggered notifications exist.
4. Scheduler processor exists.
5. Echo realtime exists.
6. Frontend integration contract is clear.

This document is the recommended starting point for any team that wants to copy or adapt the same pattern into another system.