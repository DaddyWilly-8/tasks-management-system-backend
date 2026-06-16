# Notifications Implementation Guide (Current State)

## Lengo la document
Hii ni guide ya hatua zote zilizofanyika kuanzia Notifications module mpaka hali ya sasa ya backend.
Inalenga kusaidia frontend, backend, na DevOps ku-run na ku-test flow nzima bila kubahatisha.

## Scope iliyokamilika
1. Notifications CRUD essentials kwa user wake mwenyewe.
2. Mark single notification as read.
3. Mark all notifications as read.
4. Auto-notification wakati task ina-assigned au re-assigned.
5. Scheduler command ya kuchakata notifications zilizofikia muda.
6. Realtime broadcast event kwa private user channel.
7. FCM token management endpoints.
8. Actual FCM dispatch logic (HTTP request kwenda Firebase endpoint).

---

## Files muhimu zilizopo sasa
1. routes/api.php
2. app/Http/Controllers/Api/NotificationController.php
3. app/Http/Controllers/Api/TaskController.php
4. app/Http/Controllers/Api/FcmTokenController.php
5. app/Models/Notification.php
6. app/Models/FcmToken.php
7. app/Models/User.php
8. app/Console/Commands/ProcessDueNotifications.php
9. app/Console/Kernel.php
10. app/Events/NotificationReceived.php
11. routes/channels.php
12. app/Providers/BroadcastServiceProvider.php
13. config/services.php
14. .env.example
15. database/factories/NotificationFactory.php
16. database/seeders/DatabaseSeeder.php

---

## 1) Notifications API Contract

### A. Fetch notifications
Method: GET
Endpoint: /api/users/{userId}/notifications

Query params:
1. status = unread | read | all (default unread)
2. per_page = 1..100 (default 15)

Behavior:
1. User anaona notifications zake tu.
2. userId tofauti na logged-in user -> 403.

Response shape:
1. notifications (paginated object)
2. unread_count

### B. Mark one as read
Method: PUT
Endpoint: /api/users/{userId}/notifications/{id}/read

Behavior:
1. Inamark notification moja as read.
2. Inarudisha unread_count mpya.

### C. Mark all as read
Method: PUT
Endpoint: /api/users/{userId}/notifications/read-all

Behavior:
1. Inamark unread zote za user kuwa read.
2. Inarudisha updated_count.
3. Inarudisha unread_count = 0.

---

## 2) Auto Notifications kutoka Task flow

Imeunganishwa ndani ya TaskController:
1. Task create -> assignee anapata notification ya task_assigned.
2. Task update ikiwa assigned_to imebadilika -> assignee mpya anapata task_reassigned.
3. Ikiwa creator na assignee ni mtu yule yule, notification haisendwi.

Notification fields zinazoandikwa:
1. user_id = assignee
2. title/message = assignment context
3. type = task_assigned au task_reassigned
4. channel = both
5. is_read = false
6. is_sent = false
7. scheduled_date/scheduled_time = now
8. action_url = /tasks/{id}
9. data payload ya task basics

---

## 3) Scheduler + Due Processing

### Command
Name: notifications:process-due
Class: ProcessDueNotifications

### Kazi ya command
1. Inachukua notifications zenye:
   - is_sent = false
   - scheduled_date <= leo
   - scheduled_time <= sasa
2. Kulingana na channel:
   - echo au both -> emit realtime broadcast
   - firebase au both -> send push to active fcm tokens
3. Ikiwa dispatch zote zilizotakiwa zimefanikiwa:
   - is_sent = true
   - sent_at = now

### Scheduler registration
Imewekwa ndani ya Kernel:
1. everyMinute
2. withoutOverlapping

### Cron ya server (production)
Weka kwenye crontab ya server:
* * * * * php /absolute/path/to/tasks-management-system-backend/artisan schedule:run >> /dev/null 2>&1

---

## 4) Realtime Broadcast (Echo)

### Event
Name: NotificationReceived

Payload:
1. notification object
2. unread_count

### Private channel
notifications.{userId}

### Authorization
Imewekwa kwenye routes/channels.php:
User ana-listen channel yake tu.

### Broadcast auth route
Imewekwa kutumia auth:sanctum middleware kupitia BroadcastServiceProvider.

---

## 5) FCM Integration (Actual Sender)

### FCM settings
config/services.php ina:
1. fcm.server_key
2. fcm.send_url

### Env vars
Weka kwenye .env ya environment yako:
1. FCM_SERVER_KEY=YOUR_SERVER_KEY
2. FCM_SEND_URL=https://fcm.googleapis.com/fcm/send

### FCM Token endpoints
1. POST /api/users/{userId}/fcm-tokens
   - body: token
   - upsert token + activate
2. DELETE /api/users/{userId}/fcm-tokens/{token}
   - deactivate token

### Delivery rules ndani ya processor
1. Huchukua active tokens za user.
2. Hutuma HTTP request kwa kila token.
3. Invalid token errors (NotRegistered, InvalidRegistration) -> token inawekwa inactive.
4. Failure ya firebase route inazuia notification ku-mark is_sent true (inaweza kurudiwa kwenye next cycle).

---

## 6) Database/Test Data

### Factory
NotificationFactory ipo kwa test/demo data.

### Seeder
DatabaseSeeder sasa inatengeneza:
1. users
2. tasks
3. notifications

---

## 7) Frontend Integration Checklist

1. Login upate bearer token.
2. Register FCM token baada ya login:
   - POST /api/users/{id}/fcm-tokens
3. Load notification bell list:
   - GET /api/users/{id}/notifications?status=unread&per_page=10
4. Mark single as read on click:
   - PUT /api/users/{id}/notifications/{notificationId}/read
5. Mark all as read action:
   - PUT /api/users/{id}/notifications/read-all
6. Listen realtime channel:
   - notifications.{userId}
   - event name: NotificationReceived
7. Logout cleanup:
   - DELETE /api/users/{id}/fcm-tokens/{token}

---

## 8) Backend Verification Commands

1. Route verify:
php artisan route:list | grep notifications

2. Process due notifications manually:
php artisan notifications:process-due

3. Check unsent count:
php artisan tinker --execute="echo App\\Models\\Notification::where('is_sent',false)->count();"

4. Seed test data:
php artisan db:seed

---

## 9) Common Issues na Fix

1. FCM not sending
   - Hakikisha FCM_SERVER_KEY ipo kwenye .env
   - Kisha run php artisan config:clear

2. User anaona 403 kwenye notifications/fcm endpoints
   - Hakikisha userId ya URL inafanana na logged-in user id

3. Realtime haifanyi
   - Hakikisha BROADCAST_DRIVER ime-configured kwa provider unayotumia
   - Hakikisha frontend ina-listen channel notifications.{userId}

4. Notifications hazichakatwi auto
   - Hakikisha crontab ya schedule:run imewekwa server side

---

## 10) Hali ya sasa (summary)
Notifications module iko functional kwa:
1. API fetch/read/read-all
2. task-triggered notification creation
3. scheduled processing
4. realtime event emit
5. FCM token management
6. FCM send flow ya msingi

Kama hatua inayofuata, unaweza kuongeza:
1. Firebase HTTP v1 authentication (service account flow)
2. Retry strategy na dead-letter handling kwa failed notifications
3. Integration tests kwa endpoints na command
