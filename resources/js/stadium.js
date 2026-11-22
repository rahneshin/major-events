/**
 * استادیوم مینیاتوری صفحه‌ی اصلی: دوربین وابسته به اسکرول.
 * از app.js با `import './stadium.js';` وارد می‌شود و روی `alpine:init` ثبت می‌شود.
 * مصرف: <section x-data="stadium()"> (resources/views/components/stadium.blade.php)
 */

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function register() {
    const Alpine = window.Alpine;

    // ---- استادیوم مینیاتوری: دوربین وابسته به اسکرول ----
    const lerp = (a, b, t) => a + (b - a) * t;
    const ease = (t) => t * t * (3 - 2 * t);
    const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
    // صحنه‌ها: ۰ هوایی · ۱ زمین · ۲ سکوها · ۳ تابلو · ۴ چراغ‌ها
    const CAMS = [
        { rx: 60, rz: -28, s: 0.78, tx: 0, ty: 30, night: 0 },
        { rx: 46, rz: -6, s: 1.5, tx: 0, ty: 10, night: 0.1 },
        { rx: 62, rz: 34, s: 1.2, tx: 0, ty: 20, night: 0.25 },
        { rx: 66, rz: 0, s: 1.15, tx: 0, ty: 110, night: 0.45 },
        { rx: 56, rz: 150, s: 0.82, tx: 0, ty: 30, night: 1 },
    ];

    Alpine.data('stadium', () => ({
        scene: 0,
        sport: 'football',
        pin: null,
        reduce: reduceMotion,
        raf: null,
        onScroll: null,
        init() {
            this.onScroll = () => {
                if (this.raf) return;
                this.raf = requestAnimationFrame(() => {
                    this.raf = null;
                    this.apply();
                });
            };
            window.addEventListener('scroll', this.onScroll, { passive: true });
            window.addEventListener('resize', this.onScroll);
            this.apply();
        },
        destroy() {
            window.removeEventListener('scroll', this.onScroll);
            window.removeEventListener('resize', this.onScroll);
            if (this.raf) cancelAnimationFrame(this.raf);
        },
        progress() {
            const r = this.$root.getBoundingClientRect();
            const total = r.height - window.innerHeight;
            return total > 0 ? clamp(-r.top / total, 0, 1) : 0;
        },
        apply() {
            const f = this.progress() * (CAMS.length - 1);
            const i = Math.min(CAMS.length - 2, Math.floor(f));
            const frac = f - i;
            const t = reduceMotion ? Math.round(frac) : ease(frac);
            const a = CAMS[i];
            const b = CAMS[i + 1];
            const k = clamp(window.innerWidth / 1000, 0.42, 1);
            const lift = window.innerWidth < 640 ? 80 : 0; // جا برای پنل متن در موبایل

            const root = this.$root;
            root.style.setProperty('--rx', `${lerp(a.rx, b.rx, t).toFixed(2)}deg`);
            root.style.setProperty('--rz', `${lerp(a.rz, b.rz, t).toFixed(2)}deg`);
            root.style.setProperty('--s', (lerp(a.s, b.s, t) * k).toFixed(3));
            root.style.setProperty('--tx', `${(lerp(a.tx, b.tx, t) * k).toFixed(1)}px`);
            root.style.setProperty('--ty', `${(lerp(a.ty, b.ty, t) * k - lift).toFixed(1)}px`);
            root.style.setProperty('--night', lerp(a.night, b.night, t).toFixed(3));

            const next = Math.round(f);
            if (next !== this.scene) {
                this.scene = next;
                this.pin = null;
            }
        },
        goTo(index) {
            const r = this.$root.getBoundingClientRect();
            const total = r.height - window.innerHeight;
            const top = window.scrollY + r.top + total * (index / (CAMS.length - 1));
            window.scrollTo({ top, behavior: reduceMotion ? 'auto' : 'smooth' });
        },
    }));

}

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
