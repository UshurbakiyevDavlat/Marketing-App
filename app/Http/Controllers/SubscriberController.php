<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use Illuminate\Support\Facades\DB;
use League\Csv\Exception as CsvException;

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
     * Импорт подписчиков через CSV.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function import(Request $request): JsonResponse
    {
        $path = false;

        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt',
            ]);

            $file = $request->file('file');
            $path = $file->store('/imports', ['disk' => 'public']);
            $filePath = public_path('storage/' . $path);

            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);

            $stmt = (new Statement())->limit(1000); // Ограничиваем чтение файла для тестирования
            $records = $stmt->process($csv);

            $errors = [];
            $subscribers = [];

            foreach ($records as $index => $record) {
                $validator = Validator::make($record, [
                    'email' => 'required|email|unique:subscribers,email',
                    'name' => 'required|string|max:255',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $index + 1,
                        'email' => $record['email'] ?? 'N/A',
                        'errors' => $validator->errors()->all()
                    ];
                } else {
                    $subscribers[] = [
                        'email' => $record['email'],
                        'name' => $record['name'],
                        'created_at' => now(),
                        'updated_at' => now(),
                        'user_id' => $request->user()->getAuthIdentifier(),
                        'tags' => isset($record['tags']) ? json_encode($record['tags']) : null,
                    ];
                }
            }

            if (!empty($errors)) {
                Log::error('Validation failed', ['errors' => $errors]);
                Storage::disk('public')->delete($path);

                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $errors,
                ], 422);
            }

            DB::beginTransaction();
            DB::table('subscribers')->insert($subscribers);

            Storage::disk('public')->delete($path);

            DB::commit();

            return response()->json(['message' => 'Subscribers imported successfully'], 201);

        } catch (CsvException $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            DB::rollBack();
            Log::error('CSV processing error: ' . $e->getMessage());

            return response()->json([
                'message' => 'CSV processing error',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            DB::rollBack();
            Log::error('Import subscribers error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to import subscribers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

