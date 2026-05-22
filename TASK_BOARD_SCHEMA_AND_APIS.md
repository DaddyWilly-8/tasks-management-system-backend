# Task Board — Database Schema & API Routes

---

## Database Schema

### `users`

```sql
CREATE TABLE users (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
);
```

---

### `tasks`

```sql
CREATE TABLE tasks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    status          ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    priority        ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date        DATE NOT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    assigned_to     BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);
```

---

### `notifications`

```sql
CREATE TABLE notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    type            VARCHAR(100) NOT NULL,
    channel         ENUM('echo', 'firebase', 'both') DEFAULT 'both',
    is_read         BOOLEAN DEFAULT FALSE,
    is_sent         BOOLEAN DEFAULT FALSE,
    scheduled_date  DATE NOT NULL,
    scheduled_time  TIME NOT NULL,
    sent_at         TIMESTAMP NULL,
    read_at         TIMESTAMP NULL,
    action_url      VARCHAR(255) NULL,
    data            JSON NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

### `calendar_events`

```sql
CREATE TABLE calendar_events (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NOT NULL,
    title               VARCHAR(255) NOT NULL,
    event_date          DATE NOT NULL,
    type                VARCHAR(100) NOT NULL,
    created_by          ENUM('user', 'system') DEFAULT 'user',
    background_color    VARCHAR(10) DEFAULT '#455A64',
    border_color        VARCHAR(10) DEFAULT '#455A64',
    entity_model        VARCHAR(100) NULL,
    entity_id           BIGINT UNSIGNED NULL,
    details_url         VARCHAR(255) NULL,
    status              ENUM('active', 'cancelled') DEFAULT 'active',
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

### `fcm_tokens`

```sql
CREATE TABLE fcm_tokens (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    token       TEXT NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## API Routes

### Auth

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/register` | Register a new user |
| `POST` | `/api/login` | Login, returns Sanctum token |
| `POST` | `/api/logout` | Logout, revoke token |

---

### Tasks

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tasks` | List all tasks |
| `POST` | `/api/tasks` | Create a task |
| `GET` | `/api/tasks/{id}` | Get a single task |
| `PUT` | `/api/tasks/{id}` | Update a task |
| `DELETE` | `/api/tasks/{id}` | Delete a task |

---

### Notifications

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/users/{userId}/notifications?status=unread` | Fetch notifications |
| `PUT` | `/api/users/{userId}/notifications/{id}/read` | Mark one as read |
| `PUT` | `/api/users/{userId}/notifications/read-all` | Mark all as read |

---

### Calendar Events

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/users/{userId}/calendar/events?start={date}&end={date}` | Fetch events for a date range |
| `POST` | `/api/users/{userId}/calendar/events` | Create a user event |
| `PUT` | `/api/users/{userId}/calendar/events/{id}` | Update a user event |
| `DELETE` | `/api/users/{userId}/calendar/events/{id}` | Delete a user event |

---

### FCM Tokens

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/users/{userId}/fcm-tokens` | Register device token on login |
| `DELETE` | `/api/users/{userId}/fcm-tokens/{token}` | Remove device token on logout |
