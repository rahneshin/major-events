<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PublicCompetitionService;
use App\Services\PublicLinkService;
use Illuminate\Http\Response;

/**
 * Step 12E — صفحه‌ی عمومی فقط‌خواندنی یک ورزش (بدون حساب کاربری).
 * توکن نامعتبر، غیرفعال یا متعلق به ورزش حذف‌شده همه ۴۰۴ می‌دهند (تفکیک نمی‌شوند).
 * فقط داده‌ی «لیست سفید» از PublicCompetitionService به نما می‌رسد.
 */
class PublicCompetitionController extends Controller
{
    public function __invoke(string $token, PublicLinkService $links, PublicCompetitionService $public): Response
    {
        $competition = $links->findByToken($token);

        abort_if($competition === null, 404);

        return response()
            ->view('public.competition', ['data' => $public->snapshot($competition)])
            ->withHeaders([
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'no-store, private',
            ]);
    }
}
