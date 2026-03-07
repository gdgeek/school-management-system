# Task 14.7 — Class Endpoints Frontend Integration Test

**Date**: 2025-03-07  
**Backend**: http://localhost:8084/api  
**Frontend**: http://localhost:5173  
**Test Account**: guanfei / 123456

---

## Smoke Test Results (Automated)

All class API endpoints were verified against the live backend via curl.

| Test | Endpoint | Result | Notes |
|------|----------|--------|-------|
| Login | POST /api/auth/login | ✅ 200 | JWT token returned |
| List classes | GET /api/classes | ✅ 200 | 37 total classes |
| Filter by school | GET /api/classes?school_id=41 | ✅ 200 | Filtered correctly |
| Create class | POST /api/classes | ✅ 200 | ID 127 created, auto-group created |
| Get class | GET /api/classes/127 | ✅ 200 | Correct data returned |
| Update class | PUT /api/classes/127 | ✅ 200 | Name updated correctly |
| Delete (keep groups) | DELETE /api/classes/127?deleteGroups=false | ✅ 200 | Class deleted, groups kept |
| Delete (with groups) | DELETE /api/classes/128?deleteGroups=true | ✅ 200 | Class and groups deleted |
| Auth protection | GET /api/classes (no token) | ✅ 401 | "Missing authentication token" |

**Note**: The backend was previously returning a 500 error due to stale PHP state. A `docker restart xrugc-school-backend` resolved it. Always restart the container after code changes.

---

## Frontend Features Using Class Endpoints

### 1. ClassList.vue (`/classes` route)

**API calls made:**
- `GET /api/classes?page=1&page_size=20` — on mount, loads class list
- `GET /api/schools?page=1&page_size=1000` — on mount, loads school dropdown for filter
- `GET /api/classes?page=N&page_size=20&search=...&school_id=...` — on search/filter/page change
- `DELETE /api/classes/{id}?deleteGroups=true|false` — on delete confirmation

**Features:**
- Paginated class list with search by name
- Filter by school (dropdown populated from `/api/schools`)
- Create button opens ClassForm dialog
- Edit button opens ClassForm dialog pre-filled
- Delete with two-step confirmation: first confirm delete, then choose whether to delete associated groups

### 2. ClassForm.vue (dialog, create/edit mode)

**API calls made:**
- `GET /api/schools?page=1&page_size=1000` — when dialog opens, loads school dropdown
- `POST /api/classes` — on create submit
- `PUT /api/classes/{id}` — on edit submit

**Request body:**
```json
{
  "school_id": 41,
  "name": "Class Name",
  "info": "Optional description"
}
```

### 3. ClassDetailView.vue (`/classes/:id` route)

**API calls made:**
- `GET /api/classes/{id}` — loads class basic info
- `GET /api/classes/{id}/teachers?page=1&page_size=100` — loads teacher list
- `GET /api/classes/{id}/students?page=1&page_size=100` — loads student list

---

## Manual Verification Steps

### Prerequisites
1. Ensure backend is running: `curl http://localhost:8084/api/health`
2. Ensure frontend is running: open http://localhost:5173
3. Login with guanfei / 123456

### Test 1: View Class List
1. Navigate to the Classes section in the sidebar
2. **Expected**: Table loads with paginated class list
3. **Expected**: School filter dropdown is populated

### Test 2: Search Classes
1. Enter a class name in the search box and click Search
2. **Expected**: List filters to matching classes
3. Click Reset
4. **Expected**: Full list restored

### Test 3: Filter by School
1. Select a school from the school dropdown
2. **Expected**: List shows only classes belonging to that school
3. Clear the filter
4. **Expected**: Full list restored

### Test 4: Create Class
1. Click "Create Class" button
2. Select a school from the dropdown
3. Enter a class name (min 2 chars)
4. Optionally enter a description
5. Click Confirm
6. **Expected**: Success message, list refreshes, new class appears
7. **Expected**: An associated group is auto-created in the backend (verify via Groups page)

### Test 5: Edit Class
1. Click the Edit button on any class row
2. **Expected**: Dialog opens with existing data pre-filled
3. Modify the name or description
4. Click Confirm
5. **Expected**: Success message, list refreshes with updated data

### Test 6: Delete Class — Keep Groups
1. Click the Delete button on a class
2. **Expected**: First confirmation dialog appears
3. Click "Next Step"
4. **Expected**: Second dialog asks about associated groups
5. Click "Keep Groups"
6. **Expected**: Class deleted, associated groups remain

### Test 7: Delete Class — Delete Groups
1. Click the Delete button on a class
2. Click "Next Step" in first dialog
3. Click "Delete Groups" in second dialog
4. **Expected**: Class and all associated groups deleted

### Test 8: Class Detail View
1. Navigate to `/classes/{id}` directly (or if there's a detail link)
2. **Expected**: Class basic info displayed
3. **Expected**: Teacher list shown
4. **Expected**: Student list shown

### Test 9: Auth Protection
1. Clear localStorage (remove `access_token`)
2. Try to access the classes page
3. **Expected**: Redirected to login page

---

## API Response Format

All endpoints return:
```json
{
  "code": 200,
  "message": "ok",
  "data": { ... },
  "timestamp": 1234567890
}
```

Error responses:
- `401` — Missing or invalid JWT token
- `403` — Insufficient permissions
- `404` — Class not found
- `422` — Validation error (e.g. missing required fields)

---

## Known Issues / Notes

- The backend requires `docker restart xrugc-school-backend` after any PHP code changes
- The `deleteGroups` parameter is passed as a query string (`?deleteGroups=true`), not in the request body
- Auto-group creation happens server-side when a class is created; the current user's ID is used as the group creator
- The frontend uses a 30-second response cache for GET requests; after create/update/delete the cache is invalidated automatically via the `invalidateCache()` utility
