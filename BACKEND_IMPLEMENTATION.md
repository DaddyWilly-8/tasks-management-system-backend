# Notifications & Calendar — Backend Implementation

## Overview

| Component       | Who Creates It                                | Delivery                                       |
| --------------- | --------------------------------------------- | ---------------------------------------------- |
| Calendar Events | User (via API) **or** System (business logic) | FullCalendar on frontend                       |
| Notifications   | System (business rules + cron)                | Laravel Echo (real-time) + Firebase FCM (push) |

The two components share no database relationship and scale independently.

**Stack:** Laravel Scheduler · Laravel Event Broadcasting · Laravel WebSockets (Pusher driver / laravel-websockets) · Firebase Cloud Messaging

---

## 1. Calendar Events

### Endpoints

All calendar endpoints extract `user_id` from the URL — no need to pass it in the request body.

| Method   | Endpoint                                                      | Description                   |
| -------- | ------------------------------------------------------------- | ----------------------------- |
| `GET`    | `/api/users/{userId}/calendar/events?start={date}&end={date}` | Fetch events for a date range |
| `POST`   | `/api/users/{userId}/calendar/events`                         | Create a new event            |
| `PUT`    | `/api/users/{userId}/calendar/events/{id}`                    | Update an event               |
| `DELETE` | `/api/users/{userId}/calendar/events/{id}`                    | Delete an event               |

### Event Creation Sources

Calendar events can be created in two ways:

| Source | How                                                                                     | `created_by` |
| ------ | --------------------------------------------------------------------------------------- | ------------ |
| User   | Calls `POST /api/users/{userId}/calendar/events`                                        | `user`       |
| System | Business logic inserts directly (e.g. when a permit is registered, a loan is disbursed) | `system`     |

Both sources write to the same `calendar_events` table. The `created_by` column distinguishes them. User-created events can be edited and deleted via the API; system-created events should be treated as read-only from the user's perspective.

### Data Flow

```
FETCH EVENTS
Frontend → GET /api/users/{userId}/calendar/events?start=YYYY-MM-DD&end=YYYY-MM-DD
    ↓
SELECT * FROM calendar_events
WHERE user_id = {userId}
  AND event_date BETWEEN ? AND ?
  AND status = 'active'
    ↓
Return JSON array → FullCalendar renders tiles (both user and system events)

USER-CREATED: CREATE / UPDATE / DELETE
Frontend → POST or PUT or DELETE /api/users/{userId}/calendar/events/{id}
    ↓
Write to calendar_events table (created_by = 'user')
    ↓
Return updated record to frontend

SYSTEM-CREATED: automatic insert (no API call)
Business event fires (e.g. LoanDisbursed, PermitRegistered)
    ↓
System inserts into calendar_events (created_by = 'system', user_id = owner)
    ↓
Event appears on calendar at next fetch — no further action needed
```

---

## 2. Notifications

### Endpoints

| Method   | Endpoint                                                       | Description                             |
| -------- | -------------------------------------------------------------- | --------------------------------------- |
| `GET`    | `/api/users/{userId}/notifications?status={unread\|read\|all}` | Fetch notifications (default: `unread`) |
| `PUT`    | `/api/users/{userId}/notifications/{id}/read`                  | Mark one notification as read           |
| `PUT`    | `/api/users/{userId}/notifications/read-all`                   | Mark all notifications as read          |
| `POST`   | `/api/users/{userId}/fcm-tokens`                               | Register device token (on login)        |
| `DELETE` | `/api/users/{userId}/fcm-tokens/{token}`                       | Remove device token (on logout)         |

### Data Flow

```
[1] NOTIFICATION CREATION  (triggered by a business event, not by the user)
    e.g. LoanCreated listener, PaymentScheduled observer
        ↓
    INSERT INTO notifications
    (user_id, title, message, type, scheduled_date, scheduled_time, channel, is_sent=FALSE)

[2] CRON JOB  (runs every minute via Laravel Scheduler)
    Artisan command: notifications:process-due
        ↓
    SELECT * FROM notifications
    WHERE scheduled_date = CURRENT_DATE()
      AND scheduled_time <= CURRENT_TIME()
      AND is_sent = FALSE
        ↓
    For each due notification:

    ┌─ channel = 'echo' or 'both' ─────────────────────────────┐
    │  Broadcast NotificationReceived event                     │
    │  on private channel: notifications.{userId}               │
    │  → User receives real-time in-app alert                  │
    └──────────────────────────────────────────────────────────┘

    ┌─ channel = 'firebase' or 'both' ─────────────────────────┐
    │  SELECT token FROM fcm_tokens                             │
    │  WHERE user_id = ? AND is_active = TRUE                   │
    │  → Send FCM push payload to each active token            │
    └──────────────────────────────────────────────────────────┘
        ↓
    UPDATE notifications SET is_sent = TRUE, sent_at = NOW()

[3] FRONTEND RECEIVES
    Echo  → WebSocket fires → in-app toast/bell update
    FCM   → Service Worker fires → browser push notification
```

### Scheduler Configuration

```bash
# Server crontab (required once on server)
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

```php
// Kernel.php
$schedule->command('notifications:process-due')
    ->everyMinute()
    ->withoutOverlapping();
```

### Laravel Echo — Broadcast Contract

The backend broadcasts on a **private channel per user**. The channel name and event name must match exactly what the frontend listens to.

|             | Value                    |
| ----------- | ------------------------ |
| **Channel** | `notifications.{userId}` |
| **Event**   | `NotificationReceived`   |

Broadcast payload:

```json
{
  "notification": {
    "id": 2,
    "title": "Requisition awaiting your approval",
    "message": "REQ/2026/00123 is pending your approval. Amount: TZS 5,000,000.",
    "type": "requisition_approval_pending",
    "is_read": false,
    "created_at": "2026-05-19T08:30:00+03:00",
    "action_url": "/en-US/requisition-approvals",
    "data": {
      "requisition_id": 123,
      "amount": 5000000,
      "currency": "TZS",
      "submitted_by": "Jane Smith"
    }
  },
  "unread_count": 4
}
```

> Include `unread_count` so the frontend bell badge updates immediately without a re-fetch.

### Firebase FCM Payload

```json
{
  "notification": {
    "title": "Requisition awaiting your approval",
    "body": "REQ/2026/00123 — TZS 5,000,000 submitted by Jane Smith"
  },
  "data": {
    "type": "requisition_approval_pending",
    "notification_id": "2",
    "action_url": "/en-US/requisition-approvals",
    "entity_id": "123",
    "priority": "high"
  }
}
```

> All values inside `data` must be **strings** — FCM does not support integers or booleans in the data payload.

---

## 3. Automatic Notification Triggers

Notifications are system-generated. Insert them when the triggering event occurs so the cron job picks them up at the right time.

| Business Event            | `scheduled_date`         | `type`            |
| ------------------------- | ------------------------ | ----------------- |
| Loan created              | `maturity_date − 5 days` | `loan_maturity`   |
| Approval pending          | `expiry_date − 2 days`   | `approval_expiry` |
| Payment approaching       | `due_date − 1 day`       | `payment_due`     |
| Permit approaching expiry | `expiry_date − 7 days`   | `permit_expiry`   |

---

## 4. Key Rules

- `user_id` is always taken from the URL path — never from the request body.
- `is_sent = TRUE` after dispatch — the cron job will never re-process a sent notification.
- Calendar Events and Notifications have **no foreign key relationship** between their tables.
- Echo channel name `notifications.{userId}` and event name `NotificationReceived` are fixed — do not rename without updating the frontend.
