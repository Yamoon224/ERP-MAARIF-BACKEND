<?php

namespace App\Domains\Notifications\Http\Controllers;

use App\Domains\Notifications\Http\Resources\NotificationLogResource;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Journal des notifications envoyees aux tuteurs (cahier des charges 3.3). */
class NotificationLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationLogResource::collection(
            NotificationLog::query()
                ->with('student:id,first_name,last_name')
                ->when($request->string('student_id')->toString(), fn ($query, $id) => $query->where('student_id', $id))
                ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 15))
                ->withQueryString(),
        );
    }
}
