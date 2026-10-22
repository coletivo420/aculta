"""Unit tests for DeepSeek off-peak policy (no API calls)."""
import datetime as dt
import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("deepseek_offpeak", ROOT / "scripts/deepseek-offpeak.py")
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)


def utc(day, hour, minute=0):
    return dt.datetime.fromisoformat(day).replace(hour=hour, minute=minute, tzinfo=dt.timezone.utc)


class OffpeakTests(unittest.TestCase):
    def test_weekday_boundaries(self):
        monday = "2026-10-19"
        for hour, expected in [(0, True), (1, False), (3, False), (4, True), (5, True), (6, False), (9, False), (10, True), (23, True)]:
            with self.subTest(hour=hour):
                self.assertEqual(gate.offpeak(utc(monday, hour)), expected)

    def test_weekend(self):
        for day in ("2026-10-17", "2026-10-18"):
            for hour in (1, 6, 9):
                self.assertTrue(gate.offpeak(utc(day, hour)))

    def test_chinese_holiday_override(self):
        self.assertTrue(gate.offpeak(utc("2026-10-19", 2), {"2026-10-19"}))

    def test_timezone_conversion(self):
        brasilia = dt.timezone(dt.timedelta(hours=-3))
        local = dt.datetime(2026, 10, 18, 22, 0, tzinfo=brasilia)
        self.assertFalse(gate.offpeak(local))  # Monday 01:00 UTC


if __name__ == "__main__":
    unittest.main()
