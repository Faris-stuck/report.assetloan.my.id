# API Domain README Template

This is a domain-specific template for `docs/api/README.md`. Copy and customize for the API domain.

---

# API Documentation

## Purpose

This folder documents all API specifications, endpoint definitions, request/response schemas, authentication requirements per endpoint, rate limiting policies, versioning strategy, and error handling standards. API documentation serves as the technical contract between frontend/mobile clients and backend services, ensuring consistent and predictable API behavior.

## Quick Navigation

| Document | Purpose | For Whom |
|----------|---------|----------|
| [endpoints.md](./endpoints.md) | Complete list of available endpoints with request/response schemas | Frontend/Mobile developers, API consumers |
| [authentication-requirements.md](./authentication-requirements.md) | Authentication mechanisms (session/JWT/OAuth) required per endpoint | API implementers, security reviewers |
| [rate-limiting.md](./rate-limiting.md) | Rate limit policies, quotas, and throttling behavior | Frontend/Backend developers |
| [error-codes.md](./error-codes.md) | Standard error codes and their meanings | All developers |
| [api-versioning.md](./api-versioning.md) | API versioning strategy and deprecation policy | API maintainers |
| [webhooks.md](./webhooks.md) | Webhook setup and event documentation | Integration engineers |

## Folder Organization Rules

### When to Add New Files

Add new file when:
- New endpoint category requires independent documentation (e.g., `webhooks.md`, `batch-api.md`)
- API feature (versioning, caching, rate limiting) needs standalone document
- Expected size exceeds 300 lines in existing document

Extend existing file when:
- New endpoint belongs to existing category (add to `endpoints.md`)
- New error type related to existing errors
- New auth requirement builds on existing mechanisms

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`  
**Examples**:
- `endpoints.md` (main endpoint reference)
- `endpoints-pagination.md` (variant for pagination spec)
- `authentication-requirements.md`
- `rate-limiting.md`
- `error-codes.md`
- `error-codes-deprecated.md` (variant for legacy errors)
- `api-versioning.md`
- `webhooks.md`

### Folder Growth

**Current files**: [X] direct markdown files  
**Max threshold**: 8 files  
**Action if exceeded**: Create `endpoints/` subfolder for endpoint specifications

**Example future structure** (if 8+ endpoint docs):
```
docs/api/
├── README.md
├── authentication-requirements.md
├── rate-limiting.md
├── error-codes.md
├── endpoints/
│   ├── README.md
│   ├── users-api.md
│   ├── reports-api.md
│   ├── attachments-api.md
│   └── [more endpoint categories...]
└── webhooks.md
```

## Related Domains

- **[auth/](../auth/)** - Defines authentication mechanisms referenced in API requirements
- **[database/](../database/)** - Provides data schema context for request/response structures
- **[testing/](../testing/)** - Includes API testing procedures and test results
- **[deployment/](../deployment/)** - Specifies API deployment and versioning in production
- **[business/](../business/)** - Defines business rules that constrain API behavior

## Getting Started

### For Frontend/Mobile Developers

1. **Start here**: [Read api-versioning.md](./api-versioning.md) to understand API stability
2. **Learn endpoints**: [Review endpoints.md](./endpoints.md) for all available endpoints
3. **Handle errors**: [Check error-codes.md](./error-codes.md) for standardized error handling
4. **Understand authentication**: [Read authentication-requirements.md](./authentication-requirements.md)
5. **Plan requests**: [Review rate-limiting.md](./rate-limiting.md) for quota planning

### For API Implementers

1. **Understand requirements**: [Review endpoints.md](./endpoints.md) for endpoint specs
2. **Security**: [Read authentication-requirements.md](./authentication-requirements.md)
3. **Error handling**: [Follow error-codes.md](./error-codes.md) standards
4. **Performance**: [Check rate-limiting.md](./rate-limiting.md) requirements
5. **Versioning**: [Follow api-versioning.md](./api-versioning.md) deprecation policy

### For QA/Testing

1. **Test cases**: [Review endpoints.md](./endpoints.md#test-cases) for test scenarios
2. **Error scenarios**: [Use error-codes.md](./error-codes.md) for negative test cases
3. **Rate limiting**: [Check rate-limiting.md](./rate-limiting.md) for load testing
4. **Security**: [Test authentication](./authentication-requirements.md#test-cases)

## Search Tips

| Question | Answer |
|----------|--------|
| How do I call [endpoint name]? | [endpoints.md](./endpoints.md) |
| What auth does [endpoint] need? | [authentication-requirements.md](./authentication-requirements.md) |
| What's the request format? | [endpoints.md](./endpoints.md) (request schema section) |
| What errors can occur? | [error-codes.md](./error-codes.md) |
| What's my rate limit? | [rate-limiting.md](./rate-limiting.md) |
| Is this endpoint deprecated? | [api-versioning.md](./api-versioning.md) or [endpoints.md](./endpoints.md) |
| How do webhooks work? | [webhooks.md](./webhooks.md) |
| Can I batch requests? | [endpoints.md](./endpoints.md#batch-endpoints) |

### Common Scenarios → Documents

- **"I'm starting new feature that calls API"** → Start with [api-versioning.md](./api-versioning.md), then [endpoints.md](./endpoints.md)
- **"I got error 429"** → Check [error-codes.md](./error-codes.md), then [rate-limiting.md](./rate-limiting.md)
- **"I got error 401"** → Check [error-codes.md](./error-codes.md), then [authentication-requirements.md](./authentication-requirements.md)
- **"I want to set up webhook"** → Read [webhooks.md](./webhooks.md)
- **"How do I handle pagination?"** → See [endpoints.md](./endpoints.md#pagination)

## See Also

- [Root Documentation Hub](../README.md) - Overview of all domains
- [../auth/authentication.md](../auth/authentication.md) - Detailed authentication mechanisms
- [../database/schema-overview.md](../database/schema-overview.md) - Database schema for API context
- [../testing/testing-framework.md](../testing/testing-framework.md) - API testing procedures

---

## Endpoint at a Glance

**Total Endpoints**: [X]  
**Latest API Version**: [X.X.X]  
**Authentication**: [Session|JWT|OAuth|Multiple]  
**Base URL**: `https://api.example.com`  
**Rate Limit**: [requests per minute]  
**Supported Formats**: [JSON|XML|both]  

---

*API documentation is maintained per AGENTS.md. See design.md for domain structure.*
