# Task 14 — Class Endpoints: Frontend Manual Test Steps

**Base URL**: `http://localhost:8084/api`  
**Frontend**: `http://localhost:5173`  
**Test account**: username `guanfei` / password `123456`

---

## Prerequisites

1. Docker containers are running: `docker compose up -d`
2. Backend container is up-to-date: `docker restart xrugc-school-backend`
3. Open browser DevTools → Network tab to inspect API calls.

---

## Step 1 — Login

1. Navigate to `http://localhost:5173`.
2. Enter credentials: `guanfei` / `123456` and click **Login**.
3. **Expected**: Redirected to dashboard. No error messages.
4. **Verify (DevTools)**: `POST /api/auth/login` returns HTTP 200 with body:
   ```json
   { "code": 200, "message": "...", "data": { "token": "..." }, "timestamp": ... }
   ```

---

## Step 2 — List Classes

1. Navigate to the **Classes** section in the sidebar.
2. **Expected**: A list of classes is displayed (may be empty if no classes exist yet).
3. **Verify (DevTools)**: `GET /api/classes` returns HTTP 200 with body:
   ```json
   {
     "code": 200,
     "message": "ok",
     "data": {
       "items": [...],
       "pagination": { "total": ..., "page": 1, "pageSize": 20, "totalPages": ... }
     },
     "timestamp": ...
   }
   ```

---

## Step 3 — Create a Class (and verify auto-group creation)

1. Click **Create Class** (or equivalent button).
2. Fill in:
   - **Name**: `Test Class Frontend`
   - **School**: select any existing school from the dropdown
3. Click **Save / Submit**.
4. **Expected**: Class appears in the list. No error messages.
5. **Verify (DevTools)**: `POST /api/classes` returns HTTP 200 with body:
   ```json
   {
     "code": 200,
     "message": "Class created successfully",
     "data": {
       "id": <positive integer>,
       "name": "Test Class Frontend",
       "school_id": <school id>,
       "group_id": <positive integer>
     },
     "timestamp": ...
   }
   ```
6. **Verify auto-group**: `group_id` in the response is a positive integer — this confirms the associated group was automatically created.

---

## Step 4 — Filter Classes by School

1. In the Classes list, use the **School** filter/dropdown to select the school used in Step 3.
2. **Expected**: Only classes belonging to that school are shown.
3. **Verify (DevTools)**: `GET /api/classes?school_id=<id>` returns HTTP 200 and all items in `data.items` have `school_id` equal to the filtered school.

---

## Step 5 — View Class Detail

1. Click on the class created in Step 3.
2. **Expected**: Class detail page shows name, school, and associated group information.
3. **Verify (DevTools)**: `GET /api/classes/<id>` returns HTTP 200 with body:
   ```json
   {
     "code": 200,
     "message": "ok",
     "data": { "id": ..., "name": "Test Class Frontend", "school_id": ... },
     "timestamp": ...
   }
   ```

---

## Step 6 — Update a Class

1. On the class detail page (or list), click **Edit**.
2. Change the name to `Test Class Frontend Updated`.
3. Click **Save**.
4. **Expected**: Class name updates in the UI. No error messages.
5. **Verify (DevTools)**: `PUT /api/classes/<id>` returns HTTP 200 with body:
   ```json
   {
     "code": 200,
     "message": "Class updated successfully",
     "data": { "id": ..., "name": "Test Class Frontend Updated", ... },
     "timestamp": ...
   }
   ```

---

## Step 7 — Delete a Class (without deleting groups)

1. Create a second class (e.g., `Class to Delete`) following Step 3.
2. Select that class and click **Delete**.
3. Confirm the deletion dialog.
4. **Expected**: Class is removed from the list. No error messages.
5. **Verify (DevTools)**: `DELETE /api/classes/<id>` (no `deleteGroups` param) returns HTTP 200:
   ```json
   {
     "code": 200,
     "message": "Class deleted successfully",
     "data": [],
     "timestamp": ...
   }
   ```
6. **Verify**: The associated group still exists (check Groups section — it should still be listed).

---

## Step 8 — Delete a Class with deleteGroups=true

> This step may require using the browser console or a tool like curl/Postman if the frontend does not expose a "delete groups" option.

1. Create another class (e.g., `Class with Groups`).
2. Note the `group_id` from the creation response.
3. Send: `DELETE /api/classes/<id>?deleteGroups=true` with `Authorization: Bearer <token>`.
4. **Expected**: HTTP 200 response.
5. **Verify**: The group with the noted `group_id` no longer appears in the Groups list.

**curl example**:
```bash
curl -X DELETE "http://localhost:8084/api/classes/<id>?deleteGroups=true" \
  -H "Authorization: Bearer <token>"
```

---

## Step 9 — Verify 401 Without Authentication

1. Open a new incognito window (no session).
2. Try to access `http://localhost:8084/api/classes` directly.
3. **Expected**: HTTP 401 response:
   ```json
   { "code": 401, "message": "...", "data": null, "timestamp": ... }
   ```

---

## Step 10 — Verify 404 for Non-existent Class

```bash
curl -H "Authorization: Bearer <token>" \
  http://localhost:8084/api/classes/999999
```

**Expected**:
```json
{ "code": 404, "message": "Class not found", "data": null, "timestamp": ... }
```

---

## Pass Criteria

| Step | Check | Pass? |
|------|-------|-------|
| 1    | Login returns 200 with token | ☐ |
| 2    | List classes returns paginated data | ☐ |
| 3    | Create class returns 200 with positive `group_id` | ☐ |
| 4    | school_id filter returns only matching classes | ☐ |
| 5    | Show class returns correct data | ☐ |
| 6    | Update class returns updated name | ☐ |
| 7    | Delete class (no groups) returns 200; group still exists | ☐ |
| 8    | Delete class with deleteGroups=true removes group | ☐ |
| 9    | Unauthenticated request returns 401 | ☐ |
| 10   | Non-existent class returns 404 | ☐ |
