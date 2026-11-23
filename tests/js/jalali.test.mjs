// اجرا: node --test tests/js
// هسته‌ی تقویم شمسی (resources/js/jalali.js) را با ICU داخلی Node (تقویم persian) راستی‌آزمایی می‌کند.
import assert from 'node:assert/strict';
import test from 'node:test';

import {
    addMonths,
    formatJalali,
    isLeap,
    isoOfJalali,
    jalaliOfIso,
    monthGrid,
    monthLength,
    parseIso,
    toGregorian,
    toJalali,
    weekdayIndex,
} from '../../resources/js/jalali.js';

const icu = new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
    timeZone: 'UTC',
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
});

function icuJalali(date) {
    const parts = Object.fromEntries(icu.formatToParts(date).map((p) => [p.type, p.value]));

    return { jy: Number(parts.year), jm: Number(parts.month), jd: Number(parts.day) };
}

test('matches ICU for every day from 1990 to 2060', () => {
    const start = Date.UTC(1990, 0, 1);
    const end = Date.UTC(2060, 11, 31);
    let checked = 0;

    for (let t = start; t <= end; t += 86_400_000) {
        const d = new Date(t);
        const ours = toJalali(d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate());

        assert.deepEqual(ours, icuJalali(d), `mismatch on ${d.toISOString().slice(0, 10)}`);
        checked += 1;
    }

    assert.ok(checked > 25_000);
});

test('round-trips Jalali → Gregorian → Jalali for 1300..1500', () => {
    for (let jy = 1300; jy <= 1500; jy += 1) {
        for (let jm = 1; jm <= 12; jm += 1) {
            for (let jd = 1; jd <= monthLength(jy, jm); jd += 1) {
                const g = toGregorian(jy, jm, jd);

                assert.deepEqual(toJalali(g.gy, g.gm, g.gd), { jy, jm, jd }, `${jy}/${jm}/${jd}`);
            }
        }
    }
});

test('knows the anchors: Nowruz dates and leap years', () => {
    assert.equal(isoOfJalali(1405, 1, 1), '2026-03-21');
    assert.equal(isoOfJalali(1404, 1, 1), '2025-03-21');
    assert.equal(isoOfJalali(1403, 1, 1), '2024-03-20');

    assert.equal(isLeap(1403), true);
    assert.equal(isLeap(1399), true);
    assert.equal(isLeap(1404), false);
    assert.equal(isLeap(1405), false);

    assert.equal(monthLength(1403, 12), 30);
    assert.equal(monthLength(1404, 12), 29);
    assert.equal(monthLength(1405, 6), 31);
    assert.equal(monthLength(1405, 7), 30);
});

test('year lengths are 365 or 366 days and consecutive', () => {
    for (let jy = 1350; jy < 1480; jy += 1) {
        const a = new Date(`${isoOfJalali(jy, 1, 1)}T00:00:00Z`).getTime();
        const b = new Date(`${isoOfJalali(jy + 1, 1, 1)}T00:00:00Z`).getTime();
        const days = (b - a) / 86_400_000;

        assert.equal(days, isLeap(jy) ? 366 : 365, `year ${jy}`);
    }
});

test('weekday index: Saturday = 0 … Friday = 6', () => {
    assert.equal(weekdayIndex(2026, 3, 21), 0); // 1405/01/01 is a Saturday
    assert.equal(weekdayIndex(2026, 3, 27), 6); // Friday
});

test('month grid has 42 cells, starts on Saturday and flags month membership', () => {
    const grid = monthGrid(1405, 1);

    assert.equal(grid.length, 42);
    assert.equal(grid[0].weekday, 0);
    assert.equal(grid[0].iso, '2026-03-21');
    assert.equal(grid.filter((c) => c.inMonth).length, 31);
    assert.equal(grid.filter((c) => c.friday).length, 6);
    assert.equal(grid[0].holiday, 'نوروز');

    // every cell is the day after the previous one
    for (let i = 1; i < grid.length; i += 1) {
        const prev = new Date(`${grid[i - 1].iso}T00:00:00Z`).getTime();
        const cur = new Date(`${grid[i].iso}T00:00:00Z`).getTime();

        assert.equal(cur - prev, 86_400_000);
    }
});

test('month grid of every month in 1403..1406 starts on a Saturday cell and contains the whole month', () => {
    for (let jy = 1403; jy <= 1406; jy += 1) {
        for (let jm = 1; jm <= 12; jm += 1) {
            const grid = monthGrid(jy, jm);

            assert.equal(grid[0].weekday, 0);
            assert.equal(grid.filter((c) => c.inMonth).length, monthLength(jy, jm), `${jy}/${jm}`);
        }
    }
});

test('addMonths wraps years in both directions', () => {
    assert.deepEqual(addMonths(1405, 1, -1), { jy: 1404, jm: 12 });
    assert.deepEqual(addMonths(1405, 12, 1), { jy: 1406, jm: 1 });
    assert.deepEqual(addMonths(1405, 6, 14), { jy: 1406, jm: 8 });
    assert.deepEqual(addMonths(1405, 6, -18), { jy: 1403, jm: 12 });
});

test('parses and formats ISO dates', () => {
    assert.deepEqual(parseIso('2026-12-05'), { gy: 2026, gm: 12, gd: 5 });
    assert.equal(parseIso('2026-02-30'), null);
    assert.equal(parseIso('nope'), null);
    assert.equal(parseIso(''), null);

    assert.deepEqual(jalaliOfIso('2026-03-21'), { jy: 1405, jm: 1, jd: 1 });
    assert.equal(formatJalali('2026-03-21'), '۱ فروردین ۱۴۰۵');
    assert.equal(formatJalali('2026-03-21', true), 'شنبه ۱ فروردین ۱۴۰۵');
    assert.equal(formatJalali('garbage'), '');
});
