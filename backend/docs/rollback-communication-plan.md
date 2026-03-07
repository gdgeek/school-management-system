# Rollback Communication Plan — PSR-15 Migration

**Version**: 1.0  
**Last Updated**: 2026-03-07  
**Applies To**: PSR-15 middleware migration rollback  
**Rollback Script**: `bin/rollback.sh`  
**Rollback Procedure**: `tests/Manual/TASK-30.3-ROLLBACK-TEST.md`

---

## 1. Stakeholders

| Role | Who | Responsibility |
|------|-----|----------------|
| Dev Team Lead | Backend lead | Decides to rollback, executes script, owns post-mortem |
| Backend Developers | All backend devs | Assist investigation, review logs |
| Frontend Team | Frontend lead + devs | Verify frontend still works after rollback |
| Ops / DevOps | Whoever manages Docker/infra | Monitor container health, assist if rollback itself fails |
| End Users | Students, teachers, admins | Informed only if downtime exceeds ~5 minutes |

---

## 2. Communication Channels

| Audience | Channel | Notes |
|----------|---------|-------|
| Dev + Ops team | Team chat (e.g. Slack/DingTalk `#backend-ops`) | Primary channel, real-time |
| Frontend team | Team chat `#frontend` or direct message | Ping frontend lead directly |
| End users | In-app status banner or email | Only if downtime is visible to users |
| Post-mortem record | GitHub issue or internal wiki | Written record after incident |

---

## 3. Message Templates

### 3.1 Rollback Initiated

> **[ROLLBACK INITIATED]** We are rolling back the PSR-15 middleware migration due to `<brief reason>`.
> Executing `bin/rollback.sh` now. Expected downtime: ~1–2 minutes for container restart.
> Frontend team: please hold any deployments until we confirm the API is stable.

### 3.2 Rollback In Progress

> **[ROLLBACK IN PROGRESS]** Container `xrugc-school-backend` is restarting.
> Polling health endpoint — will update in ~30 seconds.
> Do not deploy or merge to main until further notice.

### 3.3 Rollback Complete

> **[ROLLBACK COMPLETE]** API is healthy. Health check: `GET /api/health` → 200 OK.
> Reverted to commit `<short-hash>` on branch `rollback/psr15-<timestamp>`.
> Frontend team: API is stable, you can resume normal operations.
> We will schedule a post-mortem — details to follow.

### 3.4 Post-Mortem Scheduled

> **[POST-MORTEM]** Rollback incident post-mortem scheduled for `<date/time>`.
> Agenda: root cause, timeline, fix plan, prevention.
> Please review logs before the meeting: `docker logs xrugc-school-backend --since '<incident-start>'`
> GitHub issue / wiki page: `<link>`

---

## 4. Timeline Expectations

| Event | Notify Within |
|-------|--------------|
| Decision to rollback made | Immediately — post "Rollback Initiated" before running the script |
| Rollback script started | Within 1 minute of decision |
| Rollback complete / failed | Within 5 minutes of starting (script auto-verifies in ≤30s) |
| Frontend team unblocked | Immediately after "Rollback Complete" message |
| Post-mortem scheduled | Within 2 hours of incident resolution |
| Post-mortem held | Within 24–48 hours of incident |

---

## 5. Escalation Path if Rollback Fails

If `bin/rollback.sh` exits with an error or the health check does not return 200:

**Step 1 — Check container logs immediately**
```bash
docker logs xrugc-school-backend --tail 100
```
Post the last 20–30 lines in `#backend-ops`.

**Step 2 — Attempt manual container restart**
```bash
docker restart xrugc-school-backend
# Wait 15s, then:
curl -s http://localhost:8084/api/health
```

**Step 3 — Verify the file was actually restored**
```bash
git log --oneline -3
# Confirm rollback commit exists
head -5 school-management-system/backend/public/index.php
```

**Step 4 — If container won't start, rebuild it**
```bash
docker-compose -f docker/docker-compose.yml up -d --force-recreate school-backend
```

**Step 5 — Escalate to Ops**
If the above steps fail, escalate to the ops/infra owner with:
- The exact error from `rollback.sh` output
- Container logs from Step 1
- Current git status (`git log --oneline -5`, `git status`)

**Step 6 — Notify end users**
If downtime exceeds 5 minutes with no resolution in sight, post a user-facing message:
> **[SERVICE DISRUPTION]** We are experiencing a temporary issue with the school management system.
> Our team is working on a fix. Estimated resolution: `<time>`. We apologize for the inconvenience.

**Step 7 — Document everything**
Even if rollback fails, keep a running log of every action taken with timestamps. This is essential for the post-mortem.
