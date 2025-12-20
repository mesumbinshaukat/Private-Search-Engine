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

The API requires authentication for all endpoints except `/health`.

### 1. User Authentication (Laravel Sanctum)
For UI-based access or browser-based clients:
1. Authenticate via `POST /api/v1/login`.
2. Include the returned `access_token` in the `Authorization` header.

```http
Authorization: Bearer {access_token}
```

### 2. Service-to-Service (Master Key)
For trusted backend services or automated scripts:
2. **Master API Key**: Used for cross-service authentication. Accessible via `X-API-MASTER-KEY` header, `Authorization` Bearer token, or `api_master_key` query parameter.

```http
X-API-MASTER-KEY: your_master_key
```
OR
```http
Authorization: Bearer your_master_key
```
OR
```http
GET /api/v1/search?q=query&api_master_key=your_master_key
```

## Endpoints

### 1. Login

Authenticate a user and receive a Sanctum API token.

**Endpoint:** `POST /api/v1/login`

**Request Body:**
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

**Success Response:**
- **Code**: 200 OK
- **Schema**:
```json
{
  "status": "success",
  "data": {
    "access_token": "1|...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
    }
  }
}
```

### 2. Search [Auth Required]

Search across all categories or filter by specific category.

**Endpoint:** `GET /api/v1/search`

Performs a relevance-ranked search across indexed categories. Requires a valid Sanctum token or Master Key.

#### Query Parameters
| Parameter   | Type    | Required | Description |
| :---------- | :------ | :------- | :----------- |
| `q`         | string  | Yes      | Search query. Supports logical operators (`AND`, `OR`, `NOT`) and exact phrases (`""`). |
| `category`  | string  | No       | Filter by category (`technology`, `business`, etc.). Default: `all`. |
| `from_date` | date    | No       | Filter results published on or after (YYYY-MM-DD). |
| `to_date`   | date    | No       | Filter results published on or before (YYYY-MM-DD). |
| `sort`      | string  | No       | `relevance` (default), `date_desc`, `date_asc`. |
| `page`      | integer | No       | Page number for pagination. Default: `1`. |
| `per_page`  | integer | No       | Results per page. Default: `20`. |
| `api_master_key` | string | No | Optional. Master Key if not using Bearer token. |

#### Success Response
- **Code**: 200 OK
- **Schema**:
    - `results`: Array of objects including `relevance_score`, `confidence`, and `highlighted_description`.
    - `query_suggestions`: (Optional) Included in 404 responses.

```json
{
  "status": "success",
  "data": {
    "query": "artificial intelligence",
    "category": "all",
    "results": [
      {
        "title": "Article Title",
        "url": "https://example.com/article",
        "description": "Meta description or excerpt",
        "highlighted_description": "<mark>artificial</mark> <mark>intelligence</mark> is...",
        "published_at": "2025-12-15T10:30:00Z",
        "category": "ai",
        "indexed_at": "2025-12-17T05:00:00Z",
        "relevance_score": 1.0,
        "confidence": 0.85,
        "match_score": 10,
        "score_details": {
          "tf_idf_factor": 12.45,
          "stemmed_query": "artifici intellig",
          "is_boolean": false
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
    "timestamp": "2025-12-19T08:05:00Z",
    "cache_age_seconds": 3600,
    "index_date": "2025-12-18",
    "performance": {
      "time_ms": 16.5,
      "memory_mb": 0.48
    }
  }
}
```

#### Scoring Details (`match_score`) 1-10 Scale
The `match_score` is a weighted calculation (1-10) factoring in:
- **BM25 Relevance**: Logarithmically scaled raw score.
- **Exact Phrase Bonus**: +2 if the exact query appears in title or description.
- **Title Match Bonus**: +3 for title presence.
- **Description Match Bonus**: +1 for description presence.
- **Normalization**: `relevance_score` is normalized to a 0.01-1.0 range based on the top result.

#### Error Response (No Results)
- **Code**: 404 Not Found
```json
{
  "status": "error",
  "error": {
    "code": "NO_RESULTS",
    "message": "No results found for query",
    "query_suggestions": ["ai", "machine learning"]
  }
}
```

**Meta Field Notes:**
- `index_date`: The generation date of the most recent index represented in the results (YYYY-MM-DD).
- `cache_age_seconds`: Seconds since the local cache file was last modified.

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

### 3. Categories [Auth Required]

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

### 4. Random Topic Discovery [Auth Required] (`GET /api/v1/topic`)

Returns a random topic derived from current indexed records for discovery.

#### Success Response
- **Code**: 200 OK
- **Schema**:

```json
{
  "status": "success",
  "data": {
    "topic": "Concise Topic Name",
    "category": "ai",
    "original_title": "Full Article Title Here",
    "url": "https://example.com/article"
  },
  "meta": {
    "version": "v1",
    "timestamp": "2025-12-19T08:14:51+00:00"
  }
}
```

### 4. Health Check

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

### 6. Statistics [Auth Required]

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

**Discover Random Topic:**

```bash
curl "http://localhost:8000/api/v1/topic"
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
- Search endpoint: 15-50ms (cache hit/reuse)
- Initial Index Build: 2-5 seconds (one-time per data refresh)
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
- GraphQL endpoint
- Webhooks for index updates
- Bulk search API
- Export to CSV/JSON
- Search analytics
