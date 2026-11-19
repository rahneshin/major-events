<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LimitExceededException extends Exception
{
    public function __construct(
        public readonly string $limitKey,
        public readonly ?int $limit,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : __('account.limit_exceeded', ['limit' => $limitKey]));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'limit_key' => $this->limitKey,
                'limit' => $this->limit,
                'upgrade_url' => route('account.index'),
            ], 403);
        }

        return redirect()
            ->route('account.index')
            ->with('error', $this->getMessage());
    }
}
