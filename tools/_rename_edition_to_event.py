#!/usr/bin/env python3
"""One-shot hard rename Edition → PadelEvent / Event* across backend PHP (not migrations)."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

CLASS_MAP = [
    ("EditionRegistrationPaymentAttempt", "EventRegistrationPaymentAttempt"),
    ("EditionRegistrationPaymentRefund", "EventRegistrationPaymentRefund"),
    ("EditionRegistrationPaymentProof", "EventRegistrationPaymentProof"),
    ("EditionRegistrationCheckoutItem", "EventRegistrationCheckoutItem"),
    ("EditionRegistrationCheckout", "EventRegistrationCheckout"),
    ("EditionDivisionReplacement", "EventDivisionReplacement"),
    ("EditionReferralCodeInvitee", "EventReferralCodeInvitee"),
    ("EditionReferralAttribution", "EventReferralAttribution"),
    ("EditionDivisionStanding", "EventDivisionStanding"),
    ("EditionDivisionSchedule", "EventDivisionSchedule"),
    ("EditionParticipationMapping", "EventParticipationMapping"),
    ("EditionReferralSetting", "EventReferralSetting"),
    ("EditionDivisionPoster", "EventDivisionPoster"),
    ("EditionDivisionCourt", "EventDivisionCourt"),
    ("EditionLotteryLucky", "EventLotteryLucky"),
    ("EditionLotteryPrize", "EventLotteryPrize"),
    ("EditionLotteryRule", "EventLotteryRule"),
    ("EditionRegistration", "EventRegistration"),
    ("EditionReferralCode", "EventReferralCode"),
    ("EditionParticipation", "EventParticipation"),
    ("EditionDivision", "EventDivision"),
    ("EditionLottery", "EventLottery"),
    ("EditionCover", "EventCover"),
    ("EditionAppQuery", "EventAppQuery"),
    ("EditionBadge", "EventBadge"),
    ("EditionDeadline", "EventDeadline"),
    ("EditionDescription", "EventDescription"),
    ("EditionMerged", "EventMerged"),
    ("EditionFilter", "EventFilter"),
    ("PlayerEditionHistory", "PlayerEventHistory"),
    ("EditionDateRangeRequest", "EventDateRangeRequest"),
    ("EditionRegistrationCheckoutResource", "EventRegistrationCheckoutResource"),
    ("EditionDivisionScheduleResource", "EventDivisionScheduleResource"),
    ("EditionResource", "EventResource"),
    ("EditionController", "EventController"),
    ("EditionFactory", "PadelEventFactory"),
]

ID_MAP = [
    ("id_edition_division_schedule", "id_event_division_schedule"),
    ("id_edition_division_poster", "id_event_division_poster"),
    ("id_edition_division_court", "id_event_division_court"),
    ("id_edition_referral_setting", "id_event_referral_setting"),
    ("edition_division_court_id", "event_division_court_id"),
    ("id_edition_division", "id_event_division"),
    ("edition_division_id", "event_division_id"),
    ("id_benefit_edition", "id_benefit_event"),
    ("its_first_edition_id", "its_first_event_id"),
    ("show_edition_label", "show_event_label"),
    ("max_redemptions_per_edition", "max_redemptions_per_event"),
    ("active_player_for_edition", "active_player_for_event"),
    ("benefit_editions", "benefit_events"),
    ("edition_ids", "event_ids"),
    ("id_edition", "id_event"),
    ("edition_id", "event_id"),
    ("'editions'", "'events'"),
    ('"editions"', '"events"'),
    ("/editions", "/events"),
    ("editions/", "events/"),
    ("edition:", "event:"),
    ("mgr-list:edition", "mgr-list:event"),
    ("mgr:show:editions", "mgr:show:events"),
    ("edition_show", "event_show"),
    ("edition_scoped_index", "event_scoped_index"),
    ("app_editions", "app_events"),
    ('schema="Edition"', 'schema="Event"'),
    ("schema='Edition'", "schema='Event'"),
]

SKIP_PARTS = {
    "vendor",
    "node_modules",
    ".git",
    "storage",
    "bootstrap/cache",
    "tools",
}

EXTS = {".php", ".json", ".md", ".xml", ".yml", ".yaml", ".stub"}


def should_process(path: Path) -> bool:
    parts = set(path.parts)
    if parts & SKIP_PARTS:
        return False
    if "migrations" in path.parts and "database" in path.parts:
        return False
    if path.suffix not in EXTS:
        return False
    try:
        if path.is_symlink():
            return False
        return path.is_file()
    except OSError:
        return False


def transform(text: str) -> str:
    for old, new in CLASS_MAP:
        text = text.replace(old, new)
    text = re.sub(r"\bEdition\b", "PadelEvent", text)
    for old, new in ID_MAP:
        text = text.replace(old, new)
    # Repair over-renames from Edition → PadelEvent on compounds already mapped
    repairs = [
        ("PadelEventDivision", "EventDivision"),
        ("PadelEventRegistration", "EventRegistration"),
        ("PadelEventLottery", "EventLottery"),
        ("PadelEventParticipation", "EventParticipation"),
        ("PadelEventReferral", "EventReferral"),
        ("PadelEventResource", "EventResource"),
        ("PadelEventController", "EventController"),
        ("PadelEventCover", "EventCover"),
        ("PadelEventAppQuery", "EventAppQuery"),
        ("PadelEventBadge", "EventBadge"),
        ("PadelEventDeadline", "EventDeadline"),
        ("PadelEventDescription", "EventDescription"),
        ("PadelEventMerged", "EventMerged"),
        ("PadelEventFilter", "EventFilter"),
        ("PadelEventDateRange", "EventDateRange"),
        ("PadelEventFactory", "PadelEventFactory"),
    ]
    for old, new in repairs:
        text = text.replace(old, new)
    return text


def rename_file(path: Path) -> Path | None:
    name = path.name
    if name == "Edition.php" and path.parent.name == "Models":
        return path.with_name("PadelEvent.php")
    if name == "EditionFactory.php":
        return path.with_name("PadelEventFactory.php")
    if name == "EditionTest.php":
        return path.with_name("PadelEventTest.php")
    if "Edition" not in name:
        return None
    new_name = name.replace("Edition", "Event")
    # Root model already handled; avoid Event.php collision with nothing
    if new_name == name:
        return None
    return path.with_name(new_name)


def main() -> None:
    files: list[Path] = []
    for path in ROOT.rglob("*"):
        if should_process(path):
            files.append(path)

    updated = 0
    for path in files:
        orig = path.read_text(encoding="utf-8")
        text = transform(orig)
        if text != orig:
            path.write_text(text, encoding="utf-8")
            updated += 1
    print(f"updated content in {updated} files")

    renames: list[tuple[Path, Path]] = []
    for base in [ROOT / "app", ROOT / "tests", ROOT / "database" / "factories"]:
        if not base.exists():
            continue
        for path in base.rglob("*"):
            try:
                if not path.is_file() or path.is_symlink():
                    continue
            except OSError:
                continue
            target = rename_file(path)
            if target is not None and target != path:
                renames.append((path, target))

    # Longest paths first
    renames.sort(key=lambda pair: len(str(pair[0])), reverse=True)
    done = 0
    for src, dst in renames:
        if not src.exists():
            continue
        if dst.exists():
            print(f"SKIP exists: {dst}")
            continue
        dst.parent.mkdir(parents=True, exist_ok=True)
        src.rename(dst)
        print(f"{src.relative_to(ROOT)} -> {dst.relative_to(ROOT)}")
        done += 1
    print(f"renamed {done} files")


if __name__ == "__main__":
    main()
