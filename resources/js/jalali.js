/**
 * تقویم شمسی (جلالی) — بدون هیچ وابستگی.
 *
 *  - هسته‌ی تبدیل تاریخ بر اساس الگوریتم استاندارد «jalaali» (چرخه‌ی ۳۳ ساله با جدول breaks؛
 *    همان الگوریتم کتابخانه‌ی jalaali-js با مجوز MIT) نوشته شده و با ICU (`Intl` تقویم persian)
 *    روی ۷۰ سال راستی‌آزمایی می‌شود: `node --test tests/js`.
 *  - تاریخ‌ها همیشه به‌صورت رشته‌ی میلادی ISO (YYYY-MM-DD) رد و بدل می‌شوند؛ نمایش شمسی فقط
 *    در مرورگر است، پس سرور هیچ تبدیل شمسی برای ورودی انجام نمی‌دهد.
 *  - کامپوننت‌های Alpine:
 *      jalaliCalendar  نمای ماهانه‌ی تقویم وظایف (data-events روی ریشه: JSON)
 *      jalaliPicker    انتخاب‌گر تاریخ شمسی (با x-modelable و wire:model)
 */

const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

export const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

/** هفته از شنبه شروع می‌شود (اندیس ۰ = شنبه … ۶ = جمعه). */
export const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

export const WEEKDAYS_SHORT = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

/**
 * تعطیلات رسمی با تاریخ ثابت شمسی. تعطیلات قمری (عید فطر، تاسوعا، عاشورا، …) هر سال جابه‌جا
 * می‌شوند و به‌صورت رصدی اعلام می‌شوند؛ عمداً حدس زده نمی‌شوند.
 */
export const FIXED_HOLIDAYS = {
    '1/1': 'نوروز',
    '1/2': 'نوروز',
    '1/3': 'نوروز',
    '1/4': 'نوروز',
    '1/12': 'روز جمهوری اسلامی',
    '1/13': 'روز طبیعت',
    '3/14': 'رحلت امام خمینی',
    '3/15': 'قیام ۱۵ خرداد',
    '11/22': 'پیروزی انقلاب اسلامی',
    '12/29': 'ملی شدن صنعت نفت',
};

export const fa = (value) => String(value).replace(/\d/g, (d) => FA_DIGITS[d]);

const pad = (n) => String(n).padStart(2, '0');
const div = (a, b) => Math.trunc(a / b);
const mod = (a, b) => a - Math.trunc(a / b) * b;

// ------------------------------------------------------------------ هسته‌ی تبدیل

const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

function g2d(gy, gm, gd) {
    let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;

    return d;
}

function d2g(jdn) {
    let j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    const i = div(mod(j, 1461), 4) * 5 + 308;
    const gd = div(mod(i, 153), 5) + 1;
    const gm = mod(div(i, 153), 12) + 1;
    const gy = div(j, 1461) - 100100 + div(8 - gm, 6);

    return { gy, gm, gd };
}

/** جزئیات سال شمسی: leap (۰ = کبیسه)، سال میلادی متناظر و روز نوروز در ماه مارس. */
function jalCal(jy) {
    const bl = BREAKS.length;
    const gy = jy + 621;
    let leapJ = -14;
    let jp = BREAKS[0];
    let jump = 0;

    if (jy < jp || jy >= BREAKS[bl - 1]) {
        throw new RangeError(`Invalid Jalali year ${jy}`);
    }

    for (let i = 1; i < bl; i += 1) {
        const jm = BREAKS[i];
        jump = jm - jp;

        if (jy < jm) {
            break;
        }

        leapJ += div(jump, 33) * 8 + div(mod(jump, 33), 4);
        jp = jm;
    }

    let n = jy - jp;
    leapJ += div(n, 33) * 8 + div(mod(n, 33) + 3, 4);

    if (mod(jump, 33) === 4 && jump - n === 4) {
        leapJ += 1;
    }

    const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    const march = 20 + leapJ - leapG;

    if (jump - n < 6) {
        n = n - jump + div(jump + 4, 33) * 33;
    }

    let leap = mod(mod(n + 1, 33) - 1, 4);

    if (leap === -1) {
        leap = 4;
    }

    return { leap, gy, march };
}

export function isLeap(jy) {
    return jalCal(jy).leap === 0;
}

export function monthLength(jy, jm) {
    if (jm <= 6) {
        return 31;
    }

    if (jm <= 11) {
        return 30;
    }

    return isLeap(jy) ? 30 : 29;
}

function j2d(jy, jm, jd) {
    const r = jalCal(jy);

    return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
}

function d2j(jdn) {
    const gy = d2g(jdn).gy;
    let jy = gy - 621;
    const r = jalCal(jy);
    const jdn1f = g2d(gy, 3, r.march);
    let k = jdn - jdn1f;

    if (k >= 0) {
        if (k <= 185) {
            return { jy, jm: 1 + div(k, 31), jd: mod(k, 31) + 1 };
        }

        k -= 186;
    } else {
        jy -= 1;
        k += 179;

        if (r.leap === 1) {
            k += 1;
        }
    }

    return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 };
}

export const toJalali = (gy, gm, gd) => d2j(g2d(gy, gm, gd));

export const toGregorian = (jy, jm, jd) => d2g(j2d(jy, jm, jd));

// ------------------------------------------------------------------ ISO و کمکی‌ها

export function parseIso(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso ?? ''));

    if (!m) {
        return null;
    }

    const [gy, gm, gd] = [Number(m[1]), Number(m[2]), Number(m[3])];
    const probe = new Date(Date.UTC(gy, gm - 1, gd));

    if (probe.getUTCFullYear() !== gy || probe.getUTCMonth() !== gm - 1 || probe.getUTCDate() !== gd) {
        return null;
    }

    return { gy, gm, gd };
}

export const isoOf = (gy, gm, gd) => `${String(gy).padStart(4, '0')}-${pad(gm)}-${pad(gd)}`;

export function jalaliOfIso(iso) {
    const p = parseIso(iso);

    return p ? toJalali(p.gy, p.gm, p.gd) : null;
}

export function isoOfJalali(jy, jm, jd) {
    const g = toGregorian(jy, jm, jd);

    return isoOf(g.gy, g.gm, g.gd);
}

/** شنبه = ۰ … جمعه = ۶ */
export function weekdayIndex(gy, gm, gd) {
    return (new Date(Date.UTC(gy, gm - 1, gd)).getUTCDay() + 1) % 7;
}

/** امروز به وقت تهران (هم‌منطقه با سرور). */
export function todayIso() {
    try {
        return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tehran' }).format(new Date());
    } catch {
        const d = new Date();

        return isoOf(d.getFullYear(), d.getMonth() + 1, d.getDate());
    }
}

export function addMonths(jy, jm, delta) {
    const index = jy * 12 + (jm - 1) + delta;

    return { jy: Math.floor(index / 12), jm: mod(index, 12) + 1 };
}

export function holidayOf(jm, jd, weekday) {
    return FIXED_HOLIDAYS[`${jm}/${jd}`] ?? (weekday === 6 ? 'جمعه' : null);
}

/**
 * شبکه‌ی ۶ هفته‌ای (۴۲ خانه) یک ماه شمسی؛ روزهای ماه قبل/بعد هم برای پر کردن هفته‌ها می‌آیند.
 */
export function monthGrid(jy, jm) {
    const first = toGregorian(jy, jm, 1);
    const offset = weekdayIndex(first.gy, first.gm, first.gd);
    const startJdn = g2d(first.gy, first.gm, first.gd) - offset;
    const cells = [];

    for (let i = 0; i < 42; i += 1) {
        const g = d2g(startJdn + i);
        const j = d2j(startJdn + i);
        const weekday = i % 7;

        cells.push({
            iso: isoOf(g.gy, g.gm, g.gd),
            jy: j.jy,
            jm: j.jm,
            jd: j.jd,
            gd: g.gd,
            weekday,
            inMonth: j.jy === jy && j.jm === jm,
            friday: weekday === 6,
            holiday: FIXED_HOLIDAYS[`${j.jm}/${j.jd}`] ?? null,
        });
    }

    return cells;
}

export function formatJalali(iso, withWeekday = false) {
    const p = parseIso(iso);

    if (!p) {
        return '';
    }

    const j = toJalali(p.gy, p.gm, p.gd);
    const text = `${fa(j.jd)} ${MONTHS[j.jm - 1]} ${fa(j.jy)}`;

    return withWeekday ? `${WEEKDAYS[weekdayIndex(p.gy, p.gm, p.gd)]} ${text}` : text;
}

// ------------------------------------------------------------------ Alpine

export function registerJalali(Alpine) {
    /**
     * تقویم ماهانه‌ی وظایف.
     * ریشه باید `data-events` (JSON آرایه‌ای از {id,kind,title,date,status,priority,overdue,who}) داشته باشد.
     * با هر رندر Livewire، `data-events` عوض می‌شود و کامپوننت بدون از دست دادن ماه/روز انتخابی دوباره می‌خواند.
     */
    Alpine.data('jalaliCalendar', () => ({
        jy: 0,
        jm: 0,
        selected: '',
        today: '',
        events: [],
        observer: null,
        months: MONTHS,
        weekdays: WEEKDAYS,
        weekdaysShort: WEEKDAYS_SHORT,

        init() {
            this.today = todayIso();
            const t = jalaliOfIso(this.today);
            this.jy = t.jy;
            this.jm = t.jm;
            this.selected = this.today;
            this.load();

            this.observer = new MutationObserver(() => this.load());
            this.observer.observe(this.$el, { attributes: true, attributeFilter: ['data-events'] });
        },

        destroy() {
            this.observer?.disconnect();
        },

        load() {
            try {
                const parsed = JSON.parse(this.$el.dataset.events || '[]');
                this.events = Array.isArray(parsed) ? parsed : [];
            } catch {
                this.events = [];
            }
        },

        fa,

        get title() {
            return `${MONTHS[this.jm - 1]} ${fa(this.jy)}`;
        },

        get years() {
            const list = [];

            for (let y = this.jy - 6; y <= this.jy + 6; y += 1) {
                list.push(y);
            }

            return list;
        },

        get byDate() {
            const map = {};

            for (const e of this.events) {
                (map[e.date] ??= []).push(e);
            }

            return map;
        },

        get grid() {
            const map = this.byDate;

            return monthGrid(this.jy, this.jm).map((cell) => ({ ...cell, items: map[cell.iso] ?? [] }));
        },

        get monthCount() {
            return this.grid.filter((c) => c.inMonth).reduce((sum, c) => sum + c.items.length, 0);
        },

        get agenda() {
            return this.byDate[this.selected] ?? [];
        },

        get selectedLabel() {
            return formatJalali(this.selected, true);
        },

        get selectedGregorian() {
            const p = parseIso(this.selected);

            return p ? `${p.gy}/${pad(p.gm)}/${pad(p.gd)}` : '';
        },

        get selectedHoliday() {
            const j = jalaliOfIso(this.selected);

            if (!j) {
                return null;
            }

            const p = parseIso(this.selected);

            return FIXED_HOLIDAYS[`${j.jm}/${j.jd}`] ?? (weekdayIndex(p.gy, p.gm, p.gd) === 6 ? 'جمعه' : null);
        },

        prev() {
            ({ jy: this.jy, jm: this.jm } = addMonths(this.jy, this.jm, -1));
        },

        next() {
            ({ jy: this.jy, jm: this.jm } = addMonths(this.jy, this.jm, 1));
        },

        goToday() {
            const t = jalaliOfIso(this.today);
            this.jy = t.jy;
            this.jm = t.jm;
            this.selected = this.today;
        },

        select(cell) {
            this.selected = cell.iso;

            if (!cell.inMonth) {
                this.jy = cell.jy;
                this.jm = cell.jm;
            }
        },

        setYear(value) {
            this.jy = Number(value);
        },

        setMonth(value) {
            this.jm = Number(value);
        },

        chipClass(e) {
            if (e.kind === 'milestone') {
                return 'bg-flag-300/70 text-ink-900';
            }

            if (e.overdue) {
                return 'bg-signal-50 text-signal-600';
            }

            if (e.status === 'done') {
                return 'bg-success-500/15 text-success-500 line-through';
            }

            if (e.status === 'cancelled') {
                return 'bg-ink-100 text-ink-400 line-through';
            }

            if (e.status === 'blocked') {
                return 'bg-flag-300/40 text-ink-800';
            }

            return e.status === 'doing' ? 'bg-pool-100 text-pool-700' : 'bg-pool-50 text-pool-700';
        },

        dotClass(e) {
            if (e.kind === 'milestone') {
                return 'bg-flag-500';
            }

            if (e.overdue) {
                return 'bg-signal-500';
            }

            if (e.status === 'done') {
                return 'bg-success-500';
            }

            return e.status === 'doing' ? 'bg-pool-500' : 'bg-pool-300';
        },
    }));

    /**
     * انتخاب‌گر تاریخ شمسی. مقدار (value) رشته‌ی ISO میلادی است؛ با
     * `<div x-data="jalaliPicker" x-modelable="value" wire:model="field">` به Livewire وصل می‌شود.
     */
    Alpine.data('jalaliPicker', () => ({
        value: '',
        open: false,
        jy: 0,
        jm: 0,
        today: '',
        months: MONTHS,
        weekdaysShort: WEEKDAYS_SHORT,

        init() {
            this.today = todayIso();
            this.syncView();
            this.$watch('value', () => this.syncView());
        },

        fa,

        syncView() {
            const base = jalaliOfIso(this.value) ?? jalaliOfIso(this.today);
            this.jy = base.jy;
            this.jm = base.jm;
        },

        get display() {
            return this.value ? formatJalali(this.value) : '';
        },

        get title() {
            return `${MONTHS[this.jm - 1]} ${fa(this.jy)}`;
        },

        get grid() {
            return monthGrid(this.jy, this.jm);
        },

        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.syncView();
            }
        },

        prev() {
            ({ jy: this.jy, jm: this.jm } = addMonths(this.jy, this.jm, -1));
        },

        next() {
            ({ jy: this.jy, jm: this.jm } = addMonths(this.jy, this.jm, 1));
        },

        pick(cell) {
            this.value = cell.iso;
            this.open = false;
        },

        pickToday() {
            this.value = this.today;
            this.open = false;
        },

        clear() {
            this.value = '';
            this.open = false;
        },
    }));
}
