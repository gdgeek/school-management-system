# PSR-15 Migration Rollback Procedure

> Keep this document accessible offline. In an incident, every minute counts.

---

## 1. Overview

### What Was Migrated

The backend was migrated from a monolithic `index.php` switch-case router to a pure PSR-15 middleware stack. All API endpoints (`/api/auth`, `/api/schools`, `/api/classes`, `/api/groups`, `/api/students`, `/api/teachers`, `/api/users`) now route through the PSR-15 pipeline.

### What Rollback Means

Rolling back restores the previous `index.php` (switch-case routing) and removes the PSR-15 bootstrap. The Service and Repository layers are unchanged — only the HTTP routing layer is reverted.

### When to Trigger Rollback

Trigger rollback if **any** of the following occur after deployment:

- Error rate on `/api/*` endpoints exceeds 1% (baseline: ~0%)
- Any endpoint returns 500 for valid requests
- Authentication failures spike unexpectedly
- Response format changes break the frontend
- Performance degrades >50ms average response time
- Health check `GET /api/health` returns non-200

---

## 2. Pre-Rollback Checklist

Before executing rollback, confirm:

- [ ] Issue is confirmed reproducible (not a transient network blip)
- [ ] Error logs reviewed: `docker logs xrugc-school-backend --tail 100`
- [ ] Target rollback commit identified (see Section 3.1)
- [ ] Team lead / stakeholders notified (use template in Section 5)
- [ ] Database state confirmed unchanged (this migration has no schema changes)
- [ ] Decision made: rollback vs hotfix (rollback if fix ETA > 15 min)

---

## 3. Rollback Steps (Manual)

### 3.1 Identify Rollback Target Commit

```bash
# View recent commits to find the pre-migration commit
git log --oneline -20 school-management-system/backend/public/index.php

# Look for the last commit before PSR-15 migration
# The target is the commit just before "PSR-15" or "Phase 6" appears in messages
```

Note the commit hash (e.g., `abc1234`).

### 3.2 Create Rollback Branch and Reset

```bash
# Create a rollback branch from current HEAD for safety
git checkout -b rollback/psr15-$(date +%Y%m%d-%H%M)

# Restore index.php to the target commit
git checkout <target-commit-hash> -- school-management-system/backend/public/index.php

# If config files also need reverting (routes, middleware, di):
git checkout <target-commit-hash> -- school-management-system/backend/config/

# Commit the rollback
git add school-management-system/backend/public/index.php
git commit -m "rollback: revert PSR-15 migration to <target-commit-hash>"
```

### 3.3 Restart Docker Container

```bash
docker restart xrugc-school-backend
```

Wait ~5 seconds for the container to fully restart.

### 3.4 Verify Health Check

```bash
curl -s http://localhost:8084/api/health | python3 -m json.tool
```

Expected response:

```json
{
  "code": 200,
  "message": "ok",
  "data": { "status": "healthy" },
  "timestamp": 1234567890
}
```

If health check fails, check container logs:

```bash
docker logs xrugc-school-backend --tail 50
```

### 3.5 Verify Core Endpoints

```bash
# Test login
curl -s -X POST http://localhost:8084/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"guanfei","password":"123456"}' | python3 -m json.tool

# Test authenticated endpoint (replace TOKEN with login response token)
curl -s http://localhost:8084/api/schools \
  -H "Authorization: Bearer TOKEN" | python3 -m json.tool
```

### 3.6 Post-Rollback Monitoring (30 minutes)

Monitor for 30 minutes after rollback:

```bash
# Watch error logs in real time
docker logs -f xrugc-school-backend 2>&1 | grep -i "error\|fatal\|exception"
```

Check every 5 minutes:
- [ ] 5 min: Health check still passing
- [ ] 10 min: No new errors in logs
- [ ] 20 min: Frontend functioning normally
- [ ] 30 min: Error rate back to baseline — rollback confirmed stable

---

## 4. Data Considerations

### No Schema Changes

This migration is **code-only**. No database schema was modified. Rolling back the code is sufficient — no data migration or schema rollback is needed.

### Redis Cache

If caching behavior changed during the migration, flush Redis to avoid stale cache serving incorrect responses:

```bash
docker exec -it xrugc-redis redis-cli FLUSHDB
```

> Only do this if you suspect cache-related issues. Normal rollback does not require a cache flush.

### Session / JWT Tokens

JWT tokens issued before rollback remain valid — they are stateless and not affected by the routing layer change.

---

## 5. Communication Template

Send this to the team immediately when rollback is triggered:

---

**Subject**: [ACTION] PSR-15 Rollback Initiated — school-management-system backend

**Status**: 🔴 Rollback in progress

**Issue**: [Brief description of the problem]

**Impact**: [Which endpoints / features are affected]

**Action**: Rolling back `school-management-system/backend` to commit `<hash>` (pre-PSR-15 state)

**ETA**: ~5 minutes to restore service

**Next steps**: Root cause investigation will follow. Re-deployment will require a fix and re-review.

**Contact**: [Your name / channel]

---

Update the team when rollback is complete:

**Status**: ✅ Rollback complete — service restored at [time]

---

## 6. Recovery After Rollback

### Root Cause Investigation

1. Collect logs from the failed deployment window:
   ```bash
   docker logs xrugc-school-backend --since "2024-01-01T10:00:00" > /tmp/incident-logs.txt
   ```

2. Reproduce the issue locally against the PSR-15 branch.

3. Check the most common failure points:
   - Middleware order in `config/middleware.php`
   - Route registration in `config/routes.php`
   - DI container bindings in `config/di.php`
   - Exception handling in `AbstractController`

### Re-Deploy After Fix

1. Fix the root cause on a feature branch.
2. Run the full test suite:
   ```bash
   docker exec xrugc-school-backend php vendor/bin/phpunit --testdox
   ```
3. Restart and verify:
   ```bash
   docker restart xrugc-school-backend
   curl -s http://localhost:8084/api/health
   ```
4. Deploy with team sign-off.
5. Monitor for 30 minutes (same checklist as Section 3.6).

### Rollback Time Target

| Step | Target Time |
|------|-------------|
| Identify commit | 1 min |
| Git reset + commit | 2 min |
| Docker restart | 1 min |
| Verify health check | 1 min |
| **Total** | **< 5 min** |
