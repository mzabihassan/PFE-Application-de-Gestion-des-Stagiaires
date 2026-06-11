<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $conversationUserId = (int) $request->query('user');
        $q = trim((string) $request->query('q', ''));

        $allMessages = Message::query()
            ->with(['sender', 'receiver'])
            ->where(function ($query) use ($user) {
                $query->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->latest()
            ->get();

        $conversations = $allMessages
            ->groupBy(function (Message $message) use ($user): int {
                return $message->sender_id === $user->id
                    ? (int) $message->receiver_id
                    : (int) $message->sender_id;
            })
            ->map(function ($messages, $partnerId) use ($user) {
                $lastMessage = $messages->first();
                $unreadCount = $messages->where('receiver_id', $user->id)->where('is_read', false)->count();
                $partner = $lastMessage->sender_id === $user->id
                    ? $lastMessage->receiver
                    : $lastMessage->sender;

                return [
                    'partner' => $partner,
                    'last_message' => $lastMessage,
                    'unread_count' => $unreadCount,
                ];
            })
            ->sortByDesc(fn ($item) => $item['last_message']?->created_at)
            ->values();

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $conversations = $conversations
                ->filter(fn ($item) => str_contains(mb_strtolower($item['partner']?->full_name ?? ''), $needle))
                ->values();
        }

        $conversationUser = null;
        $conversationMessages = collect();

        if ($conversationUserId > 0) {
            $conversationMessages = $allMessages
                ->filter(function (Message $message) use ($user, $conversationUserId) {
                    return (
                        $message->sender_id === $user->id
                        && (int) $message->receiver_id === $conversationUserId
                    ) || (
                        $message->receiver_id === $user->id
                        && (int) $message->sender_id === $conversationUserId
                    );
                })
                ->sortBy('created_at')
                ->values();

            $conversationUser = $conversationMessages->isNotEmpty()
                ? ($conversationMessages->first()->sender_id === $user->id
                    ? $conversationMessages->first()->receiver
                    : $conversationMessages->first()->sender)
                : User::query()->find($conversationUserId);

            if ($conversationUser !== null) {
                Message::query()
                    ->where('sender_id', $conversationUser->id)
                    ->where('receiver_id', $user->id)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
            }
        }

        return view('messages.index', compact('conversations', 'conversationUser', 'conversationMessages', 'q'));
    }

    public function create(): View
    {
        $users = User::query()
            ->where('is_active', true)
            ->where('id', '!=', auth()->id())
            ->orderBy('full_name')
            ->get();

        return view('messages.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string'],
        ]);

        Message::query()->create([
            'sender_id' => $request->user()->id,
            'receiver_id' => (int) $validated['receiver_id'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'is_read' => false,
        ]);

        return redirect()->route('messages.index')->with('success', 'Message envoyé.');
    }

    public function show(Message $message): View
    {
        $userId = auth()->id();

        if ($message->sender_id !== $userId && $message->receiver_id !== $userId) {
            abort(403, 'Accès au message interdit.');
        }

        if ($message->receiver_id === $userId && ! $message->is_read) {
            $message->update(['is_read' => true]);
        }

        $message->load(['sender', 'receiver']);

        return view('messages.show', compact('message'));
    }

    public function markAsRead(Message $message): JsonResponse
    {
        if ($message->receiver_id !== auth()->id()) {
            abort(403, 'Accès interdit.');
        }

        $message->update(['is_read' => true]);

        return response()->json(['message' => 'Message marque comme lu.']);
    }
}
