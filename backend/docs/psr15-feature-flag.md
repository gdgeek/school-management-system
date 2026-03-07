# PSR-15 Feature Flag Configuration

## Overview

The PSR-15 middleware stack migration uses a dual-layer feature flag system to control whether requests are routed through the new PSR-15 middleware stack or the legacy switch-case routing.

## Configuration Layers

### 1. Environment Variable (Highest Priority)

**Variable**: `PSR15_ENABLED`

**Location**: `.env` file

**Values**:
- `true` - Enable PSR-15 middleware stack for migrated paths
- `false` - Disable PSR-15 completely, use legacy routing only
- Not set - Fall back to config file setting

**Example**:
```bash
# Enable PSR-15 stack
PSR15_ENABLED=true

# Disable PSR-15 stack
PSR15_ENABLED=false
```

**Use Cases**:
- Quick enable/disable without code changes
- Environment-specific control (staging vs production)
- Emergency rollback without redeploying code
- Testing PSR-15 stack in specific environments

### 2. Configuration File (Fallback)

**File**: `config/psr15-migration.php`

**Setting**: `'enabled' => true/false`

**Use Cases**:
- Default behavior when environment variable is not set
- Version-controlled feature flag state
- Documentation of migration progress

## Priority Order

The system checks flags in this order:

1. **Environment Variable** (`PSR15_ENABLED`) - If set, this value is used
2. **Config File** (`config/psr15-migration.php` → `'enabled'`) - Used if env var not set
3. **Default** - If neither is set, defaults to `false` (legacy routing)

## Path Matching

When PSR-15 is enabled (via either method), the system checks if the request path matches any migrated endpoint in `config/psr15-migration.php` → `'paths'` array.

**Matching Rules**:
- **Exact match**: `/api/health` matches only `/api/health`
- **Prefix match**: `/api/schools` matches `/api/schools`, `/api/schools/123`, `/api/schools/123/edit`, etc.

## Usage Examples

### Example 1: Enable PSR-15 in Development

```bash
# .env
PSR15_ENABLED=true
```

```php
// config/psr15-migration.php
return [
    'enabled' => false,  // Ignored because env var is set
    'paths' => [
        '/api/health',
        '/api/auth',
    ],
];
```

Result: PSR-15 enabled for `/api/health` and `/api/auth` paths.

### Example 2: Disable PSR-15 for Emergency Rollback

```bash
# .env
PSR15_ENABLED=false
```

Result: All requests use legacy routing, regardless of config file settings.

### Example 3: Use Config File Default

```bash
# .env
# PSR15_ENABLED not set
```

```php
// config/psr15-migration.php
return [
    'enabled' => true,
    'paths' => [
        '/api/health',
    ],
];
```

Result: PSR-15 enabled for `/api/health` path (using config file setting).

## Migration Workflow

### Phase 1: Foundation Setup
```bash
# .env
PSR15_ENABLED=false  # Keep disabled during setup
```

### Phase 2: Testing Individual Endpoints
```bash
# .env
PSR15_ENABLED=true  # Enable for testing
```

```php
// config/psr15-migration.php
'paths' => [
    '/api/health',  // Test with health check first
],
```

### Phase 3: Gradual Rollout
```bash
# .env
PSR15_ENABLED=true
```

```php
// config/psr15-migration.php
'paths' => [
    '/api/health',
    '/api/auth',
    '/api/schools',  // Add more as they're tested
],
```

### Phase 4: Full Migration
```bash
# .env
PSR15_ENABLED=true
```

```php
// config/psr15-migration.php
'paths' => [
    '/api/health',
    '/api/auth',
    '/api/schools',
    '/api/classes',
    '/api/groups',
    '/api/students',
    '/api/teachers',
],
```

### Phase 5: Legacy Removal
After all endpoints are migrated and tested, the feature flag system and legacy routing code can be removed entirely.

## Deployment Considerations

### Docker Environment

After changing `.env` file, restart the Docker container:

```bash
docker restart xrugc-school-backend
```

### Environment-Specific Configuration

**Development**:
```bash
PSR15_ENABLED=true  # Test new stack
```

**Staging**:
```bash
PSR15_ENABLED=true  # Validate before production
```

**Production**:
```bash
PSR15_ENABLED=false  # Keep disabled until fully tested
# Or
PSR15_ENABLED=true   # Enable after successful staging tests
```

## Troubleshooting

### Issue: PSR-15 not activating

**Check**:
1. Is `PSR15_ENABLED=true` in `.env`?
2. Is the path listed in `config/psr15-migration.php` → `'paths'`?
3. Did you restart the Docker container after changing `.env`?

### Issue: Need to rollback quickly

**Solution**:
```bash
# Set in .env
PSR15_ENABLED=false

# Restart container
docker restart xrugc-school-backend
```

### Issue: Different behavior in different environments

**Check**:
- Each environment has its own `.env` file
- Environment variable takes precedence over config file
- Verify `.env` settings in each environment

## Testing the Feature Flag

### Test 1: Verify Environment Variable Priority

```bash
# Set env var to false
PSR15_ENABLED=false

# Set config to true
# config/psr15-migration.php: 'enabled' => true

# Restart container
docker restart xrugc-school-backend

# Test: Should use legacy routing (env var wins)
curl http://localhost:8084/api/health
```

### Test 2: Verify Config File Fallback

```bash
# Remove env var from .env
# PSR15_ENABLED not set

# Set config to true
# config/psr15-migration.php: 'enabled' => true

# Restart container
docker restart xrugc-school-backend

# Test: Should use PSR-15 for migrated paths
curl http://localhost:8084/api/health
```

### Test 3: Verify Path Matching

```bash
# Enable PSR-15
PSR15_ENABLED=true

# Add only /api/health to paths
# config/psr15-migration.php: 'paths' => ['/api/health']

# Restart container
docker restart xrugc-school-backend

# Test: /api/health should use PSR-15
curl http://localhost:8084/api/health

# Test: /api/schools should use legacy
curl http://localhost:8084/api/schools
```

## Related Files

- `.env.example` - Template with PSR15_ENABLED variable
- `.env` - Actual environment configuration (not in git)
- `config/psr15-migration.php` - Migration configuration and path list
- `public/index.php` - `shouldUsePsr15Stack()` function implementation
- `docs/psr15-middleware-migration.md` - Overall migration documentation

## References

- [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15/)
- [PSR-7: HTTP Message Interface](https://www.php-fig.org/psr/psr-7/)
- [Feature Flags Best Practices](https://martinfowler.com/articles/feature-toggles.html)
