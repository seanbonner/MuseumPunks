# MuseumPunks

A WordPress site for tracking CryptoPunks held by museums and cultural institutions.

## Project Structure

```
wp-content/
├── plugins/
│   └── sb-punks-registry/       # Core plugin for punk registry
│       ├── templates/           # Custom page templates
│       └── assets/              # CSS and JS
└── themes/
    ├── blocksy/                 # Base theme
    ├── minimalio/               # Alternative base theme
    └── minimalio-child/         # Active child theme
```

## Deployment

This project uses GitHub Actions for automatic deployment to Hostinger via FTP.

**Triggers:** Push to `main` branch (only when `wp-content/themes/` or `wp-content/plugins/` change)

**Required Secrets:**
- `FTP_SERVER` - Hostinger FTP server
- `FTP_USERNAME` - FTP username
- `FTP_PASSWORD` - FTP password

**Deploy Target:** `museumpunks.com`

## Custom Plugin

### SB Punks Registry (v0.7.0)

Registry plugin for tracking CryptoPunks in museum collections.

**Custom Post Type:**
- `sb_punk` - Individual punk entries with numeric permalinks (`/1234/`)

**Taxonomy:**
- `sb_institution` - Museums and cultural institutions
  - Custom fields: Website URL, Logo URL

**Meta Fields:**

| Field | Description |
|-------|-------------|
| `_sbpr_punk_id` | Punk number (0-9999), synced with title/slug |
| `_sbpr_acquisition_date` | Date acquired (YYYY-MM-DD) |
| `_sbpr_announcement_url` | Link to acquisition announcement |
| `_sbpr_museum_wallet` | Institution's wallet address |
| `_sbpr_acquisition_type` | donation / purchase |
| `_sbpr_donor_name` | Donor name (if donated) |
| `_sbpr_donor_url` | Donor URL (if donated) |
| `_sbpr_v1_wrapped` | V1 wrapper status |
| `_sbpr_v1_held` | V1 held by institution |
| `_sbpr_claimer_wallet` | Original claimer wallet |
| `_sbpr_claimer_name` | Claimer name (if known) |
| `_sbpr_claim_date` | Day claimed (1-30 June 2017) |
| `_sbpr_exhibitions` | JSON array of exhibition history |

**Shortcodes:**
- `[sb_punks_home]` - Homepage mosaic with logo header
- `[sb_punks_index]` - Grid index of all punks

**Custom Pages:**
- `/the-punks/` - Full punk index (sorted by acquisition date)
- `/institution/` - Institutions index
- `/institution/{slug}/` - Individual institution archive

**Settings:**
- About URL (logo link destination)
- Logo default/hover images

**Features:**
- Numeric permalinks (`/1234/` for punk #1234)
- Institution taxonomy with logo support
- Acquisition tracking (date, type, donor info)
- Exhibition history management
- V1 Wrapped Punk support (links to OpenSea)
- Original claimer tracking
- Comments/trackbacks disabled

## URL Structure

| Content | URL Pattern |
|---------|-------------|
| Individual Punk | `/{punk-number}/` |
| Punks Index | `/the-punks/` |
| Institutions | `/institution/` |
| Institution Archive | `/institution/{slug}/` |

## External Links

The plugin generates links to:
- CryptoPunks.app - Punk details and wallet profiles
- OpenSea - V1 Wrapped Punks

## Requirements

- WordPress 5.0+
- PHP 7.4+
- GD library (for image handling)

## License

Private/Proprietary

---

*Last updated: January 8, 2026*
