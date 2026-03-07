# Rollback Triggers and Decision Criteria — PSR-15 Migration

**Version**: 1.0  
**Last Updated**: 2026-03-07  
**Applies To**: PSR-15 middleware migration  
**Rollback Script**: `bin/rollback.sh`  
**Communication Plan**: `docs/rollback-communication-plan.md`

---

## 1. Automatic / Immediate Rollback Triggers

These conditions require **no discussion** — execute `bin/rollback.sh` immediately.

| Trigger | Threshold | How to Detect |
|---------|-----------|---------------|
| Health check fails | `GET /api/health` returns non-200 for **≥ 2 consecutive checks** (within 60s of deploy) | `curl -s -o /dev/null -w "%{http_code}" http://localhost:8084/api/health` |
| Login endpoint down | `POST /api/auth/login` returns 5xx or no response for **≥ 2 attempts** | `curl -s -X POST .../api/auth/login -d '{"username":"guanfei","password":"123456"}'` |
| Error rate spike | HTTP 5xx responses exceed **10% of requests** over any 5-minute window | Container logs: `docker logs xrugc-school-backend --since '5m' \| grep -c "HTTP/.*5[0-9][0-9]"` |
| Container crash loop | Container restarts **≥ 2 times** within 10 minutes of deploy | `docker inspect xrugc-school-backend --format '{{.RestartCount}}'` |
| PHP fatal errors | Any `Fatal error` or `Uncaught Exception` in logs **not present before deploy** | `docker logs xrugc-school-backend --since '10m' \| grep -i "fatal\|uncaught"` |
| Data corruption | Any write operation (POST/PUT/DELETE) returns unexpected data or corrupts DB records | Manual spot-check of create/update/delete on schools, classes, groups |

**Time limit**: If any automatic trigger fires, rollback must begin within **5 minutes**.

---

## 2. Manual / Judgment-Based Triggers

These require a quick team assessment (≤ 15 minutes) before deciding.

| Trigger | Concern Level | Suggested Action |
|---------|--------------|-----------------|
| Response time increase > 50% vs baseline | Medium | Check metrics, profile middleware stack; rollback if no fix within 30 min |
| Response time increase > 200% vs baseline | High | Rollback unless root cause is immediately obvious and fixable |
| Specific endpoint returning wrong data (non-critical) | Medium | Fix forward if isolated; rollback if widespread |
| User complaints about login or data access | High | Verify with test account; rollback if confirmed |
| Frontend reports unexpected 401/403 on previously working flows | Medium | Check AuthMiddleware logs; rollback if not resolved in 20 min |
| Rate limiting triggering unexpectedly for normal usage | Low | Adjust config and redeploy; rollback only if config change is risky |
| Memory usage increase > 100MB vs baseline | Low | Monitor; rollback if trending upward with no plateau |

**Decision owner**: Backend team lead. If unavailable, any senior backend developer can decide.

---

## 3. Decision Matrix

| Situation | Who Decides | Threshold to Rollback | Time to Decide |
|-----------|-------------|----------------------|----------------|
| Automatic trigger fires | Anyone on the team | Automatic — no vote needed | Immediate |
| Error rate 5–10% | Backend lead | Rollback if no fix identified in 15 min | 15 min |
| Performance degradation > 50% | Backend lead | Rollback if no fix identified in 30 min | 30 min |
| Isolated non-critical bug | Backend lead + 1 dev | Fix forward unless risk is high | 30 min |
| User-facing data issue | Backend lead | Rollback immediately if data integrity at risk | 5 min |
| Ambiguous / unclear impact | Backend lead + team | Default to rollback when in doubt | 20 min |

**Default rule**: When in doubt, roll back. It is always safer to revert and re-deploy with a fix than to leave a degraded system running.

---

## 4. Do NOT Rollback — Fix Forward Instead

These situations may look alarming but do not warrant a rollback.

| Situation | Why Not Rollback | What to Do Instead |
|-----------|-----------------|-------------------|
| Single endpoint returns 404 for a route that was never migrated | Not a regression — route was never in PSR-15 | Add the missing route to `config/routes.php` and restart container |
| CORS error from a new frontend origin | Config issue, not a code regression | Update `CorsMiddleware` allowed origins and restart |
| Rate limit 429 during load test | Expected behaviour — rate limiter is working | Adjust thresholds in `RateLimitMiddleware` if needed |
| `X-Powered-By` header reappears after container rebuild | PHP SAPI issue, not PSR-15 regression | Re-apply `header_remove('X-Powered-By')` fix in `index.php` |
| Slow first request after container restart | JIT/opcode cache warm-up — normal | Wait 30–60 seconds; re-test |
| Test account login fails due to expired JWT | Token expiry, not a system issue | Re-login and retry |
| Redis connection warning in logs | Redis may have restarted; not a PSR-15 issue | Restart Redis container; verify `CacheHelper` reconnects |
| One unit test fails in CI after deploy | Test environment issue, not production regression | Fix the test; do not rollback production |

---

## 5. Post-Rollback Success Criteria

After running `bin/rollback.sh`, confirm all of the following before declaring the rollback complete.

### 5.1 Automated Checks (run immediately after script completes)

```bash
# Health check — must return 200
curl -s -o /dev/null -w "%{http_code}" http://localhost:8084/api/health
# Expected: 200

# Login — must return a JWT token
curl -s -X POST http://localhost:8084/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"guanfei","password":"123456"}' | python3 -m json.tool
# Expected: {"code":200,"data":{"token":"...",...}}
```

### 5.2 Core Endpoint Spot-Check

Using the token from login above (`TOKEN=<token>`):

```bash
# Schools list
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8084/api/schools
# Expected: 200 with data array

# Classes list
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8084/api/classes
# Expected: 200 with data array
```

### 5.3 Success Criteria Checklist

| Check | Pass Condition |
|-------|---------------|
| Health endpoint | HTTP 200, `"status":"healthy"` |
| Login endpoint | HTTP 200, JWT token in response |
| No 5xx errors in logs | Zero 5xx in `docker logs` since rollback | 
| Container stable | No restarts in 5 minutes post-rollback |
| Error rate back to baseline | < 1% 5xx over 5-minute window |
| Frontend can log in | Manual browser test passes |

### 5.4 Monitoring Window

After a successful rollback, monitor for **30 minutes** before declaring the incident resolved:

```bash
# Tail logs for errors
docker logs -f xrugc-school-backend 2>&1 | grep -i "error\|fatal\|exception"
```

If no new errors appear within 30 minutes, the rollback is confirmed stable. Proceed with scheduling a post-mortem per `docs/rollback-communication-plan.md`.
