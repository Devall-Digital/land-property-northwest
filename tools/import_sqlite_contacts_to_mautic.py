#!/usr/bin/env python3
"""
Import all rows from email_automation SQLite contacts.db into Mautic.

Uses the same REST style as the WordPress plugin: Basic auth to
{MAUTIC_URL}api/contacts/new (one POST per contact; reliable across Mautic versions).

Environment (set in shell or put in repo .env — not committed):
  MAUTIC_URL              e.g. https://marketing.land-property-northwest.co.uk/
  MAUTIC_USER             Mautic API user
  MAUTIC_PASS

Optional:
  LPNW_SQLITE_CONTACTS    Full path to contacts.db
                          Default: D:\\Documents\\Code\\email_automation\\data\\contacts.db

Examples:
  python tools/import_sqlite_contacts_to_mautic.py --dry-run
  python tools/import_sqlite_contacts_to_mautic.py --limit 10
  python tools/import_sqlite_contacts_to_mautic.py --skip-legacy-risky --skip-if-bounces
  python tools/import_sqlite_contacts_to_mautic.py --csv-only --csv-out %TEMP%\\mautic-import.csv
  python tools/import_sqlite_contacts_to_mautic.py
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import re
import sqlite3
import ssl
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path


DEFAULT_SQLITE = Path(r"D:\Documents\Code\email_automation\data\contacts.db")

# Practical check for Mautic / SMTP (not full RFC).
_EMAIL_RE = re.compile(
    r"^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@"
    r"[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?"
    r"(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$"
)
_PLACEHOLDER_COMPANY = frozenset(
    {"", "n/a", "na", "-", ".", "none", "tbc", "tba", "unknown", "xxx"}
)
_CAMPAIGN_TAG = "lpnw-campaign-2026"
# Mautic default "company" field max length.
_MAX_COMPANY_LEN = 64
# Substrings in SQLite status / contact_stage to skip on --skip-legacy-risky (lowercase match).
_LEGACY_RISKY_STATUS_MARKERS = (
    "bounce",
    "bounced",
    "unsub",
    "spam",
    "invalid",
    "suppress",
    "complaint",
    "blacklist",
)


def load_env_file(path: Path) -> None:
    if not path.is_file():
        return
    for raw in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, val = line.partition("=")
        key, val = key.strip(), val.strip().strip('"').strip("'")
        if key and key not in os.environ:
            os.environ[key] = val


def split_name(full: str | None) -> tuple[str, str]:
    full = (full or "").strip()
    if not full:
        return "", ""
    parts = full.split(None, 1)
    if len(parts) == 1:
        return parts[0], ""
    return parts[0], parts[1]


def normalize_email(raw: str | None) -> str:
    return (raw or "").strip().lower()


def tidy_text(raw: str | None, max_len: int = 120) -> str:
    if not raw:
        return ""
    s = " ".join(str(raw).split())
    return s[:max_len].strip()


def is_valid_email(email: str) -> bool:
    if not email or len(email) > 254 or ".." in email:
        return False
    return bool(_EMAIL_RE.match(email))


def tidy_company(raw: str | None) -> str:
    c = tidy_text(raw, max_len=_MAX_COMPANY_LEN)
    if c.lower() in _PLACEHOLDER_COMPANY:
        return ""
    return c


def row_matches_legacy_risky(row: dict) -> bool:
    """True if SQLite status/stage suggests do-not-mail in the legacy DB."""
    blob = f"{row.get('status', '')} {row.get('contact_stage', '')}".lower()
    return any(m in blob for m in _LEGACY_RISKY_STATUS_MARKERS)


def tidy_rows(
    raw_rows: list[dict],
    *,
    skip_legacy_risky: bool = False,
    skip_if_bounces: bool = False,
) -> tuple[list[dict], dict]:
    """
    Normalise emails, names, companies; drop bad emails; dedupe by email (first wins).
    """
    stats: dict = {
        "raw": len(raw_rows),
        "dropped_invalid_email": 0,
        "dropped_duplicate_email": 0,
        "dropped_legacy_risky": 0,
        "dropped_bounces": 0,
    }
    seen: set[str] = set()
    out: list[dict] = []
    for row in raw_rows:
        em = normalize_email(row.get("email"))
        if not is_valid_email(em):
            stats["dropped_invalid_email"] += 1
            continue
        if em in seen:
            stats["dropped_duplicate_email"] += 1
            continue
        if skip_if_bounces:
            tb = row.get("total_bounces")
            try:
                tb_int = int(tb) if tb is not None and str(tb).strip() != "" else 0
            except (TypeError, ValueError):
                tb_int = 0
            if tb_int > 0:
                stats["dropped_bounces"] += 1
                continue
        stage_row = {
            "email": em,
            "name": tidy_text(row.get("name")),
            "company_name": tidy_company(row.get("company_name")),
            "status": tidy_text(row.get("status"), 40),
            "contact_stage": tidy_text(row.get("contact_stage"), 40),
        }
        if skip_legacy_risky and row_matches_legacy_risky(stage_row):
            stats["dropped_legacy_risky"] += 1
            continue
        seen.add(em)
        out.append(stage_row)
    stats["ready"] = len(out)
    return out, stats


def fetch_rows(db_path: Path) -> list[dict]:
    conn = sqlite3.connect(str(db_path))
    conn.row_factory = sqlite3.Row
    cur = conn.cursor()
    cur.execute(
        """
        SELECT email, name, company_name, status, contact_stage,
               total_sent, total_opens, total_bounces
        FROM contacts
        WHERE email IS NOT NULL AND TRIM(email) != ''
        ORDER BY id
        """
    )
    rows = [dict(r) for r in cur.fetchall()]
    conn.close()
    return rows


def build_payload(row: dict) -> dict:
    first, last = split_name(row.get("name"))
    payload: dict = {"email": row["email"].strip()}
    if first:
        payload["firstname"] = first[:64]
    if last:
        payload["lastname"] = last[:64]
    company = (row.get("company_name") or "").strip()[:_MAX_COMPANY_LEN]
    if company:
        payload["company"] = company
    # Tag so you can build a segment in Mautic (create tag automatically on many installs).
    payload["tags"] = [_CAMPAIGN_TAG]
    return payload


def api_post_contact(
    base_url: str,
    user: str,
    password: str,
    payload: dict,
    *,
    timeout_sec: int = 120,
    max_retries: int = 4,
) -> tuple[int, str]:
    url = base_url.rstrip("/") + "/api/contacts/new"
    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    token = base64.b64encode(f"{user}:{password}".encode()).decode()
    ctx = ssl.create_default_context()
    last_err = ""
    for attempt in range(max_retries):
        req = urllib.request.Request(url, data=body, method="POST")
        req.add_header("Content-Type", "application/json")
        req.add_header("Accept", "application/json")
        req.add_header("Authorization", f"Basic {token}")
        try:
            with urllib.request.urlopen(req, context=ctx, timeout=timeout_sec) as resp:
                text = resp.read().decode("utf-8", errors="replace")
                return resp.status, text
        except urllib.error.HTTPError as e:
            text = e.read().decode("utf-8", errors="replace")
            return e.code, text
        except urllib.error.URLError as e:
            last_err = str(e.reason)
        except TimeoutError:
            last_err = "timeout"
        except OSError as e:
            last_err = str(e)
        if attempt + 1 < max_retries:
            time.sleep(2.0 * (attempt + 1))
    return 0, last_err or "request failed"


def write_csv(rows: list[dict], out: Path) -> None:
    import csv

    out.parent.mkdir(parents=True, exist_ok=True)
    with out.open("w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(
            [
                "email",
                "firstname",
                "lastname",
                "company",
                "tags",
                "legacy_status",
                "legacy_stage",
            ]
        )
        for row in rows:
            first, last = split_name(row.get("name"))
            w.writerow(
                [
                    row["email"].strip(),
                    first,
                    last,
                    (row.get("company_name") or "").strip(),
                    _CAMPAIGN_TAG,
                    row.get("status") or "",
                    row.get("contact_stage") or "",
                ]
            )


def main() -> int:
    repo_root = Path(__file__).resolve().parents[1]
    load_env_file(repo_root / ".env")

    parser = argparse.ArgumentParser(description="SQLite contacts.db to Mautic")
    parser.add_argument(
        "--db",
        type=Path,
        default=Path(os.environ.get("LPNW_SQLITE_CONTACTS", str(DEFAULT_SQLITE))),
        help="Path to contacts.db",
    )
    parser.add_argument("--dry-run", action="store_true", help="Print counts only")
    parser.add_argument("--limit", type=int, default=0, help="Max contacts to send (0=all)")
    parser.add_argument("--csv-only", action="store_true", help="Write CSV only, no API")
    parser.add_argument(
        "--csv-out",
        type=Path,
        default=repo_root / "tools" / "mautic-campaign-ready.csv",
        help="Output path for --csv-only",
    )
    parser.add_argument("--sleep", type=float, default=0.05, help="Seconds between API calls")
    parser.add_argument(
        "--write-csv",
        action="store_true",
        help="Also write campaign CSV before API import (same path as --csv-out)",
    )
    parser.add_argument(
        "--offset",
        type=int,
        default=0,
        help="Skip first N contacts after tidy (resume a stalled import)",
    )
    parser.add_argument(
        "--skip-legacy-risky",
        action="store_true",
        help="Skip rows whose status/contact_stage suggest bounce/unsub/spam (SQLite only)",
    )
    parser.add_argument(
        "--skip-if-bounces",
        action="store_true",
        help="Skip rows with total_bounces > 0 from SQLite",
    )
    args = parser.parse_args()

    if not args.db.is_file():
        print(f"Database not found: {args.db}", file=sys.stderr)
        return 1

    raw = fetch_rows(args.db)
    rows, stats = tidy_rows(
        raw,
        skip_legacy_risky=args.skip_legacy_risky,
        skip_if_bounces=args.skip_if_bounces,
    )
    extra = ""
    if args.skip_legacy_risky or args.skip_if_bounces:
        extra = (
            f", legacy-risky skipped: {stats['dropped_legacy_risky']}"
            f", bounces skipped: {stats['dropped_bounces']}"
        )
    print(
        f"SQLite rows: {stats['raw']} -> Mautic-ready: {stats['ready']} "
        f"(bad email: {stats['dropped_invalid_email']}, dupes skipped: {stats['dropped_duplicate_email']}"
        f"{extra})",
        flush=True,
    )

    if args.dry_run:
        return 0

    if args.csv_only:
        write_csv(rows, args.csv_out)
        print(f"Wrote {args.csv_out} ({len(rows)} rows). Import in Mautic: Contacts, Import.", flush=True)
        return 0

    base = os.environ.get("MAUTIC_URL", "").strip()
    user = os.environ.get("MAUTIC_USER", "").strip()
    pwd = os.environ.get("MAUTIC_PASS", "").strip()
    if not base or not user or not pwd:
        print(
            "Set MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS (e.g. from .env) or use --csv-only.",
            file=sys.stderr,
        )
        return 1

    if args.write_csv and args.offset == 0:
        write_csv(rows, args.csv_out)
        print(f"Wrote {args.csv_out} ({len(rows)} rows).", flush=True)

    if args.offset:
        rows = rows[args.offset :]
        print(
            f"Using offset {args.offset}: {len(rows)} contacts remaining to send.",
            flush=True,
        )

    to_send = rows[: args.limit] if args.limit > 0 else rows
    ok, fail = 0, 0
    for i, row in enumerate(to_send, 1):
        payload = build_payload(row)
        code, text = api_post_contact(base, user, pwd, payload)
        if 200 <= code < 300:
            ok += 1
        else:
            fail += 1
            print(
                f"FAIL {row['email'][:50]} HTTP {code} {text[:300]}",
                file=sys.stderr,
                flush=True,
            )
        if i % 100 == 0:
            print(f"Progress {i}/{len(to_send)} ok={ok} fail={fail}", flush=True)
        if args.sleep > 0:
            time.sleep(args.sleep)

    print(f"Done. Sent {len(to_send)} contacts. OK={ok} FAIL={fail}", flush=True)
    if fail and ok == 0:
        print(
            "Tip: run with --csv-only and import the CSV in Mautic if API keeps failing.",
            file=sys.stderr,
        )
        return 1
    return 0 if fail == 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())
