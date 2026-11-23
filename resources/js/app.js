/**
 * رفتارهای رابط کاربری — بدون هیچ وابستگی جدید.
 * Alpine همراه Livewire بارگذاری می‌شود؛ همه‌چیز روی `alpine:init` ثبت می‌شود.
 *
 *  x-reveal            یک‌بار هنگام ورود به صفحه کلاس is-in می‌گیرد (برای .reveal / .bar-fill / .ring-fill)
 *  x-inview[.mid][.once]="expr"   وقتی عنصر وارد ناحیه‌ی دید (یا خط میانی صفحه) شد، expr اجرا می‌شود
 *  counter(to)         شمارنده‌ی عددی با ارقام فارسی
 *  countdown(iso)      شمارش معکوس تا یک زمان ISO-8601
 *  jalaliCalendar / jalaliPicker   تقویم و انتخاب‌گر تاریخ شمسی (jalali.js)
 */

import { registerJalali } from './jalali.js';
import './stadium.js';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const fa = (value) => String(value).replace(/\d/g, (d) => FA_DIGITS[d]);
const pad = (n) => String(n).padStart(2, '0');

function register() {
    const Alpine = window.Alpine;

    Alpine.directive('reveal', (el, _binding, { cleanup }) => {
        let guard = null;

        const show = () => el.classList.add('is-in');
        const done = () => {
            show();
            // Livewire هنگام morph ممکن است class را به نسخه‌ی سرور برگرداند؛ دوباره اعمال می‌کنیم.
            guard = new MutationObserver(() => {
                if (!el.classList.contains('is-in')) show();
            });
            guard.observe(el, { attributes: true, attributeFilter: ['class'] });
        };

        if (reduceMotion || !('IntersectionObserver' in window)) {
            done();
            return;
        }

        const io = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    io.disconnect();
                    done();
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -6% 0px' },
        );
        io.observe(el);

        cleanup(() => {
            io.disconnect();
            guard?.disconnect();
        });
    });

    Alpine.directive('inview', (el, { modifiers, expression }, { evaluate, cleanup }) => {
        if (!('IntersectionObserver' in window)) {
            evaluate(expression);
            return;
        }

        const once = modifiers.includes('once');
        const options = modifiers.includes('mid')
            ? { rootMargin: '-45% 0px -45% 0px' }
            : { threshold: 0.35 };

        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                evaluate(expression);
                if (once) io.disconnect();
            });
        }, options);

        io.observe(el);
        cleanup(() => io.disconnect());
    });

    Alpine.data('counter', (to = 0, duration = 1300) => ({
        shown: fa(0),
        ran: false,
        run() {
            if (this.ran) return;
            this.ran = true;

            if (reduceMotion) {
                this.shown = fa(to);
                return;
            }

            const start = performance.now();
            const step = (now) => {
                const p = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                this.shown = fa(Math.round(to * eased));
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        },
    }));

    Alpine.data('countdown', (iso) => ({
        d: fa(0),
        h: fa('00'),
        m: fa('00'),
        s: fa('00'),
        over: false,
        timer: null,
        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },
        destroy() {
            clearInterval(this.timer);
        },
        tick() {
            const diff = new Date(iso).getTime() - Date.now();

            if (Number.isNaN(diff) || diff <= 0) {
                this.over = true;
                this.d = fa(0);
                this.h = this.m = this.s = fa('00');
                clearInterval(this.timer);
                return;
            }

            const total = Math.floor(diff / 1000);
            this.d = fa(Math.floor(total / 86400));
            this.h = fa(pad(Math.floor((total % 86400) / 3600)));
            this.m = fa(pad(Math.floor((total % 3600) / 60)));
            this.s = fa(pad(total % 60));
        },
    }));

    registerJalali(Alpine);
}

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
