# Data Sources

Technical documentation for each data feed integrated into the LPNW Property Alerts plugin.

## 1. Planning Portal (planning.data.gov.uk)

**Type:** REST API (JSON)
**Auth:** None required
**Rate limit:** Be respectful, add delays between requests
**Cost:** Free

### API Endpoint

```
GET https://www.planning.data.gov.uk/api/v1/entity.json
```

### Parameters

- `dataset` = `planning-application`
- `organisation-entity` = ONS authority code (e.g. `E08000003` for Manchester)
- `start-date-day-since` = date filter (YYYY-MM-DD)
- `limit` = max results per page

### Response Fields

- `entity` - unique ID
- `reference` - planning reference number
- `name` / `address` - property location
- `description` - application description
- `planning-application-type` - full, outline, householder, etc.
- `point.coordinates` - [longitude, latitude]

### NW Authority Codes

See `LPNW_Feed_Planning::NW_AUTHORITIES` in `feeds/class-lpnw-feed-planning.php` for the complete list of 34 NW local authority codes.

### Cron Schedule

Every 6 hours. Each authority is queried separately with a 250ms delay between requests.

---

## 2. EPC Open Data (opendatacommunities.org)

**Type:** REST API (JSON)
**Auth:** Basic auth with API key (free registration)
**Rate limit:** Reasonable use
**Cost:** Free
**Register:** https://epc.opendatacommunities.org/login

### API Endpoint

```
GET https://epc.opendatacommunities.org/api/v1/domestic/search
```

### Parameters

- `postcode` = postcode prefix (e.g. `M`, `L`, `PR`)
- `from-month` = YYYY-MM filter
- `size` = max results (up to 5000)

### Headers

```
Authorization: Basic {base64(api_key + ':')}
Accept: application/json
```

### Response Fields

- `lmk-key` - unique certificate ID
- `address` - full address
- `postcode` - postcode
- `current-energy-rating` - A to G
- `current-energy-efficiency` - numeric score
- `property-type` - house, flat, bungalow, etc.
- `total-floor-area` - square metres
- `transaction-type` - marketed sale, rental, etc.
- `certificate-hash` - used to build certificate URL

### Why It Matters

A new EPC is lodged when a property is sold, let, built, or renovated. Detecting new EPCs can signal property coming to market before it appears on Rightmove/Zoopla.

### Cron Schedule

Daily. Each NW postcode prefix is queried separately with 500ms delays.

---

## 3. HM Land Registry Price Paid Data

**Type:** CSV bulk download
**Auth:** None
**Cost:** Free
**Source:** https://www.gov.uk/government/statistical-data-sets/price-paid-data-downloads

### Download URL

```
http://prod.publicdata.landregistry.gov.uk/pp-monthly-update-new-version.csv
```

This is the monthly update file containing the latest transactions.

### CSV Columns (no header row)

| Column | Description |
|--------|-------------|
| 0 | Transaction unique ID (GUID) |
| 1 | Price |
| 2 | Date of transfer |
| 3 | Postcode |
| 4 | Property type: D (detached), S (semi), T (terraced), F (flat), O (other) |
| 5 | Old/new: Y (new build), N (existing) |
| 6 | Duration: F (freehold), L (leasehold) |
| 7 | PAON (primary address) |
| 8 | SAON (secondary address) |
| 9 | Street |
| 10 | Locality |
| 11 | Town/city |
| 12 | District |
| 13 | County |
| 14 | PPD category: A (standard), B (additional) |
| 15 | Record status: A (addition), C (change), D (deletion) |

### NW Filtering

Filter by postcode column (index 3) starting with NW prefixes: M, L, PR, BB, LA, BL, OL, SK, WA, WN, CW, CH, CA, FY.

### Cron Schedule

Daily check, but new data only appears monthly (around the 20th).

---

## 4. Auction House Scrapers

**Type:** HTML scraping
**Auth:** None (public pages)
**Cost:** Free

### Pugh Auctions (pugh-auctions.com)

Major NW-focused auction house based in Manchester.

- **URL:** `https://www.pugh-auctions.com/lots`
- **Method:** Parse HTML lot cards using DOMDocument/DOMXPath
- **Data extracted:** Address, guide price, lot number, detail page URL
- **Postcode extraction:** Regex from address text

### SDL Property Auctions (sdlauctions.co.uk)

National auctioneer with heavy NW coverage.

- **URL:** TBD (catalogue pages)
- **Status:** Planned for Phase 3

### Auction House North West (auctionhousenorthwest.co.uk)

Regional specialist.

- **URL:** TBD
- **Status:** Planned for Phase 3

### Allsop (allsop.co.uk)

National commercial/residential auctioneer.

- **URL:** TBD
- **Status:** Planned for Phase 3

### Scraping Notes

- Always set a descriptive User-Agent header
- Respect robots.txt
- Add delays between requests
- HTML structure may change; scrapers need maintenance when layouts update
- Consider reaching out to auction houses about data partnerships

### Cron Schedule

Daily check for new catalogue pages.

---

## Postcode Geocoding

All feeds use the **postcodes.io** API for geocoding:

```
GET https://api.postcodes.io/postcodes/{postcode}
POST https://api.postcodes.io/postcodes (bulk, up to 100)
```

Free, no auth required, rate limited to reasonable use. Results are cached as WordPress transients for 1 week.
