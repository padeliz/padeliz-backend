#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP = {"vendor", "node_modules", ".git", "storage", "tools", "public"}

# longest first
TABLES = [
    ("edition_registration_payment_attempts", "event_registration_payment_attempts"),
    ("edition_registration_payment_refunds", "event_registration_payment_refunds"),
    ("edition_registration_payment_proofs", "event_registration_payment_proofs"),
    ("edition_registration_checkout_items", "event_registration_checkout_items"),
    ("edition_registration_checkouts", "event_registration_checkouts"),
    ("edition_division_replacements", "event_division_replacements"),
    ("edition_referral_code_invitees", "event_referral_code_invitees"),
    ("edition_referral_attributions", "event_referral_attributions"),
    ("edition_division_standings", "event_division_standings"),
    ("edition_division_schedules", "event_division_schedules"),
    ("edition_referral_settings", "event_referral_settings"),
    ("edition_division_posters", "event_division_posters"),
    ("edition_division_courts", "event_division_courts"),
    ("edition_lottery_luckies", "event_lottery_luckies"),
    ("edition_lottery_prizes", "event_lottery_prizes"),
    ("edition_lottery_rules", "event_lottery_rules"),
    ("edition_participations", "event_participations"),
    ("edition_registrations", "event_registrations"),
    ("edition_referral_codes", "event_referral_codes"),
    ("edition_divisions", "event_divisions"),
    ("edition_lotteries", "event_lotteries"),
    ("benefit_editions", "benefit_events"),
]

REPLS = [
    *TABLES,
    ('schema="PadelEvent"', 'schema="Event"'),
    ("schema='PadelEvent'", "schema='Event'"),
    ("#/components/schemas/PadelEvent", "#/components/schemas/Event"),
    ("ref=\"#/components/schemas/PadelEvent\"", 'ref="#/components/schemas/Event"'),
    ("'edition_divisions'", "'event_divisions'"),
    ('"edition_divisions"', '"event_divisions"'),
    ("edition_divisions", "event_divisions"),  # keys / linked_data after table pass careful
    ("editionDivisions", "eventDivisions"),
    ("edition_divisions_count", "event_divisions_count"),
    ("editions_count", "events_count"),
    ("clearAttendanceForEdition", "clearAttendanceForEvent"),
    ("function editions(", "function events("),
    ("->editions(", "->events("),
    ("'editions'", "'events'"),  # relation load names may already be events
    ("property=\"edition\"", 'property="event"'),
    ("'edition_divisions' =>", "'event_divisions' =>"),
]


def main() -> None:
    updated = 0
    for path in ROOT.rglob("*.php"):
        try:
            if not path.is_file() or path.is_symlink():
                continue
        except OSError:
            continue
        if any(part in SKIP for part in path.parts):
            continue
        if "migrations" in path.parts:
            continue
        text = path.read_text(encoding="utf-8")
        orig = text
        for old, new in REPLS:
            text = text.replace(old, new)
        # Repair over-replace event_event_
        text = text.replace("event_event_", "event_")
        text = text.replace("EventEvent", "Event")
        if text != orig:
            path.write_text(text, encoding="utf-8")
            updated += 1
    print(f"updated {updated}")


if __name__ == "__main__":
    main()
