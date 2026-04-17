# MuseumPunks

A registry of CryptoPunks held by museums and cultural institutions.

Live at [museumpunks.com](https://museumpunks.com).

## Stack

Static site built with [Eleventy v3](https://www.11ty.dev/). Content lives in `.md` files — one per punk, one per institution. Punk images are SVGs fetched from the on-chain [CryptoPunksData contract](https://etherscan.io/address/0x16F5A35647D6F03D5D3da7b35409D65ba03aF3B2) and committed to the repo. Institution logos are plain image files. Deploys via [Cloudflare Pages](https://pages.cloudflare.com/) on push to `main`.

The previous WordPress + Hostinger implementation is preserved on the `wp-legacy` branch for reference.

## Repo layout

```
punks/              One .md file per punk (filename = punk ID).
institutions/       One .md file per institution (filename = institution slug).
pages/              Top-level pages (homepage, /the-punks/, /institution/, /about/, /faq/).
images/
├── logos/          Site logos.
├── institutions/   Institution logos (one per slug).
└── punks/          On-chain SVGs (one per punk).
_includes/layouts/  Nunjucks templates (base, punk, institution).
_data/site.js       Site-wide config.
css/style.css       All styles.
js/mosaic.js        Homepage mosaic (runs in browser).
scripts/
├── fetch-punk-svgs.mjs    Pulls SVGs from the CryptoPunksData contract.
└── import-narratives.mjs  Re-pulls verbatim narratives from the live WP site via defuddle (historical).
eleventy.config.mjs
```

## Adding a new punk

1. Create `punks/{id}.md` with the frontmatter schema below.
2. Run `npm run fetch-svgs -- {id}` to pull the punk's SVG from chain.
3. Commit + push. Cloudflare Pages builds and deploys automatically.

### Punk frontmatter schema

```yaml
---
id: 74                                      # required; matches filename
institution: moma                           # required; must match an institutions/{slug}.md
acquisition_type: donation                  # donation | purchase
acquired: "2025-12-19"                      # optional; YYYY-MM-DD (quoted). Omit for imprecise dates.
announcement_url: "https://..."             # optional
museum_wallet: "0x..."                      # required for purchase; omit for donation
donor_name: "Name"                          # required for donation; omit for purchase
donor_url: "https://..."                    # optional
claimer_wallet: "0x..."                     # required
claimer_name: "Optional Name"               # optional
claim_date: 9                               # day of June 2017 (1–30)
v1_wrapped: false                           # bool
v1_held: false                              # bool — does the institution hold the V1?
burned: true                                # optional — set true if the punk was accidentally burned
                                            # and now lives at the CryptoPunks contract.
                                            # Changes the "Institution wallet" label to "Final location"
                                            # and adds a link to the BurnedPunks entry.
---
Narrative body in markdown. Inline links encouraged.
```

## Adding a new institution

1. Create `institutions/{slug}.md` with the frontmatter below.
2. Drop the institution's logo into `images/institutions/{slug}.{ext}`.
3. Reference the slug from any punk's `institution:` frontmatter field.

### Institution frontmatter schema

```yaml
---
slug: moma                                  # required; matches filename
name: "Museum of Modern Art (MoMA)"         # required
url: "https://moma.org"                     # required
logo: "/images/institutions/moma.jpg"       # required
---
Optional notes about the institution (rendered on /institution/{slug}/).
```

## URL structure

| Route | Source |
|---|---|
| `/` | `pages/index.njk` — mosaic homepage |
| `/{id}/` | `punks/{id}.md` |
| `/the-punks/` | `pages/the-punks.njk` — grid of all punks |
| `/institution/` | `pages/institutions.njk` — list of all institutions |
| `/institution/{slug}/` | `institutions/{slug}.md` — filtered punk grid for that institution |
| `/about/` | `pages/about.md` |
| `/faq/` | `pages/faq.md` |

## Editing content

Every page is a `.md` file. Edit, commit, push. Cloudflare Pages rebuilds on every push to `main`. Every branch and PR gets its own `*.pages.dev` preview URL.

## License

Content (prose, data, curation): [CC-BY 4.0](https://creativecommons.org/licenses/by/4.0/).

Site code: MIT-adjacent.
