<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Trả kết quả sau khi store/update từ modal.
     *
     * - Request AJAX (modal): flash success + trả JSON {redirect} để JS điều
     *   hướng (validation error đã được FormRequest tự trả 422 JSON).
     * - Request thường: redirect như cũ (graceful fallback).
     */
    protected function modalRedirect(string $url, string $message): JsonResponse|RedirectResponse
    {
        if (request()->wantsJson()) {
            session()->flash('success', $message);

            return response()->json(['redirect' => $url]);
        }

        return redirect($url)->with('success', $message);
    }

    /**
     * True khi request muốn nhận HTML biểu mẫu cho modal (AJAX GET).
     * Dùng ở create()/edit() để quyết định trả partial hay redirect về index.
     */
    protected function wantsModalForm(): bool
    {
        return request()->ajax() && ! request()->wantsJson();
    }

    /**
     * Chuẩn hoá lỗi ValidationException do Service ném ra: AJAX → 422 JSON
     * (để modal vẽ lỗi cạnh field), request thường → back()->withErrors.
     */
    protected function validationResponse(ValidationException $e): JsonResponse|RedirectResponse
    {
        if (request()->wantsJson()) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return back()->withErrors($e->errors())->withInput();
    }
}
