{{--
    استادیوم مینیاتوری با دوربین وابسته به اسکرول.
    ۵ صحنه: ۰ نمای هوایی · ۱ زمین (کمیته‌ها) · ۲ سکوها (نقش‌ها) · ۳ تابلو · ۴ چراغ‌ها (شروع)
    رفتار در resources/js/app.js (Alpine.data('stadium')) و استایل در app.css (.sd-*).
--}}
@props(['demoStart'])

@php
    $rings = [
        ['#0b2e62', '#06183a', 200],
        ['#1b5db0', '#0a2f66', 174],
        ['#2f78d0', '#12407f', 148],
        ['#1b5db0', '#0a2f66', 122],
        ['#4c98f5', '#1a4f96', 96],
    ];

    $sports = [
        'football' => 'فوتبال',
        'volleyball' => 'والیبال',
        'basketball' => 'بسکتبال',
    ];

    // موقعیت پین‌ها به درصدِ صفحه‌ی ۹۶۰×۶۰۰
    $committees = [
        ['key' => 'technical', 'icon' => 'stopwatch', 'x' => 31, 'y' => 44, 't' => 'کمیته‌ی فنی', 'd' => 'برنامه‌ی مسابقات، زمین‌ها و تجهیزات'],
        ['key' => 'medical', 'icon' => 'cross', 'x' => 69, 'y' => 44, 't' => 'کمیته‌ی پزشکی', 'd' => 'ایمنی، کمک‌های اولیه و آمادگی درمانی'],
        ['key' => 'marketing', 'icon' => 'flag', 'x' => 31, 'y' => 66, 't' => 'بازاریابی', 'd' => 'اسپانسرها، تبلیغات و اطلاع‌رسانی'],
        ['key' => 'volunteers', 'icon' => 'whistle', 'x' => 69, 'y' => 66, 't' => 'داوطلبان', 'd' => 'جذب، آموزش و برنامه‌ی نیروهای داوطلب'],
    ];

    $roles = [
        ['key' => 'owner', 'icon' => 'trophy', 'x' => 50, 'y' => 5, 't' => 'مدیر رویداد', 'd' => 'همه‌ی رویداد را مدیریت می‌کند'],
        ['key' => 'head', 'icon' => 'whistle', 'x' => 94, 'y' => 50, 't' => 'رئیس کمیته', 'd' => 'وظایف و اعضای کمیته‌ی خودش را اداره می‌کند'],
        ['key' => 'member', 'icon' => 'medal', 'x' => 50, 'y' => 95, 't' => 'عضو', 'd' => 'کارهای کمیته را انجام می‌دهد'],
        ['key' => 'viewer', 'icon' => 'eye', 'x' => 6, 'y' => 50, 't' => 'ناظر', 'd' => 'همه‌چیز را می‌بیند، ولی ویرایش نمی‌کند'],
    ];
@endphp

<section id="stadium" class="stadium-wrap" x-data="stadium()" :data-scene="scene" :class="{ 'is-static': reduce }"
         x-inview.mid="active = ''">
    <div class="stadium-sticky">
        <div class="stadium-sky" aria-hidden="true"></div>
        <div class="stadium-night" aria-hidden="true" x-ref="night"></div>

        {{-- صحنه‌ی سه‌بعدی --}}
        <div class="stadium-stage" x-ref="stage" role="img"
             aria-label="نمای مینیاتوری یک استادیوم؛ با اسکرول کردن دوربین روی زمین، سکوها و تابلو می‌رود.">
            <div class="sd-plane">
                <div class="sd-plaza"></div>

                {{-- سکوها: پله‌پله، بیرونی بلندتر --}}
                @foreach ($rings as $i => [$top, $riser, $radius])
                    @foreach ([2, 1, 0] as $d)
                        <div class="sd-ring"
                             style="inset: {{ $i * 26 }}px; --r: {{ $radius }}px; --c: {{ $d === 0 ? $top : $riser }}; --z: {{ (4 - $i) * 16 - $d * 5 + 6 }}px"></div>
                    @endforeach
                @endforeach

                {{-- پیست دو و میدانی --}}
                <svg class="sd-track" viewBox="0 0 700 340" aria-hidden="true">
                    <rect x="0" y="0" width="700" height="340" rx="170" fill="#b84a32"/>
                    @foreach ([10, 22, 34, 46] as $a)
                        <rect x="{{ $a }}" y="{{ $a }}" width="{{ 700 - 2 * $a }}" height="{{ 340 - 2 * $a }}" rx="{{ 170 - $a }}" fill="none" stroke="#fff" stroke-opacity=".75" stroke-width="1.6"/>
                    @endforeach
                </svg>

                {{-- سطح بازی: با دکمه‌های نماد ورزش عوض می‌شود --}}
                <div class="sd-pitch">
                    {{-- فوتبال --}}
                    <svg class="sd-surface" viewBox="0 0 588 228" preserveAspectRatio="none" :style="{ opacity: sport === 'football' ? 1 : 0 }">
                        @foreach (range(0, 7) as $b)
                            <rect x="{{ $b * 73.5 }}" y="0" width="73.5" height="228" fill="{{ $b % 2 ? '#1f8f55' : '#27a362' }}"/>
                        @endforeach
                        <g fill="none" stroke="#fff" stroke-width="2" opacity=".9">
                            <rect x="6" y="6" width="576" height="216"/>
                            <line x1="294" y1="6" x2="294" y2="222"/>
                            <circle cx="294" cy="114" r="30"/>
                            <rect x="6" y="54" width="62" height="120"/>
                            <rect x="520" y="54" width="62" height="120"/>
                            <rect x="6" y="86" width="22" height="56"/>
                            <rect x="560" y="86" width="22" height="56"/>
                        </g>
                        <circle cx="294" cy="114" r="3" fill="#fff"/>
                    </svg>
                    {{-- بسکتبال --}}
                    <svg class="sd-surface" viewBox="0 0 588 228" preserveAspectRatio="none" :style="{ opacity: sport === 'basketball' ? 1 : 0 }">
                        <rect width="588" height="228" fill="#c98a4b"/>
                        <g fill="none" stroke="#fff" stroke-width="2" opacity=".92">
                            <rect x="6" y="6" width="576" height="216"/>
                            <line x1="294" y1="6" x2="294" y2="222"/>
                            <circle cx="294" cy="114" r="26"/>
                            <rect x="6" y="84" width="74" height="60"/>
                            <rect x="508" y="84" width="74" height="60"/>
                            <path d="M80 92a22 22 0 010 44M508 92a22 22 0 000 44"/>
                            <path d="M6 34H46A110 110 0 0 1 46 194H6M582 34H542A110 110 0 0 0 542 194H582"/>
                        </g>
                    </svg>
                    {{-- والیبال --}}
                    <svg class="sd-surface" viewBox="0 0 588 228" preserveAspectRatio="none" :style="{ opacity: sport === 'volleyball' ? 1 : 0 }">
                        <rect width="588" height="228" fill="#3f7fc1"/>
                        <rect x="66" y="6" width="456" height="216" fill="#e6a04f"/>
                        <g fill="none" stroke="#fff" stroke-width="2.2">
                            <rect x="66" y="6" width="456" height="216"/>
                            <line x1="218" y1="6" x2="218" y2="222"/>
                            <line x1="370" y1="6" x2="370" y2="222"/>
                        </g>
                        <line x1="294" y1="0" x2="294" y2="228" stroke="#101827" stroke-width="4"/>
                    </svg>
                </div>

                {{-- نور پرژکتورها --}}
                @foreach ([[-30, -30], [730, -30], [-30, 370], [730, 370]] as [$lx, $ly])
                    <div class="sd-light" style="left: {{ $lx + 0 }}px; top: {{ $ly }}px;"></div>
                @endforeach

                {{-- پین‌های کمیته (صحنه‌ی ۱) --}}
                <div class="sd-pins" data-for="1">
                    @foreach ($committees as $c)
                        <div class="sd-anchor" style="left: {{ $c['x'] }}%; top: {{ $c['y'] }}%">
                            <div class="sd-bill">
                                <button type="button" class="sd-pin" @click="pin = pin === '{{ $c['key'] }}' ? null : '{{ $c['key'] }}'"
                                        :aria-expanded="pin === '{{ $c['key'] }}'" aria-label="{{ $c['t'] }}">
                                    <x-sport-icon :name="$c['icon']" class="h-5 w-5" />
                                </button>
                                <span class="sd-pin-label">{{ $c['t'] }}</span>
                                <div class="sd-bubble" x-show="pin === '{{ $c['key'] }}'" x-cloak x-transition.opacity>{{ $c['d'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- پین‌های نقش (صحنه‌ی ۲) --}}
                <div class="sd-pins" data-for="2">
                    @foreach ($roles as $r)
                        <div class="sd-anchor" style="left: {{ $r['x'] }}%; top: {{ $r['y'] }}%">
                            <div class="sd-bill">
                                <button type="button" class="sd-pin" @click="pin = pin === '{{ $r['key'] }}' ? null : '{{ $r['key'] }}'"
                                        :aria-expanded="pin === '{{ $r['key'] }}'" aria-label="{{ $r['t'] }}">
                                    <x-sport-icon :name="$r['icon']" class="h-5 w-5" />
                                </button>
                                <span class="sd-pin-label">{{ $r['t'] }}</span>
                                <div class="sd-bubble" x-show="pin === '{{ $r['key'] }}'" x-cloak x-transition.opacity>{{ $r['d'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- تابلوی امتیاز (صحنه‌ی ۳) --}}
                <div class="sd-pins" data-for="3">
                    <div class="sd-anchor" style="left: 50%; top: 8%">
                        <div class="sd-bill sd-bill-high">
                            <div class="led-panel w-64 rounded-2xl p-4" x-data="countdown('{{ $demoStart }}')">
                                <div class="flex items-center justify-between text-[11px] text-ink-300">
                                    <span>تابلوی رویداد</span>
                                    <span class="rounded bg-white/10 px-1.5 py-0.5">نمونه</span>
                                </div>
                                <div class="mt-3 grid grid-cols-4 gap-1.5">
                                    @foreach ([['d', 'روز'], ['h', 'ساعت'], ['m', 'دقیقه'], ['s', 'ثانیه']] as [$k, $u])
                                        <div class="rounded-lg bg-black/50 py-2 text-center ring-1 ring-white/10">
                                            <p class="led-digit text-xl font-bold" x-text="{{ $k }}">۰۰</p>
                                            <p class="text-[10px] text-ink-300">{{ $u }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-3 flex items-center gap-1.5" aria-hidden="true">
                                    @foreach ([0, 1, 2, 3] as $n)
                                        <span class="h-1.5 flex-1 rounded-full {{ $n < 2 ? 'bg-flag-500' : 'bg-white/15' }}"></span>
                                    @endforeach
                                </div>
                                <p class="mt-1.5 text-[10px] text-ink-300">مرحله‌ی رویداد: برنامه‌ریزی</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- انتخاب ورزش --}}
        <div class="absolute inset-x-0 top-20 z-10 flex justify-center px-4">
            <div class="flex items-center gap-1 rounded-full bg-white/10 p-1 ring-1 ring-white/20 backdrop-blur" role="group" aria-label="انتخاب ورزش">
                @foreach ($sports as $key => $label)
                    <button type="button" @click="sport = '{{ $key }}'" :aria-pressed="sport === '{{ $key }}'"
                            class="flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors"
                            :class="sport === '{{ $key }}' ? 'bg-flag-500 text-ink-950' : 'text-white hover:bg-white/15'">
                        <x-sport-icon :name="$key" class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- متن صحنه‌ها --}}
        <div class="absolute inset-x-4 bottom-5 z-10 sm:inset-x-auto sm:bottom-10 sm:start-10 sm:w-[27rem]">
            <div class="grid">
                {{-- ۰ --}}
                <div class="sd-copy" :class="{ 'is-on': scene === 0 }">
                    <h1 class="text-2xl font-bold leading-[1.5] text-ink-900 sm:text-3xl">رویداد ورزشی‌تان را مثل یک استادیوم بچینید</h1>
                    <p class="mt-3 text-sm leading-7 text-ink-600">کمیته‌ها، اعضا و برنامه را یک‌جا ببینید. با شماره‌ی موبایل وارد می‌شوید و رمز عبوری لازم نیست.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('auth.login') }}" class="btn-primary">ساخت اولین رویداد</a>
                        <button type="button" class="btn-ghost" @click="goTo(1)">تور استادیوم</button>
                    </div>
                    <p class="mt-3 text-xs text-ink-400">پایین بروید؛ دوربین روی استادیوم حرکت می‌کند. ورزش را از بالا عوض کنید.</p>
                </div>
                {{-- ۱ --}}
                <div class="sd-copy" :class="{ 'is-on': scene === 1 }">
                    <p class="text-sm font-semibold text-pool-700">زمین: کمیته‌ها</p>
                    <h2 class="mt-1 text-xl font-bold text-ink-900">هر بخش زمین یک کمیته است</h2>
                    <p class="mt-3 text-sm leading-7 text-ink-600">کمیته‌ی فنی، پزشکی، بازاریابی و داوطلبان را بسازید و رئیس و اعضای هرکدام را مشخص کنید. روی نشان‌ها بزنید.</p>
                </div>
                {{-- ۲ --}}
                <div class="sd-copy" :class="{ 'is-on': scene === 2 }">
                    <p class="text-sm font-semibold text-pool-700">سکوها: تیم و نقش‌ها</p>
                    <h2 class="mt-1 text-xl font-bold text-ink-900">هرکس جای خودش می‌نشیند</h2>
                    <p class="mt-3 text-sm leading-7 text-ink-600">همکاران را با موبایل دعوت کنید و نقش بدهید: مدیر رویداد، رئیس کمیته، عضو یا ناظر.</p>
                </div>
                {{-- ۳ --}}
                <div class="sd-copy" :class="{ 'is-on': scene === 3 }">
                    <p class="text-sm font-semibold text-pool-700">تابلو: وضعیت یک‌نگاه</p>
                    <h2 class="mt-1 text-xl font-bold text-ink-900">تا شروع مسابقه چقدر مانده؟</h2>
                    <p class="mt-3 text-sm leading-7 text-ink-600">داشبورد شمارش معکوس و مرحله‌ی هر رویداد را نشان می‌دهد. وظایف، بودجه و ریسک به‌زودی به همین تابلو اضافه می‌شوند.</p>
                </div>
                {{-- ۴ --}}
                <div class="sd-copy" :class="{ 'is-on': scene === 4 }">
                    <p class="text-sm font-semibold text-pool-700">چراغ‌ها روشن شد</p>
                    <h2 class="mt-1 text-xl font-bold text-ink-900">استادیوم رویداد شما آماده است</h2>
                    <p class="mt-3 text-sm leading-7 text-ink-600">شماره‌ی موبایل را وارد کنید و اولین رویدادتان را کمتر از یک دقیقه بسازید.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('auth.login') }}" class="btn-primary">ورود با موبایل</a>
                        <a href="#features" class="btn-ghost">دیدن امکانات</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- نقطه‌های صحنه --}}
        <div class="absolute end-3 top-1/2 z-10 flex -translate-y-1/2 flex-col gap-3 sm:end-6">
            @foreach (['نمای هوایی', 'زمین', 'سکوها', 'تابلو', 'چراغ‌ها'] as $i => $name)
                <button type="button" @click="goTo({{ $i }})" aria-label="{{ $name }}" :aria-current="scene === {{ $i }}"
                        class="h-2.5 w-2.5 rounded-full ring-2 ring-white/60 transition-all"
                        :class="scene === {{ $i }} ? 'scale-125 bg-flag-500' : 'bg-white/20 hover:bg-white/60'"></button>
            @endforeach
        </div>
    </div>
</section>
