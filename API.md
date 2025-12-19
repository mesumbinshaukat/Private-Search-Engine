# API.md

## API Documentation

This document defines the versioned REST API for the private search engine system.

## Versioning Strategy

### Version Format

API versions follow semantic versioning: `v{major}`.

Current version: **v1**

### Version in URL Path

All API endpoints include the version in the URL path:

```
/api/v1/{resource}
```

### Version Deprecation Policy

- Major version changes indicate breaking changes
- Previous major version supported for 6 months after new version release
- Deprecation warnings included in response headers: `X-API-Deprecation: true`
- Sunset date included in response headers: `X-API-Sunset: 2025-06-17`

### Breaking Changes

Breaking changes that trigger major version increment:
- Removing endpoints
- Removing request/response fields
- Changing field data types
- Changing authentication requirements
- Changing error response structure

### Non Breaking Changes

Non breaking changes that do not trigger version increment:
- Adding new endpoints
- Adding optional request parameters
- Adding response fields
- Adding new error codes
- Performance improvements

## Base URL

**Local Development:**
```
http://localhost:8000/api/v1
```

**Production:**
```
https://your-domain.com/api/v1
```

## Authentication

### Current Implementation (Local Development)

No authentication required. All endpoints are publicly accessible.

### Future Implementation (Production)

API key authentication via header:

```
Authorization: Bearer {api_key}
```

## Endpoints

### 1. Search

Search across all categories or filter by specific category.

**Endpoint:** `GET /api/v1/search`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `q` | string | Yes | - | Search query string |
| `category` | string | No | all | Category filter: `technology`, `business`, `ai`, `sports`, `politics`, or `all` |
| `page` | integer | No | 1 | Page number for pagination (1-indexed) |
| `per_page` | integer | No | 20 | Results per page (max 100) |

**Request Example:**

```bash
GET /api/v1/search?q=artificial+intelligence&category=ai&page=1&per_page=20
```

**Response Schema:**

```json
{
  "status": "success",
  "data": {
    "query": "artificial intelligence",
    "category": "ai",
    "results": [
      {
        "title": "Article Title",
        "url": "https://example.com/article",
        "description": "Meta description or excerpt",
        "published_at": "2025-12-15T10:30:00Z",
        "category": "ai",
        "indexed_at": "2025-12-17T05:00:00Z",
        "match_score": 8,
        "score_details": {
          "title_matches": 1,
          "description_matches": 2,
          "phrase_match": "title"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total_results": 150,
      "total_pages": 8,
      "has_next": true,
      "has_previous": false
    }
  },
    "meta": {
    "version": "v1",
    "timestamp": "2025-12-17T21:33:24+05:00",
    "cache_age_seconds": 3600,
    "index_date": "2025-12-17"
  }
}

**Meta Field Notes:**
- `index_date`: The generation date of the most recent index represented in the results (YYYY-MM-DD).
- `cache_age_seconds`: Seconds since the local cache file was last modified.
```

**Response Codes:**

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request (invalid parameters) |
| 404 | No results found |
| 429 | Rate limit exceeded |
| 500 | Internal server error |
| 503 | Service unavailable (cache not ready) |

**Error Response Schema:**

```json
{
  "status": "error",
  "error": {
    "code": "INVALID_CATEGORY",
    "message": "Category must be one of: technology, business, ai, sports, politics, all",
    "details": {
      "parameter": "category",
      "provided_value": "invalid"
    }
  },
  "meta": {
    "version": "v1",
    "timestamp": "2025-12-17T21:33:24+05:00"
  }
}
```

### 2. Categories

List all available categories with metadata.

**Endpoint:** `GET /api/v1/categories`

**Query Parameters:** None

**Request Example:**

```bash
GET /api/v1/categories
```

**Response Schema:**

```json
{
  "status": "success",
  "data": {
    "categories": [
      {
        "id": "technology",
        "name": "Technology",
        "description": "Software, hardware, programming, tech industry news",
        "record_count": 1250,
        "last_updated": "2025-12-17T05:00:00Z"
      },
      {
        "id": "business",
        "name": "Business",
        "description": "Finance, markets, entrepreneurship, corporate news",
        "record_count": 1180,
        "last_updated": "2025-12-17T05:00:00Z"
      },
      {
        "id": "ai",
        "name": "AI",
        "description": "Artificial intelligence, machine learning, AI research and applications",
        "record_count": 1320,
        "last_updated": "2025-12-17T05:00:00Z"
      },
      {
        "id": "sports",
        "name": "Sports",
        "description": "All sports news, events, and analysis",
        "record_count": 1095,
        "last_updated": "2025-12-17T05:00:00Z"
      },
      {
        "id": "politics",
        "name": "Politics",
        "description": "Political news, policy, elections, government",
        "record_count": 1210,
        "last_updated": "2025-12-17T05:00:00Z"
      }
    ],
    "total_categories": 5,
    "total_records": 6055
  },
  "meta": {
    "version": "v1",
    "timestamp": "2025-12-17T21:33:24+05:00",
    "cache_age_seconds": 3600
  }
}
```

**Response Codes:**

| Code | Description |
|------|-------------|
| 200 | Success |
| 429 | Rate limit exceeded |
| 500 | Internal server error |
| 503 | Service unavailable (cache not ready) |

### 3. Health Check

Check API and system health status.

**Endpoint:** `GET /api/v1/health`

**Query Parameters:** None

**Request Example:**

```bash
GET /api/v1/health
```

**Response Schema:**

```json
{
  "status": "healthy",
  "data": {
    "api": "operational",
    "cache": "operational",
    "database": "operational",
    "google_drive": "operational",
    "queue": "operational"
  },
  "meta": {
    "version": "v1",
    "timestamp": "2025-12-17T21:33:24+05:00",
    "uptime_seconds": 86400
  }
}
```

**Degraded Response:**

```json
{
  "status": "degraded",
  "data": {
    "api": "operational",
    "cache": "stale",
    "database": "operational",
    "google_drive": "unreachable",
    "queue": "operational"
  },
  "warnings": [
    "Cache is stale (last updated 8 hours ago)",
    "Google Drive unreachable (using local cache)"
  ],
  "meta": {
    "version": "v1",
    "timestamp": "2025-12-17T21:33:24+05:00",
    "uptime_seconds": 86400
  }
}
```

**Response Codes:**

| Code | Description |
|------|-------------|
| 200 | Healthy or degraded |
| 503 | Unhealthy (critical services down) |

### 4. Statistics

Get system statistics and metrics.

**Endpoint:** `GET /api/v1/stats`

**Query Parameters:** None

**Request Example:**

```bash
GET /api/v1/stats
```

**Response Schema:**

```json
{
  "status": "success",
  "data": {
    "index": {
      "total_records": 6055,
      "last_generated": "2025-12-17T04:00:00Z",
      "oldest_record": "2025-12-13T10:00:00Z",
      "newest_record": "2025-12-17T02:00:00Z"
    },
    "crawler": {
      "last_run": "2025-12-17T00:00:00Z",
      "pages_crawled": 7500,
      "pages_failed": 320,
      "success_rate": 95.73
    },
    "api": {
      "requests_today": 1250,
      "average_response_time_ms": 45,
      "cache_hit_rate": 98.5
    }
  },
  "meta": {
    "version": "v1",
    "timestamp": "2025-12-17T21:33:24+05:00"
  }
}
```

**Response Codes:**

| Code | Description |
|------|-------------|
| 200 | Success |
| 429 | Rate limit exceeded |
| 500 | Internal server error |

## Error Standards

### Error Response Structure

All error responses follow this structure:

```json
{
  "status": "error",
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable error message",
    "details": {
      "additional": "context"
    }
  },
  "meta": {
    "version": "v1",
    "timestamp": "2025-12-17T21:33:24+05:00"
  }
}
```

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INVALID_QUERY` | 400 | Search query is empty or invalid |
| `INVALID_CATEGORY` | 400 | Category parameter is not valid |
| `INVALID_PAGE` | 400 | Page number is invalid (must be >= 1) |
| `INVALID_PER_PAGE` | 400 | Per page value is invalid (must be 1-100) |
| `NO_RESULTS` | 404 | No results found for query |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |
| `CACHE_NOT_READY` | 503 | Cache is not initialized |
| `CACHE_STALE` | 503 | Cache data is too old |
| `INTERNAL_ERROR` | 500 | Unexpected server error |

### Error Details

Error responses may include additional context in the `details` object:

```json
{
  "error": {
    "code": "INVALID_CATEGORY",
    "message": "Category must be one of: technology, business, ai, sports, politics, all",
    "details": {
      "parameter": "category",
      "provided_value": "invalid",
      "valid_values": ["technology", "business", "ai", "sports", "politics", "all"]
    }
  }
}
```

## Rate Limits

### Current Limits

**Per IP Address:**
- 60 requests per minute
- 1000 requests per hour
- 10000 requests per day

### Rate Limit Headers

All responses include rate limit headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1702850000
```

### Rate Limit Exceeded Response

```json
{
  "status": "error",
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Please try again later.",
    "details": {
      "limit": 60,
      "window": "1 minute",
      "retry_after_seconds": 30
    }
  },
  "meta": {
    "version": "v1",
    "timestamp": "2025-12-17T21:33:24+05:00"
  }
}
```

**Response Headers:**

```
Retry-After: 30
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1702850030
```

## Caching Behavior

### Cache Strategy

The API serves results from a local cache that is refreshed daily from Google Drive.

### Cache Headers

All successful responses include cache metadata headers:

```
X-Cache-Status: HIT
X-Cache-Age: 3600
X-Index-Date: 2025-12-17
```

**Cache Status Values:**
- `HIT` - Response served from cache
- `MISS` - Response generated dynamically (should be rare)
- `STALE` - Cache is older than expected but still serving

### Cache Invalidation

Cache is automatically invalidated and refreshed:
- Daily at 05:00 UTC after new index upload
- Manually via admin command: `php artisan cache:refresh`

### Stale Cache Handling

If cache is older than 24 hours:
- API continues serving from stale cache
- Response includes warning header: `X-Cache-Warning: stale`
- Health endpoint reports degraded status

If cache is older than 48 hours:
- API returns 503 Service Unavailable
- Error response indicates cache staleness

## Security Considerations

### Input Validation

All input parameters are validated:
- Query strings sanitized to prevent injection
- Category values whitelisted
- Numeric parameters validated for range and type
- Maximum query length: 500 characters

### Output Encoding

All output is JSON encoded with proper escaping to prevent XSS.

### CORS

CORS is disabled by default for local development.

Production deployment should configure CORS appropriately:

```php
'allowed_origins' => ['https://your-frontend-domain.com'],
'allowed_methods' => ['GET'],
'allowed_headers' => ['Content-Type', 'Authorization'],
```

### HTTPS

Local development uses HTTP.

Production deployment must use HTTPS for all API endpoints.

### SQL Injection Prevention

All database queries use parameter binding. No raw SQL with user input.

### Rate Limiting

Rate limiting prevents abuse and DoS attacks.

### Logging

All API requests are logged with:
- Timestamp
- IP address
- Endpoint
- Query parameters
- Response status
- Response time

Logs are rotated daily and retained for 14 days.

### Error Message Sanitization

Error messages do not expose:
- Internal file paths
- Database structure
- Stack traces (in production)
- Sensitive configuration

## Request Examples

### cURL Examples

**Basic Search:**

```bash
curl "http://localhost:8000/api/v1/search?q=machine+learning"
```

**Category Filtered Search:**

```bash
curl "http://localhost:8000/api/v1/search?q=stock+market&category=business"
```

**Paginated Search:**

```bash
curl "http://localhost:8000/api/v1/search?q=football&category=sports&page=2&per_page=50"
```

**Get Categories:**

```bash
curl "http://localhost:8000/api/v1/categories"
```

**Health Check:**

```bash
curl "http://localhost:8000/api/v1/health"
```

**Statistics:**

```bash
curl "http://localhost:8000/api/v1/stats"
```

### JavaScript Fetch Examples

**Basic Search:**

```javascript
fetch('http://localhost:8000/api/v1/search?q=artificial+intelligence&category=ai')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));
```

**With Error Handling:**

```javascript
async function search(query, category = 'all', page = 1) {
  try {
    const params = new URLSearchParams({
      q: query,
      category: category,
      page: page,
      per_page: 20
    });
    
    const response = await fetch(`http://localhost:8000/api/v1/search?${params}`);
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error.message);
    }
    
    const data = await response.json();
    return data.data;
  } catch (error) {
    console.error('Search failed:', error);
    throw error;
  }
}
```

## Response Time SLA

**Target Response Times (p95):**

- Search endpoint: < 200ms
- Categories endpoint: < 50ms
- Health endpoint: < 10ms
- Statistics endpoint: < 100ms

**Actual Performance (Local Development):**

Response times depend on hardware and cache status. Typical values:
- Search endpoint: 20-80ms (cache hit)
- Categories endpoint: 5-15ms
- Health endpoint: 2-5ms
- Statistics endpoint: 10-30ms

## Changelog

### v1.0.0 (2025-12-17)

**Initial Release:**
- Search endpoint with pagination and category filtering
- Categories endpoint with metadata
- Health check endpoint
- Statistics endpoint
- Rate limiting (60 req/min)
- Error standardization
- Cache headers

## Future Enhancements

Planned features for future versions:

**v2.0.0:**
- API key authentication
- Advanced search operators (AND, OR, NOT, phrase search)
- Fuzzy matching and typo tolerance
- Relevance ranking
- Search suggestions
- Highlighted snippets
- Date range filtering
- Sorting options (relevance, date, title)

**v3.0.0:**
- GraphQL endpoint
- Webhooks for index updates
- Bulk search API
- Export to CSV/JSON
- Search analytics
