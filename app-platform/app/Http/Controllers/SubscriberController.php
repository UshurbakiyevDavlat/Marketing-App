<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscribers = $user->subscribers;
        return response()->json($subscribers);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // todo add validation request class and validate there
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
        ]);

        $subscriber = Subscriber::create([
            'user_id' => $request->user()->getAuthIdentifier(),
            'email' => $validated['email'],
            'name' => $validated['name'],
            'tags' => json_encode($validated['tags']),
        ]);

        return response()->json($subscriber, 201);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'email|max:255',
            'name' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
        ]);

        if (!$validated) {
            throw new \Exception('Nothing to updated', 400);
        }

        $subscriber = Subscriber::findOrFail($id);
        $subscriber->update($validated);

        return response()->json($subscriber);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $subscriber = Subscriber::findOrFail($id);
        $subscriber->delete();

        return response()->json(['message' => 'Subscriber deleted successfully']);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        // Логика импорта подписчиков через CSV
        // Валидация и обработка CSV файла
        // Пример заглушки

        return response()->json(['message' => 'Subscribers imported successfully']);
    }
}

