<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private download of a task attachment.
 *
 * Files are never publicly served: access = TaskPolicy@view (anyone who can see
 * the event). The route uses scoped bindings (/events/{event}/tasks/{task}/attachments/{attachment}),
 * so an attachment from another task/event resolves to 404 instead of leaking.
 */
final class TaskAttachmentController extends Controller
{
    public function __invoke(Request $request, Event $event, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        abort_unless($request->user()?->can('view', $task), 403);

        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
